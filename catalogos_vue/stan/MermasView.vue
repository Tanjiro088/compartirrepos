<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Mermas</h3>
                
                <!-- Barra de Acciones y Búsqueda Principal -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <button class="btn btn-outline-danger me-2" @click="abrirModalRegistro">
                            + Registrar Merma
                        </button>
                        <button class="btn btn-outline-warning me-2" @click="exportarExcel">
                            Exportar Excel
                        </button>
                    </div>
                    <div class="w-50">
                        <input type="text" class="form-control" v-model="busquedaMermas" placeholder="Buscar por folio, tipo o almacén...">
                    </div>
                </div>

                <!-- Tabla Principal de Historial -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Monto Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="merma in mermasFiltradas" :key="merma.id_merma">
                                <td class="fw-bold">{{ merma.folio }}</td>
                                <td>{{ merma.fecha }}</td>
                                <td>{{ merma.tipo }}</td>
                                <td class="fw-bold" :class="'text-status-' + merma.estado">$ {{ $formatCurrency(merma.monto) }}</td>
                                <td>
                                    <span class="badge" :class="getEstadoMermaBadgeClass(merma.estado)">
                                        {{ merma.estado.toUpperCase() }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" @click="abrirModalVerMerma(merma)">
                                        Ver
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="mermasFiltradas.length === 0">
                                <td colspan="6" class="text-muted py-3">{{ busquedaMermas ? 'No se encontraron mermas con ese criterio.' : 'No existen registros de mermas almacenados.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Paginador
                  :pagina-actual="pagina"
                  :total-paginas="totalPaginas"
                  @cambiar="cambiarPagina"
                />
              </div>
            </div>

        <!-- MODAL 1: REGISTRO DE MERMA -->
        <div class="modal fade" id="registroModal" tabindex="-1" ref="registroModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-transparent text-danger border-bottom border-danger">
                        <h5 class="modal-title fw-bold">Registrar Nueva Merma de Inventario</h5>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 mb-3 text-center fw-bold fs-5">
                            Folio: {{ folioGenerado || 'Generando...' }}
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Fecha</label>
                                <input type="date" class="form-control" v-model="formRegistro.fecha_merma">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Tipo de Merma</label>
                                <select class="form-select" v-model="formRegistro.tipo_merma">
                                    <option value="dañado">Dañado</option>
                                    <option value="vencido">Vencido</option>
                                    <option value="robo">Robo</option>
                                    <option value="extraviado">Extraviado</option>
                                    <option value="devolucion_proveedor">Devolución a Proveedor</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Almacén</label>
                                <select class="form-select" v-model="formRegistro.id_almacen">
                                    <option v-for="a in almacenes" :key="a.id_almacen" :value="a.id_almacen">{{ a.nombre }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label fw-bold">Motivo Detallado</label>
                                <input type="text" class="form-control" v-model="formRegistro.motivo" placeholder="Explique la causa o siniestro...">
                            </div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">Productos a Mermar</h5>
                        
                        <!-- SECCIÓN BUSCADOR REACTIVO (SIMULACIÓN V-SELECT) -->
                        <div class="row mb-4 align-items-end bg-light p-3 rounded border border-light shadow-sm">
                            <div class="col-md-8 position-relative">
                                <label class="form-label fw-bold text-danger">Buscar Artículo Afectado</label>
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Escriba el nombre del artículo para filtrar..."
                                       v-model="busquedaProducto"
                                       @focus="mostrarDropdown = true"
                                       @blur="ocultarDropdownConRetraso">
                                
                                <ul class="dropdown-menu w-100 show shadow" v-if="mostrarDropdown && productosFiltrados.length">
                                    <li v-for="prod in productosFiltrados" :key="prod.id">
                                        <button class="dropdown-item py-2" @click.prevent="seleccionarProducto(prod)">
                                            🔍 {{ prod.nombre }} — <strong class="text-secondary">$ {{$formatCurrency( prod.costo_promedio) }}</strong>
                                        </button>
                                    </li>
                                </ul>
                                <div v-if="mostrarDropdown && busquedaProducto && !productosFiltrados.length" class="text-danger small position-absolute mt-1">
                                    No se encontraron artículos en el catálogo maestro.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-danger w-100 fw-bold" @click="agregarProductoBuscado" :disabled="!productoSeleccionado">
                                    + Incluir en Lista de Pérdidas
                                </button>
                            </div>
                        </div>

                        <!-- TABLA DE DETALLE DE MERMAS -->
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th width="15%">Cantidad</th>
                                        <th width="20%">Costo Unit. ($)</th>
                                        <th width="20%">Subtotal ($)</th>
                                        <th width="10%">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(detalle, index) in detallesMerma" :key="index">
                                        <td class="text-start ps-3 fw-bold text-secondary">{{ detalle.producto }}</td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm text-center fw-bold" v-model.number="detalle.cantidad" min="1">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm text-center" v-model.number="detalle.precio_costo" min="0">
                                        </td>
                                        <td class="fw-bold text-dark">$ {{ $formatCurrency(detalle.cantidad * detalle.precio_costo) }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" @click="eliminarFilaMerma(index)">✖</button>
                                        </td>
                                    </tr>
                                    <tr v-if="detallesMerma.length === 0">
                                        <td colspan="5" class="text-muted py-3">No hay productos en el reporte. Use la barra de búsqueda superior.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- PANEL DE COSTO TOTAL CON ESTILO CLEAN/FLAT -->
                        <div class="row justify-content-end pt-2">
                            <div class="col-md-4 bg-light p-3 rounded border border-light text-end">
                                <span class="text-muted small d-block fw-bold">TOTAL PÉRDIDA ESTIMADA:</span>
                                <span class="fs-3 fw-bold text-danger">$ {{$formatCurrency( totalMermaForm) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-outline-danger fw-bold" @click="confirmarRegistroMerma" :disabled="detallesMerma.length === 0">
                            Guardar Reporte de Merma
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 2: VER / EDITAR DETALLE DE MERMA -->
        <div class="modal fade" id="detalleMermaModal" tabindex="-1" ref="detalleModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-transparent border-bottom"
                         :class="modalModoMerma === 'ver' ? 'text-secondary border-secondary' : 'text-warning border-warning'">
                        <h5 class="modal-title fw-bold">
                            {{ modalModoMerma === 'ver' ? 'Detalle de la Merma (Solo Lectura)' : 'Modificar Merma' }}
                        </h5>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-secondary py-2 mb-3 fw-bold fs-5">
                            Folio: {{ mermaActiva.folio }}
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">1. Datos Generales</h5>
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Fecha</label>
                                <input v-if="modalModoMerma === 'editar'" type="date" class="form-control" v-model="formEdicion.fecha_merma">
                                <div v-else class="form-control-plaintext">{{ mermaActiva.fecha_merma }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Tipo de Merma</label>
                                <select v-if="modalModoMerma === 'editar'" class="form-select" v-model="formEdicion.tipo_merma">
                                    <option value="dañado">Dañado</option>
                                    <option value="vencido">Vencido</option>
                                    <option value="robo">Robo</option>
                                    <option value="extraviado">Extraviado</option>
                                </select>
                                <div v-else class="form-control-plaintext">{{ mermaActiva.tipo_merma }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Almacén</label>
                                <select v-if="modalModoMerma === 'editar'" class="form-select" v-model="formEdicion.id_almacen">
                                    <option v-for="a in almacenes" :key="a.id_almacen" :value="a.id_almacen">{{ a.nombre }}</option>
                                </select>
                                <div v-else class="form-control-plaintext">{{ mermaActiva.almacen }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Estado</label>
                                <div class="form-control-plaintext">
                                    <span class="badge" :class="getEstadoMermaBadgeClass(mermaActiva.estado)">
                                        {{ (mermaActiva.estado || '').toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label fw-bold">Motivo</label>
                                <textarea v-if="modalModoMerma === 'editar'" class="form-control" rows="2" v-model="formEdicion.motivo"></textarea>
                                <div v-else class="form-control-plaintext">{{ mermaActiva.motivo || 'Sin motivo registrado' }}</div>
                            </div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">2. Productos Afectados</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th width="15%">Cantidad</th>
                                        <th width="20%">Costo Unit.</th>
                                        <th width="20%">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="det in mermaActiva.detalles" :key="det.id_detalle_merma">
                                        <td class="text-start ps-3 fw-bold text-secondary">{{ det.producto }}</td>
                                        <td class="fw-bold">{{ det.cantidad }}</td>
                                        <td>$ {{ $formatCurrency(det.precio_costo) }}</td>
                                        <td class="fw-bold text-dark">$ {{ $formatCurrency(det.subtotal) }}</td>
                                    </tr>
                                    <tr v-if="!mermaActiva.detalles || mermaActiva.detalles.length === 0">
                                        <td colspan="4" class="text-muted py-3">Sin productos registrados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row justify-content-end pt-2">
                            <div class="col-md-4 bg-light p-3 rounded border border-light text-end">
                                <span class="text-muted small d-block fw-bold">TOTAL PÉRDIDA:</span>
                                <span class="fs-3 fw-bold text-danger">$ {{ $formatCurrency(mermaActiva.monto_total || 0) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button v-if="modalModoMerma === 'ver' && mermaActiva.estado === 'registrada'"
                                class="btn btn-outline-warning fw-bold" @click="activarEdicionMerma">
                            Editar
                        </button>
                        <button v-if="modalModoMerma === 'editar'"
                                class="btn btn-outline-success fw-bold" @click="guardarEdicionMerma">
                            Guardar Cambios
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import { useToast } from '@/composables/useToast.js'
const { addToast } = useToast()
import { 
    obtenerHistorialMermas, 
    calcularTotalMerma, 
    getEstadoMermaBadgeClass, 
    obtenerProductosMermables,
    obtenerAlmacenes,
    registrarMerma,
    exportarMermas,
    obtenerSiguienteFolioMerma,
    obtenerMerma,
    actualizarMerma
} from '../services/mermasService';

// ============================================================================
// VARIABLES REACTIVAS
// ============================================================================
const registroModalRef = ref(null);
const detalleModalRef = ref(null);
const folioGenerado = ref('')
const modalModoMerma = ref('ver')

const mermaActiva = ref({})
const formEdicion = ref({
    fecha_merma: '',
    tipo_merma: '',
    motivo: '',
    id_almacen: ''
})

const historialMermas = ref([])
const productosMaestros = ref([])
const almacenes = ref([])
const busquedaMermas = ref('')

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value))

const formRegistro = ref({
    tipo_merma: 'dañado',
    motivo: '',
    fecha_merma: new Date().toISOString().slice(0, 10),
    id_almacen: '1'
});
const detallesMerma = ref([]);

const busquedaProducto = ref('');
const productoSeleccionado = ref(null);
const mostrarDropdown = ref(false);

const errors = ref({});

// ============================================================================
// CICLO DE VIDA (HOOKS DE NAVEGACIÓN ASÍNCRONA)
// ============================================================================

onMounted(async () => {
    await cargarDatosDesdeServidor();
});

/**
 * Función: cargarDatosDesdeServidor
 * Propósito: Consultar la lista de mermas y el catálogo maestro desde el backend Laravel.
 */
const cargarDatosDesdeServidor = async () => {
    try {
        const respuesta = await obtenerHistorialMermas({ pagina: pagina.value, por_pagina: porPagina.value })
        historialMermas.value = Array.isArray(respuesta) ? respuesta : (respuesta.mermas || [])
        totalRegistros.value = respuesta.total || historialMermas.value.length
        if (almacenes.value.length === 0) {
            almacenes.value = await obtenerAlmacenes()
        }
    } catch (error) {
        console.error('Error mermas:', error)
        const msg = error?.response?.data?.Mensaje || error?.message || 'Error desconocido.'
        alert('Error al conectar con el servidor de Laravel: ' + msg)
    }
}

const cargarProductosPorAlmacen = async (idAlmacen) => {
    try {
        productosMaestros.value = await obtenerProductosMermables(idAlmacen)
    } catch {
        productosMaestros.value = []
    }
}

watch(() => formRegistro.value.id_almacen, (nuevo) => {
    if (nuevo) cargarProductosPorAlmacen(nuevo)
})

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    cargarDatosDesdeServidor()
}

const exportarExcel = async () => {
  try {
    await exportarMermas({ tipo_merma: filtroTipo || undefined, estado: filtroEstado || undefined })
  } catch {
    addToast('Error al exportar a Excel.', 'danger')
  }
}

// ============================================================================
// PROPIEDADES COMPUTADAS
// ============================================================================

const productosFiltrados = computed(() => {
    const lista = Array.isArray(productosMaestros.value) ? productosMaestros.value : []
    if (!busquedaProducto.value) return lista
    return lista.filter(p => 
        p.nombre.toLowerCase().includes(busquedaProducto.value.toLowerCase())
    )
});

const totalMermaForm = computed(() => calcularTotalMerma(detallesMerma.value));

const mermasFiltradas = computed(() => {
    if (!busquedaMermas.value) return historialMermas.value;
    const q = busquedaMermas.value.toLowerCase();
    return historialMermas.value.filter(m =>
        (m.folio && m.folio.toLowerCase().includes(q)) ||
        (m.tipo && m.tipo.toLowerCase().includes(q)) ||
        (m.almacen && m.almacen.toLowerCase().includes(q))
    );
});

// ============================================================================
// MÉTODOS OPERATIVOS DE LA INTERFAZ
// ============================================================================

const seleccionarProducto = (prod) => {
    productoSeleccionado.value = prod;
    busquedaProducto.value = prod.nombre;
    mostrarDropdown.value = false;
};

const ocultarDropdownConRetraso = () => {
    setTimeout(() => { mostrarDropdown.value = false; }, 200);
};

const agregarProductoBuscado = () => {
    if (!productoSeleccionado.value) return;
    
    const prod = productoSeleccionado.value;
    const indiceExistente = detallesMerma.value.findIndex(item => item.id_producto === prod.id);
    
    if (indiceExistente !== -1) {
        detallesMerma.value[indiceExistente].cantidad += 1;
    } else {
        detallesMerma.value.push({
            id_producto: prod.id,
            producto: prod.nombre,
            cantidad: 1,
            precio_costo: prod.costo_promedio
        });
    }
    
    productoSeleccionado.value = null;
    busquedaProducto.value = '';
};

const eliminarFilaMerma = (index) => {
    detallesMerma.value.splice(index, 1);
};

const abrirModalRegistro = async () => {
    detallesMerma.value = [];
    errors.value = {};
    folioGenerado.value = '';
    formRegistro.value = {
        tipo_merma: 'dañado',
        motivo: '',
        fecha_merma: new Date().toISOString().slice(0, 10),
        id_almacen: almacenes.value[0]?.id_almacen ?? '1'
    };
    Modal.getOrCreateInstance(registroModalRef.value).show();
    try { folioGenerado.value = await obtenerSiguienteFolioMerma() } catch { folioGenerado.value = 'MER-XXXX-XXXX-XX' }
    await cargarProductosPorAlmacen(formRegistro.value.id_almacen)
};

/**
 * Método: confirmarRegistroMerma
 * Propósito: Transmitir la estructura Maestro-Detalle a la API utilizando Axios para crear el reporte permanente.
 */
const confirmarRegistroMerma = async () => {
    if (detallesMerma.value.length === 0) {
        alert('Debe agregar al menos un producto al reporte.')
        return
    }
    try {
        const payload = {
            id_almacen: formRegistro.value.id_almacen,
            id_usuario: 3, // Mock: María López - Almacén
            fecha_merma: formRegistro.value.fecha_merma,
            tipo_merma: formRegistro.value.tipo_merma,
            motivo: formRegistro.value.motivo,
            monto: totalMermaForm.value,
            detalles: detallesMerma.value.map(d => ({
                id_presentacion: d.id_producto,
                cantidad: d.cantidad,
                precio_costo: d.precio_costo,
                observaciones: ''
            }))
        };

        await registrarMerma(payload);
        await cargarDatosDesdeServidor();
        Modal.getInstance(registroModalRef.value)?.hide();
    } catch (error) {
        console.error(error);
        alert("Error al intentar guardar el reporte de merma en la base de datos.");
    }
};

const abrirModalVerMerma = async (merma) => {
    modalModoMerma.value = 'ver'
    try {
        const data = await obtenerMerma(merma.id_merma)
        mermaActiva.value = data
        formEdicion.value = {
            fecha_merma: data.fecha_merma,
            tipo_merma: data.tipo_merma,
            motivo: data.motivo || '',
            id_almacen: data.id_almacen
        }
        Modal.getOrCreateInstance(detalleModalRef.value).show()
    } catch (error) {
        console.error(error)
        alert('Error al cargar el detalle de la merma.')
    }
}

const activarEdicionMerma = () => {
    modalModoMerma.value = 'editar'
}

const guardarEdicionMerma = async () => {
    try {
        await actualizarMerma(mermaActiva.value.id_merma, formEdicion.value)
        await cargarDatosDesdeServidor()
        modalModoMerma.value = 'ver'
        const data = await obtenerMerma(mermaActiva.value.id_merma)
        mermaActiva.value = data
        addToast('Merma actualizada correctamente.', 'success')
    } catch (error) {
        console.error(error)
        const msg = error?.response?.data?.Mensaje || 'Error al actualizar la merma.'
        alert(msg)
    }
};
</script>

<style scoped>
</style>
