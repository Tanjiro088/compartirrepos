<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '../stores/store.js';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';
import Swal from 'sweetalert2';

const store = useGlobalStore();
const url = store.baseUrl;

// Referencias para el control del Lector de Códigos de Barras
const inputBusqueda = ref(null);
const inputCodigo = ref(null);

const productos = ref([]);
const categorias = ref([]);
const marcas = ref([]);
const unidades = ref([]);
const tiposPresentacion = ref([]);
const mostrarFormulario = ref(false);

const filtroCategoria = ref('todas');
const filtroMarca = ref('todas');
const busqueda = ref('');
const filtroEstado = ref('todos');

const form = ref({
  id_producto: null, id_categoria: '', id_marca: '', codigo_interno: '', nombre: '', descripcion: '',
  precio_compra: 0, precio_venta: 0, precio_mayoreo: 0, utilidad: 0, 
  aplica_iva: true, aplica_ieps: false, aplica_ish: false, 
  requiere_lote: false, requiere_serie: false, requiere_caducidad: false, 
  presentaciones: [
    { 
      id_unidad_medida: '', id_tipo_presentacion: '', nombre: '', sku: '', archivo: null, url_imagen: '',
      factor_conversion: 1, stock_minimo: 0, stock_maximo: 0, peso: 0, volumen: 0 
    }
  ]
});

// --- VARIABLES DE PAGINACIÓN ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

watch([filtroEstado, filtroCategoria, filtroMarca, busqueda], () => { paginaActual.value = 1; });

// ===============================
// MANEJO DE IMÁGENES Y PRECIOS
// ===============================

const capturarImagenVariante = (event, index) => {
  form.value.presentaciones[index].archivo = event.target.files[0];
};

const obtenerUrlImagen = (path) => {
  if (!path) return '';
  return url.replace('api/', '') + 'storage/' + path;
};

const calcularUtilidad = () => {
  const compra = parseFloat(form.value.precio_compra) || 0;
  const venta = parseFloat(form.value.precio_venta) || 0;
  if (compra > 0 && venta >= compra) {
    form.value.utilidad = Number((((venta - compra) / compra) * 100).toFixed(2));
  } else {
    form.value.utilidad = 0;
  }
};

const calcularPrecioVenta = () => {
  const compra = parseFloat(form.value.precio_compra) || 0;
  const utilidad = parseFloat(form.value.utilidad) || 0;
  if (compra > 0) {
    form.value.precio_venta = Number((compra + (compra * (utilidad / 100))).toFixed(2));
  }
};

// ===============================
// MODALES RÁPIDOS (CATEGORÍA/MARCA)
// ===============================

const mostrarModalCategoria = ref(false);
const mostrarModalMarca = ref(false);
const nuevaCategoria = ref({ nombre: '', descripcion: '' });
const nuevaMarca = ref({ nombre: '' });

const abrirModalCategoria = () => { nuevaCategoria.value = { nombre: '', descripcion: '' }; mostrarModalCategoria.value = true; };
const cerrarModalCategoria = () => mostrarModalCategoria.value = false;

const guardarNuevaCategoria = async () => {
  if (!nuevaCategoria.value.nombre) return Swal.fire('Atención', 'El nombre es obligatorio', 'warning');
  try {
    const respuesta = await axios.post(`${url}categorias`, nuevaCategoria.value);
    categorias.value.push(respuesta.data.categoria || respuesta.data);
    form.value.id_categoria = (respuesta.data.categoria || respuesta.data).id_categoria;
    cerrarModalCategoria();
    Swal.fire({ title: 'Éxito', text: 'Categoría agregada', icon: 'success', timer: 1500, showConfirmButton: false });
  } catch (error) { Swal.fire('Error', 'No se pudo guardar la categoría.', 'error'); }
};

const abrirModalMarca = () => { nuevaMarca.value = { nombre: '' }; mostrarModalMarca.value = true; };
const cerrarModalMarca = () => mostrarModalMarca.value = false;

const guardarNuevaMarca = async () => {
  if (!nuevaMarca.value.nombre) return Swal.fire('Atención', 'El nombre es obligatorio', 'warning');
  try {
    const respuesta = await axios.post(`${url}marcas`, nuevaMarca.value);
    marcas.value.push(respuesta.data.marca || respuesta.data);
    form.value.id_marca = (respuesta.data.marca || respuesta.data).id_marca;
    cerrarModalMarca();
    Swal.fire({ title: 'Éxito', text: 'Marca agregada', icon: 'success', timer: 1500, showConfirmButton: false });
  } catch (error) { Swal.fire('Error', 'No se pudo guardar la marca.', 'error'); }
};

// ===============================
// PETICIONES PRINCIPALES (AXIOS)
// ===============================

const cargarCatalogos = async () => {
  try {
    const [resProd, resCat, resMar, resUni, resTip] = await Promise.all([
      axios.get(url + 'productos'), axios.get(url + 'categorias'), axios.get(url + 'marcas'),
      axios.get(url + 'unidades-medida'), axios.get(url + 'tipos-presentacion')
    ]);
    productos.value = resProd.data.data || resProd.data.Datos || resProd.data;
    categorias.value = (resCat.data.data || resCat.data.Datos || resCat.data).filter(c => c.activo == 1);
    marcas.value = (resMar.data.data || resMar.data.Datos || resMar.data).filter(m => m.activo == 1);
    unidades.value = (resUni.data.data || resUni.data.Datos || resUni.data).filter(u => u.activo == 1);
    tiposPresentacion.value = (resTip.data.data || resTip.data.Datos || resTip.data).filter(t => t.activo == 1);
  } catch (error) { console.error('Error al cargar catálogos:', error); }
};

const recargarProductos = async () => {
  const respuesta = await axios.get(url + 'productos');
  productos.value = respuesta.data.data || respuesta.data.Datos || respuesta.data;
};

const guardarProducto = async () => {
  if (form.value.precio_venta < form.value.precio_compra) {
    return Swal.fire('Revisa los precios', 'El precio de venta no puede ser menor al de compra.', 'warning'); 
  }
  if (!form.value.presentaciones[0].sku || !form.value.presentaciones[0].id_unidad_medida) {
    return Swal.fire('Faltan datos', 'Completa el SKU y la Unidad de Medida en la presentación principal.', 'warning'); 
  }

  const formData = new FormData();
  formData.append('id_categoria', form.value.id_categoria);
  
  if (form.value.id_marca) {
    formData.append('id_marca', form.value.id_marca);
  }
  
  formData.append('codigo_interno', form.value.codigo_interno || '');
  formData.append('nombre', form.value.nombre);
  formData.append('descripcion', form.value.descripcion || '');
  formData.append('precio_compra', form.value.precio_compra);
  formData.append('precio_venta', form.value.precio_venta);
  formData.append('precio_mayoreo', form.value.precio_mayoreo || 0);
  formData.append('utilidad', form.value.utilidad || 0);
  formData.append('aplica_iva', form.value.aplica_iva ? 1 : 0);
  formData.append('aplica_ieps', form.value.aplica_ieps ? 1 : 0);
  formData.append('aplica_ish', form.value.aplica_ish ? 1 : 0);
  formData.append('requiere_lote', form.value.requiere_lote ? 1 : 0);
  formData.append('requiere_serie', form.value.requiere_serie ? 1 : 0);
  formData.append('requiere_caducidad', form.value.requiere_caducidad ? 1 : 0);
  
  const presentacionesDatos = form.value.presentaciones.map(p => ({
    id_presentacion: p.id_presentacion, id_unidad_medida: p.id_unidad_medida, 
    id_tipo_presentacion: p.id_tipo_presentacion || null, 
    nombre: p.nombre || '', sku: p.sku,
    factor_conversion: p.factor_conversion || 1, 
    stock_minimo: p.stock_minimo || 0, stock_maximo: p.stock_maximo || 0,
    peso: p.peso || 0, volumen: p.volumen || 0
  }));
  
  formData.append('presentaciones', JSON.stringify(presentacionesDatos));

  form.value.presentaciones.forEach((pres, index) => {
    if (pres.archivo) formData.append(`imagen_variante_${index}`, pres.archivo);
  });

  try {
    if (form.value.id_producto) {
      formData.append('_method', 'PUT');
      await axios.post(`${url}productos/${form.value.id_producto}`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    } else {
      await axios.post(`${url}productos`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    }
    await recargarProductos();
    cerrarFormulario();
    Swal.fire({ title: '¡Guardado!', text: 'El producto se registró correctamente.', icon: 'success', timer: 2000, showConfirmButton: false });
  } catch (error) {
    Swal.fire('Ocurrió un error', error.response?.data?.error || 'No se pudo guardar el producto.', 'error');
  }
};

const cambiarEstado = async (prod) => {
  const accion = prod.activo ? 'deshabilitar' : 'habilitar';
  const idSeguro = prod.id_producto || prod.id;
  
  if (!idSeguro) return Swal.fire('Error', 'El producto no tiene un ID válido.', 'error');

  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Producto?`,
    text: `¿Deseas ${accion} ${prod.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: prod.activo ? '#dc3545' : '#198754',
    confirmButtonText: `Sí, ${accion}`
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}productos/${idSeguro}`);
      await recargarProductos();
      Swal.fire({ title: '¡Actualizado!', text: 'El estado del producto ha cambiado.', icon: 'success', timer: 1500, showConfirmButton: false });
    } catch (error) {
      Swal.fire('Error', 'Hubo un problema al cambiar el estado.', 'error');
    }
  }
};

// ===============================
// CONTROLADORES DE INTERFAZ Y COMPUTED
// ===============================

const abrirNuevo = () => {
  form.value = {
    id_producto: null, id_categoria: '', id_marca: '', codigo_interno: '', nombre: '', descripcion: '', 
    precio_compra: 0, precio_venta: 0, precio_mayoreo: 0, utilidad: 0,
    aplica_iva: true, aplica_ieps: false, aplica_ish: false,
    requiere_lote: false, requiere_serie: false, requiere_caducidad: false,
    presentaciones: [{ id_unidad_medida: '', id_tipo_presentacion: '', nombre: '', sku: '', archivo: null, url_imagen: '', factor_conversion: 1, stock_minimo: 0, stock_maximo: 0, peso: 0, volumen: 0 }]
  };
  mostrarFormulario.value = true;
  
  // MAGIA LECTOR: Enfoca el campo de código al abrir el formulario
  nextTick(() => {
    if (inputCodigo.value) inputCodigo.value.focus();
  });
};

const abrirEditar = async (prod) => {
  try {
    const idSeguro = prod.id_producto || prod.id;
    if (!idSeguro) {
      Swal.fire('Falla de Conexión', 'El servidor no está devolviendo el ID del producto.', 'error');
      return;
    }

    const respuesta = await axios.get(`${url}productos/${idSeguro}`);
    const productoCompleto = respuesta.data.data || respuesta.data.Datos || respuesta.data;
    
    const presentacionesBD = productoCompleto.presentaciones || [];
    const presentacionesPreparadas = presentacionesBD.map(p => ({ ...p, archivo: null }));

    form.value = {
      id_producto: productoCompleto.id_producto || productoCompleto.id,
      id_categoria: productoCompleto.id_categoria, 
      id_marca: productoCompleto.id_marca || '',
      codigo_interno: productoCompleto.codigo_interno || '', 
      nombre: productoCompleto.nombre, 
      descripcion: productoCompleto.descripcion || '',
      precio_compra: productoCompleto.precio_compra || 0, 
      precio_venta: productoCompleto.precio_venta || 0, 
      precio_mayoreo: productoCompleto.precio_mayoreo || 0,
      utilidad: productoCompleto.utilidad || 0,
      aplica_iva: productoCompleto.aplica_iva == 1, 
      aplica_ieps: productoCompleto.aplica_ieps == 1, 
      aplica_ish: productoCompleto.aplica_ish == 1,
      requiere_lote: productoCompleto.requiere_lote == 1, 
      requiere_serie: productoCompleto.requiere_serie == 1, 
      requiere_caducidad: productoCompleto.requiere_caducidad == 1,
      presentaciones: presentacionesPreparadas.length > 0 ? presentacionesPreparadas : [{ id_unidad_medida: '', id_tipo_presentacion: '', nombre: '', sku: '', archivo: null, url_imagen: '' }]
    };
    mostrarFormulario.value = true;

    // MAGIA LECTOR: Enfoca el campo de código al editar
    nextTick(() => {
      if (inputCodigo.value) inputCodigo.value.focus();
    });

  } catch (error) { 
    console.error(error);
    Swal.fire('Error', 'No se pudo cargar la información del producto. Verifica el servidor.', 'error'); 
  }
};

const cerrarFormulario = () => { 
  mostrarFormulario.value = false; 
  
  // MAGIA LECTOR: Regresa el foco a la búsqueda general al cerrar el formulario
  nextTick(() => {
    if (inputBusqueda.value) inputBusqueda.value.focus();
  });
};

const agregarPresentacion = () => { form.value.presentaciones.push({ id_unidad_medida: '', id_tipo_presentacion: '', nombre: '', sku: '', archivo: null, url_imagen: '', factor_conversion: 1, stock_minimo: 0, stock_maximo: 0, peso: 0, volumen: 0 }); };
const quitarPresentacion = (index) => { if (form.value.presentaciones.length > 1) form.value.presentaciones.splice(index, 1); };

// --- FILTROS INTELIGENTES ---
const opcionesFiltroCategoria = computed(() => {
  const idsUsados = [...new Set(productos.value.map(p => p.id_categoria).filter(Boolean))];
  const catsConDatos = categorias.value.filter(c => idsUsados.includes(c.id_categoria));
  return [{ id_categoria: 'todas', nombre: 'Todas las categorías...' }, ...catsConDatos];
});

const opcionesFiltroMarca = computed(() => {
  const idsUsados = [...new Set(productos.value.map(p => p.id_marca).filter(Boolean))];
  const marcasConDatos = marcas.value.filter(m => idsUsados.includes(m.id_marca));
  return [{ id_marca: 'todas', nombre: 'Todas las marcas...' }, ...marcasConDatos];
});

const productosFiltrados = computed(() => {
  let resultado = productos.value || [];
  if (filtroEstado.value === 'activos') resultado = resultado.filter(p => p.activo == 1);
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(p => p.activo == 0);
  if (filtroCategoria.value !== 'todas') resultado = resultado.filter(p => p.id_categoria == filtroCategoria.value);
  if (filtroMarca.value !== 'todas') resultado = resultado.filter(p => p.id_marca == filtroMarca.value);
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase();
    resultado = resultado.filter(p => (p.nombre && p.nombre.toLowerCase().includes(termino)) || (p.codigo_interno && p.codigo_interno.toLowerCase().includes(termino)) || (p.sku_principal && p.sku_principal.toLowerCase().includes(termino)));
  }
  return resultado;
});

const totalPaginas = computed(() => Math.ceil(productosFiltrados.value.length / registrosPorPagina.value) || 1);
const productosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
  return productosFiltrados.value.slice(inicio, inicio + registrosPorPagina.value);
});
const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag; };

onMounted(() => { 
  cargarCatalogos(); 

  // MAGIA LECTOR: Enfoca la barra de búsqueda al abrir el módulo
  nextTick(() => {
    if (inputBusqueda.value) inputBusqueda.value.focus();
  });
});
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Gestión de Productos</h2>

    <!-- VISTA DE TABLA -->
    <div v-if="!mostrarFormulario" class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo General</h4>
        <button class="btn btn-primary" @click="abrirNuevo"><i class="bi bi-plus-circle me-1"></i> Nuevo Producto</button>
      </div>
      
      <!-- PANEL DE FILTROS A 4 COLUMNAS -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosProd" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodosProd">Todos</label>

            <input type="radio" class="btn-check" id="estadoActivosProd" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivosProd">Activos</label>

            <input type="radio" class="btn-check" id="estadoInactivosProd" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivosProd">Inactivos</label>
          </div>
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold">Categoría</label>
          <v-select 
            v-model="filtroCategoria" 
            :options="opcionesFiltroCategoria" 
            label="nombre" 
            :reduce="cat => cat.id_categoria"
            :clearable="false"
            class="bg-white"
          ></v-select>
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold">Marca</label>
          <v-select 
            v-model="filtroMarca" 
            :options="opcionesFiltroMarca" 
            label="nombre" 
            :reduce="marca => marca.id_marca"
            :clearable="false"
            class="bg-white"
          ></v-select>
        </div>
        <div class="col-md-3">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <!-- CONEXIÓN LECTOR 1: ref="inputBusqueda" -->
            <input type="text" class="form-control" v-model="busqueda" ref="inputBusqueda" placeholder="Código, SKU o Nombre...">
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 15%">Categoría</th>
              <th style="width: 15%">Código / SKU Principal</th>
              <th style="width: 30%">Producto</th>
              <th style="width: 10%" class="text-center">Precio Base</th>
              <th style="width: 10%" class="text-center">Stock Total</th>
              <th style="width: 10%" class="text-center">Estado</th>
              <th style="width: 10%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="prod in productosPaginados" :key="prod.id_producto || Math.random()">
              <td>
                <div class="text-muted small fw-semibold"><i class="bi bi-tags me-1"></i>{{ prod.categoria_nombre || 'Sin Categoría' }}</div>
                <span class="badge bg-light text-dark border mt-1"><i class="bi bi-award me-1"></i>{{ prod.marca_nombre || 'Sin Marca' }}</span>
              </td>
              <td>
                <div class="fw-bold text-dark">{{ prod.codigo_interno || 'S/C' }}</div>
                <div class="small text-muted">{{ prod.sku_principal || 'Sin SKU' }}</div>
              </td>
              <td class="fw-semibold">
                <div class="d-flex align-items-center">
                  <img v-if="prod.imagen_url" :src="obtenerUrlImagen(prod.imagen_url)" class="rounded shadow-sm me-3 border" style="width: 45px; height: 45px; object-fit: cover;" alt="Prod">
                  <div v-else class="bg-light rounded shadow-sm me-3 border d-flex justify-content-center align-items-center text-muted" style="width: 45px; height: 45px;"><i class="bi bi-box-seam fs-5"></i></div>
                  
                  <div class="text-truncate" style="max-width: 200px;" :title="prod.nombre">
                    {{ prod.nombre || 'Producto sin nombre' }}
                  </div>
                </div>
              </td>
              <td class="text-center text-success fw-bold">${{ prod.precio_venta != null && !isNaN(prod.precio_venta) ? Number(prod.precio_venta).toFixed(2) : '0.00' }}</td>
              <td class="text-center fw-bold" :class="prod.stock_total > 0 ? 'text-dark' : 'text-danger'">{{ prod.stock_total ? parseFloat(prod.stock_total) : '0' }} un.</td>
              <td class="text-center">
                <span class="badge rounded-pill" :class="prod.activo ? 'bg-success' : 'bg-danger'">{{ prod.activo ? 'Activo' : 'Inactivo' }}</span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirEditar(prod)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm" :class="prod.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="prod.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(prod)">
                  <i class="bi" :class="prod.activo ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
              </td>
            </tr>
            <tr v-if="productosFiltrados.length === 0"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron productos.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="productosFiltrados.length > 0">
        <span class="text-muted small">Mostrando {{ productosPaginados.length }} de {{ productosFiltrados.length }} registros</span>
        <nav aria-label="Navegación de páginas" v-if="totalPaginas > 1">
          <ul class="pagination pagination-sm mb-0 shadow-sm">
            <li class="page-item" :class="{ disabled: paginaActual === 1 }"><button class="page-link text-secondary" @click="cambiarPagina(paginaActual - 1)"><i class="bi bi-chevron-left"></i></button></li>
            <li class="page-item" v-for="pag in totalPaginas" :key="pag" :class="{ active: paginaActual === pag }"><button class="page-link" @click="cambiarPagina(pag)">{{ pag }}</button></li>
            <li class="page-item" :class="{ disabled: paginaActual === totalPaginas }"><button class="page-link text-secondary" @click="cambiarPagina(paginaActual + 1)"><i class="bi bi-chevron-right"></i></button></li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- VISTA DE FORMULARIO -->
    <div v-else class="card shadow-sm border-0">
      <div class="card-header bg-white pt-4 pb-3 border-bottom d-flex justify-content-between align-items-center">
        <h4 class="text-primary mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>{{ form.id_producto ? 'Editar Producto' : 'Registrar Nuevo Producto' }}</h4>
        <button class="btn btn-sm btn-outline-secondary" @click="cerrarFormulario"><i class="bi bi-arrow-left me-1"></i> Volver a la lista</button>
      </div>

      <div class="card-body p-4 bg-light">
        <form @submit.prevent="guardarProducto">
          
          <!-- BLOQUE 1: GENERAL -->
          <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4">
              <h5 class="mb-4 text-secondary border-bottom pb-2"><i class="bi bi-info-circle me-2"></i>1. Información General</h5>
              <div class="row g-3">
                <!-- CONEXIÓN LECTOR 2: ref="inputCodigo" y @keydown.enter.prevent -->
                <div class="col-md-4">
                  <label class="form-label text-muted small fw-bold">Código Interno <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.codigo_interno" ref="inputCodigo" @keydown.enter.prevent required>
                </div>
                
                <div class="col-md-8"><label class="form-label text-muted small fw-bold">Nombre del Producto (Ej. Refresco Coca-Cola) <span class="text-danger">*</span></label><input type="text" class="form-control" v-model="form.nombre" required></div>
                
                <div class="col-md-6">
                  <label class="form-label text-muted small fw-bold">Categoría Padre <span class="text-danger">*</span></label>
                  <div class="d-flex">
                    <div class="flex-grow-1">
                      <v-select v-model="form.id_categoria" :options="categorias" label="nombre" :reduce="cat => cat.id_categoria" placeholder="Seleccione..." class="bg-white"></v-select>
                    </div>
                    <button type="button" class="btn btn-outline-primary ms-2 rounded shadow-sm" @click="abrirModalCategoria" title="Nueva Categoría"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
                
                <div class="col-md-6">
                  <label class="form-label text-muted small fw-bold">Marca</label>
                  <div class="d-flex">
                    <div class="flex-grow-1">
                      <v-select v-model="form.id_marca" :options="marcas" label="nombre" :reduce="marca => marca.id_marca" placeholder="Opcional..." class="bg-white"></v-select>
                    </div>
                    <button type="button" class="btn btn-outline-primary ms-2 rounded shadow-sm" @click="abrirModalMarca" title="Nueva Marca"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
                <div class="col-12"><label class="form-label text-muted small fw-bold">Descripción / Especificaciones</label><textarea class="form-control" rows="2" v-model="form.descripcion"></textarea></div>
              </div>
            </div>
          </div>

          <!-- BLOQUE 2: PRECIOS -->
          <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4">
              <h5 class="mb-4 text-secondary border-bottom pb-2"><i class="bi bi-currency-dollar me-2"></i>2. Finanzas e Impuestos</h5>
              <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label text-muted small fw-bold">Precio Compra ($) <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" class="form-control" v-model="form.precio_compra" @input="calcularUtilidad" required></div>
                <div class="col-md-3"><label class="form-label text-muted small fw-bold">Margen Utilidad (%)</label><div class="input-group"><input type="number" step="0.01" min="0" class="form-control bg-white text-primary fw-bold" v-model="form.utilidad" @input="calcularPrecioVenta"><span class="input-group-text bg-light">%</span></div></div>
                <div class="col-md-3"><label class="form-label text-muted small fw-bold">Precio Venta Base ($) <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" class="form-control" v-model="form.precio_venta" @input="calcularUtilidad" required></div>
                <div class="col-md-3"><label class="form-label text-muted small fw-bold">Precio Mayoreo ($)</label><input type="number" step="0.01" min="0" class="form-control" v-model="form.precio_mayoreo"></div>
                
                <div class="col-12 mt-4 border-top pt-3 bg-light rounded px-3 pb-3">
                  <span class="fw-bold d-block mb-3 text-muted small">Impuestos Aplicables:</span>
                  <div class="form-check form-switch form-check-inline me-4">
                    <input class="form-check-input shadow-sm" type="checkbox" id="chkIva" v-model="form.aplica_iva">
                    <label class="form-check-label ms-1" for="chkIva">Aplica IVA (16%)</label>
                  </div>
                  <div class="form-check form-switch form-check-inline me-4">
                    <input class="form-check-input shadow-sm" type="checkbox" id="chkIeps" v-model="form.aplica_ieps">
                    <label class="form-check-label ms-1" for="chkIeps">Aplica IEPS</label>
                  </div>
                  <div class="form-check form-switch form-check-inline">
                    <input class="form-check-input shadow-sm" type="checkbox" id="chkIsh" v-model="form.aplica_ish">
                    <label class="form-check-label ms-1" for="chkIsh">Aplica ISH</label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- BLOQUE 3: TRAZABILIDAD -->
          <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4">
              <h5 class="mb-3 text-secondary border-bottom pb-2"><i class="bi bi-shield-check me-2"></i>3. Reglas de Trazabilidad e Inventario</h5>
              <div class="row">
                <div class="col-md-4 mb-2">
                  <div class="form-check form-switch border rounded p-3 bg-white shadow-sm h-100">
                    <input class="form-check-input ms-0 me-2 mt-1" type="checkbox" v-model="form.requiere_lote">
                    <label class="form-check-label fw-bold text-dark ms-1">Exigir Lote</label>
                    <small class="text-muted d-block mt-1 ms-4" style="font-size: 0.8rem;">Pide lote al ingresar mercancía.</small>
                  </div>
                </div>
                <div class="col-md-4 mb-2">
                  <div class="form-check form-switch border rounded p-3 bg-white shadow-sm h-100">
                    <input class="form-check-input ms-0 me-2 mt-1" type="checkbox" v-model="form.requiere_caducidad">
                    <label class="form-check-label fw-bold text-dark ms-1">Exigir Caducidad</label>
                    <small class="text-muted d-block mt-1 ms-4" style="font-size: 0.8rem;">Alerta de productos por vencer.</small>
                  </div>
                </div>
                <div class="col-md-4 mb-2">
                  <div class="form-check form-switch border rounded p-3 bg-white shadow-sm h-100">
                    <input class="form-check-input ms-0 me-2 mt-1" type="checkbox" v-model="form.requiere_serie">
                    <label class="form-check-label fw-bold text-dark ms-1">Rastreo por Serie</label>
                    <small class="text-muted d-block mt-1 ms-4" style="font-size: 0.8rem;">Para equipos y electrónicos.</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- BLOQUE 4: PRESENTACIONES -->
          <div class="card shadow-sm mb-4 border-0">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                <h5 class="text-secondary mb-0"><i class="bi bi-boxes me-2"></i>4. SKUs, Logística y Presentaciones</h5>
                <button type="button" class="btn btn-sm btn-info text-white fw-bold shadow-sm" @click="agregarPresentacion"><i class="bi bi-plus-lg me-1"></i> Agregar Variante</button>
              </div>

              <div v-for="(pres, index) in form.presentaciones" :key="index" class="border border-2 rounded p-4 bg-white shadow-sm mb-4 position-relative">
                <button v-if="form.presentaciones.length > 1" type="button" class="btn-close position-absolute top-0 end-0 m-3" title="Quitar variante" @click="quitarPresentacion(index)"></button>
                
                <h6 class="text-primary mb-3"><i class="bi bi-bookmark-fill me-1"></i>Variante #{{ index + 1 }}</h6>
                
                <div class="row g-3 align-items-end mb-4">
                  <div class="col-md-3"><label class="form-label text-muted small fw-bold">SKU <span class="text-danger">*</span></label><input type="text" class="form-control text-uppercase" v-model="pres.sku" required></div>
                  <div class="col-md-3"><label class="form-label text-muted small fw-bold">Nombre (Ej. Lata 355ml)</label><input type="text" class="form-control" v-model="pres.nombre"></div>
                  <div class="col-md-3"><label class="form-label text-muted small fw-bold">Unidad Medida <span class="text-danger">*</span></label><select class="form-select" v-model="pres.id_unidad_medida" required><option value="" disabled>Seleccione...</option><option v-for="uni in unidades" :key="uni.id_unidad_medida" :value="uni.id_unidad_medida">{{ uni.abreviatura }} - {{ uni.nombre }}</option></select></div>
                  <div class="col-md-3"><label class="form-label text-muted small fw-bold">Envase / Tipo</label><select class="form-select" v-model="pres.id_tipo_presentacion"><option value="">Ninguno</option><option v-for="tipo in tiposPresentacion" :key="tipo.id_tipo_presentacion" :value="tipo.id_tipo_presentacion">{{ tipo.nombre }}</option></select></div>
                </div>

                <div class="row g-3 align-items-end bg-light p-3 rounded border mb-4 mx-0">
                  <div class="col-md-2"><label class="form-label small fw-bold text-muted">Factor Mul.</label><input type="number" step="1" min="1" class="form-control" v-model="pres.factor_conversion" title="Piezas que contiene (Ej. Six pack = 6)"></div>
                  <div class="col-md-2"><label class="form-label small fw-bold text-muted">Stock Mín.</label><input type="number" step="0.01" class="form-control" v-model="pres.stock_minimo"></div>
                  <div class="col-md-2"><label class="form-label small fw-bold text-muted">Stock Máx.</label><input type="number" step="0.01" class="form-control" v-model="pres.stock_maximo"></div>
                  <div class="col-md-3"><label class="form-label small fw-bold text-muted">Peso (kg)</label><input type="number" step="0.01" class="form-control" v-model="pres.peso"></div>
                  <div class="col-md-3"><label class="form-label small fw-bold text-muted">Volumen (m3)</label><input type="number" step="0.01" class="form-control" v-model="pres.volumen"></div>
                </div>

                <div class="row">
                  <div class="col-md-12">
                    <label class="form-label text-muted small fw-bold"><i class="bi bi-camera me-1"></i>Imagen Fotográfica (Opcional)</label>
                    <div class="d-flex align-items-center mb-3 p-2 border rounded bg-light" v-if="pres.url_imagen">
                      <img :src="obtenerUrlImagen(pres.url_imagen)" class="rounded shadow-sm border bg-white me-3" style="width: 50px; height: 50px; object-fit: cover;" alt="Variante">
                      <span class="text-success small fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Imagen cargada en el servidor.</span>
                    </div>
                    <input type="file" class="form-control" accept="image/*" @change="capturarImagenVariante($event, index)">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end mt-4 pt-3 border-top">
            <button type="button" class="btn btn-outline-secondary me-3 px-4" @click="cerrarFormulario">Cancelar</button>
            <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Producto Completo</button>
          </div>

        </form>
      </div>
    </div>
  </div>

  <!-- MODAL NUEVA CATEGORÍA -->
    <div v-if="mostrarModalCategoria" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light">
            <h5 class="modal-title text-primary fw-bold"><i class="bi bi-tags me-2"></i>Nueva Categoría</h5>
            <button type="button" class="btn-close" @click="cerrarModalCategoria"></button>
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold text-muted small">Nombre de la Categoría <span class="text-danger">*</span></label>
              <input type="text" class="form-control" v-model="nuevaCategoria.nombre" placeholder="Ej. Lácteos" @keyup.enter="guardarNuevaCategoria">
            </div>
          </div>
          <div class="modal-footer bg-light border-top-0">
            <button type="button" class="btn btn-outline-secondary" @click="cerrarModalCategoria">Cancelar</button>
            <button type="button" class="btn btn-primary" @click="guardarNuevaCategoria">Guardar Categoría</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL NUEVA MARCA -->
    <div v-if="mostrarModalMarca" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-light">
            <h5 class="modal-title text-primary fw-bold"><i class="bi bi-award me-2"></i>Nueva Marca</h5>
            <button type="button" class="btn-close" @click="cerrarModalMarca"></button>
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-bold text-muted small">Nombre de la Marca <span class="text-danger">*</span></label>
              <input type="text" class="form-control" v-model="nuevaMarca.nombre" placeholder="Ej. Nestlé" @keyup.enter="guardarNuevaMarca">
            </div>
          </div>
          <div class="modal-footer bg-light border-top-0">
            <button type="button" class="btn btn-outline-secondary" @click="cerrarModalMarca">Cancelar</button>
            <button type="button" class="btn btn-primary" @click="guardarNuevaMarca">Guardar Marca</button>
          </div>
        </div>
      </div>
    </div>
</template>