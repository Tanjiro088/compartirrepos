<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

// Estado global
const store = useGlobalStore()
const url = store.baseUrl

// Estados del componente
const categorias = ref([])
const departamentos = ref([]) 
const modalAbierto = ref(false)
const editando = ref(false)

// Filtros y Búsqueda
const filtroEstado = ref('todos')
const filtroDepartamento = ref('todos')
const busqueda = ref('')

// Objeto para el formulario y validación
const form = ref({ id_categoria: null, id_departamento: '', nombre: '', descripcion: '', activo: true })
const errores = ref({ nombre: '', id_departamento: '' })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

// Reiniciar a la página 1 cuando el usuario busque o filtre
watch([filtroEstado, filtroDepartamento, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerCategorias = async () => {
  try {
    const respuesta = await axios.get(url + 'categorias')
    categorias.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) {
    console.error('Error al obtener categorías:', error)
  }
}

const obtenerDepartamentos = async () => {
  try {
    const respuesta = await axios.get(url + 'departamentos')
    const data = respuesta.data.data || respuesta.data.Datos || respuesta.data
    // Guardamos solo los departamentos activos para el formulario
    departamentos.value = data.filter(d => d.activo == 1)
  } catch (error) {
    console.error('Error al obtener departamentos:', error)
  }
}

const guardarCategoria = async () => {
  validarFormulario()
  if (!formularioValido.value) return
  
  try {
    if (editando.value) {
      await axios.put(`${url}categorias/${form.value.id_categoria}`, form.value)
    } else {
      await axios.post(`${url}categorias`, form.value)
    }
    await obtenerCategorias()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Categoría guardada correctamente.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    console.error('Error al guardar:', error)
    Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al guardar la categoría.' })
  }
}

const cambiarEstado = async (cat) => {
  const accion = cat.activo ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Categoría?`,
    text: `¿Deseas ${accion} la categoría ${cat.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: cat.activo ? '#dc3545' : '#198754',
    confirmButtonText: `Sí, ${accion}`
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...cat, activo: !cat.activo }
      await axios.put(`${url}categorias/${cat.id_categoria}`, datosActualizados)
      await obtenerCategorias()
      Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false })
    } catch (error) {
      console.error('Error al cambiar estado:', error)
      Swal.fire('Error', 'No se pudo cambiar el estado.', 'error')
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Eliminar Categoría?',
    html: `La categoría <b>${nombre}</b> será eliminada permanentemente.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}categorias/${id}`)
      await obtenerCategorias()
      Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false })
    } catch (err) {
      Swal.fire('Error', 'No se pudo eliminar la categoría.', 'error')
    }
  }
}

// ===============================
// CONTROLADORES Y VALIDACIÓN
// ===============================

const validarFormulario = () => {
  const nombreLimpio = form.value.nombre.trim()
  if (!nombreLimpio) errores.value.nombre = 'El nombre es obligatorio.'
  else if (nombreLimpio.length > 100) errores.value.nombre = 'No puede exceder 100 caracteres.'
  else errores.value.nombre = ''

  if (!form.value.id_departamento) errores.value.id_departamento = 'Seleccione un departamento.'
  else errores.value.id_departamento = ''
}

const formularioValido = computed(() => {
  return form.value.nombre.trim().length > 0 && form.value.id_departamento && !errores.value.nombre
})

const abrirModalCrear = () => {
  editando.value = false
  form.value = { id_categoria: null, id_departamento: '', nombre: '', descripcion: '', activo: true }
  errores.value.nombre = ''
  errores.value.id_departamento = ''
  modalAbierto.value = true
}

const abrirModalEditar = (cat) => {
  editando.value = true
  form.value = { ...cat, activo: cat.activo == 1 }
  errores.value.nombre = ''
  errores.value.id_departamento = ''
  modalAbierto.value = true
}

const cerrarModal = () => {
  modalAbierto.value = false
}

// ===============================
// COMPUTEDS (Filtros y Paginación)
// ===============================

// MAGIA DE UX: Este filtro extrae SOLO los departamentos que están siendo usados en la tabla
const opcionesFiltroDepartamento = computed(() => {
  const idsUsados = [...new Set(categorias.value.map(c => c.id_departamento))]
  const departamentosConDatos = departamentos.value.filter(d => idsUsados.includes(d.id_departamento))
  
  return [{ id_departamento: 'todos', nombre: 'Todos...' }, ...departamentosConDatos]
})

const categoriasFiltradas = computed(() => {
  let resultado = categorias.value

  // 1. Filtro por Estado
  if (filtroEstado.value === 'activos') resultado = resultado.filter(c => c.activo == 1)
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(c => c.activo == 0)

  // 2. Filtro por Departamento
  if (filtroDepartamento.value !== 'todos') {
    resultado = resultado.filter(c => c.id_departamento == filtroDepartamento.value)
  }

  // 3. Filtro por Búsqueda de texto
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(c => c.nombre.toLowerCase().includes(termino))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(categoriasFiltradas.value.length / registrosPorPagina.value) || 1)

const categoriasPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return categoriasFiltradas.value.slice(inicio, inicio + registrosPorPagina.value)
})

const cambiarPagina = (pag) => {
  if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag
}

onMounted(() => {
  obtenerCategorias()
  obtenerDepartamentos()
})
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Módulo de Categorías</h2>

    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Categorías</h4>
        <button class="btn btn-primary" @click="abrirModalCrear">
          <i class="bi bi-plus-circle me-1"></i> Nueva Categoría
        </button>
      </div>

      <!-- PANEL DE FILTROS A 3 COLUMNAS -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        
        <!-- Filtro Estado -->
        <div class="col-md-4 mb-3 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodos" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodos">Todas</label>

            <input type="radio" class="btn-check" id="estadoActivos" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivos">Activas</label>

            <input type="radio" class="btn-check" id="estadoInactivos" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivos">Inactivas</label>
          </div>
        </div>

        <!-- Filtro Departamento (Solo con datos) -->
        <div class="col-md-4 mb-3 mb-md-0">
          <label class="form-label text-muted small fw-bold">Filtrar por Departamento</label>
          <v-select 
            v-model="filtroDepartamento" 
            :options="opcionesFiltroDepartamento" 
            label="nombre" 
            :reduce="dept => dept.id_departamento"
            :clearable="false"
            class="bg-white"
          ></v-select>
        </div>
        
        <!-- Búsqueda -->
        <div class="col-md-4">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar por nombre...">
          </div>
        </div>
      </div>

      <!-- TABLA CON ANCHOS Y ESPACIADOS REAJUSTADOS -->
      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 7%">ID</th>
              <th style="width: 18%">Departamento Padre</th>
              <th style="width: 20%">Nombre de Categoría</th>
              <th style="width: 33%" class="px-3">Descripción</th>
              <th style="width: 8%" class="text-center">Estado</th>
              <th style="width: 14%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cat in categoriasPaginadas" :key="cat.id_categoria">
              <td><span class="badge bg-dark mb-1">#{{ cat.id_categoria }}</span></td>
              <td><span class="badge bg-secondary text-wrap lh-base"><i class="bi bi-diagram-3 me-1"></i>{{ cat.departamento_nombre || 'Desconocido' }}</span></td>
              <td class="fw-bold text-primary">{{ cat.nombre }}</td>
              
              <!-- Celda de descripción con padding lateral (px-4) para que respire -->
              <td class="px-4">
                <span v-if="cat.descripcion" class="small text-muted" style="line-height: 1.5; display: inline-block;">{{ cat.descripcion }}</span>
                <span v-else class="small text-muted fst-italic">Sin descripción</span>
              </td>
              
              <td class="text-center">
                <span class="badge rounded-pill" :class="cat.activo ? 'bg-success' : 'bg-danger'">
                  {{ cat.activo ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(cat)">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm me-2" :class="cat.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="cat.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(cat)">
                  <i class="bi" :class="cat.activo ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(cat.id_categoria, cat.nombre)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="categoriasFiltradas.length === 0">
              <td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inboxes fs-3 d-block mb-2"></i>No se encontraron categorías registradas.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="categoriasFiltradas.length > 0">
        <span class="text-muted small">Mostrando {{ categoriasPaginadas.length }} de {{ categoriasFiltradas.length }} registros</span>
        <nav aria-label="Navegación de páginas" v-if="totalPaginas > 1">
          <ul class="pagination pagination-sm mb-0 shadow-sm">
            <li class="page-item" :class="{ disabled: paginaActual === 1 }">
              <button class="page-link text-secondary" @click="cambiarPagina(paginaActual - 1)"><i class="bi bi-chevron-left"></i></button>
            </li>
            <li class="page-item" v-for="pag in totalPaginas" :key="pag" :class="{ active: paginaActual === pag }">
              <button class="page-link" @click="cambiarPagina(pag)">{{ pag }}</button>
            </li>
            <li class="page-item" :class="{ disabled: paginaActual === totalPaginas }">
              <button class="page-link text-secondary" @click="cambiarPagina(paginaActual + 1)"><i class="bi bi-chevron-right"></i></button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- MODAL REGISTRAR / EDITAR -->
    <Teleport to="body">
      <div v-if="modalAbierto" class="modal fade show d-block" style="background-color: rgba(0, 0, 0, 0.55);" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content shadow-sm border-0">
            <div class="modal-header py-3 px-4 text-white bg-primary">
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-tags'"></i> {{ editando ? 'Actualizar Categoría' : 'Nueva Categoría' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            
            <form @submit.prevent="guardarCategoria">
              <div class="modal-body py-4 px-4">
                
                <!-- Aquí sí se muestran todos los departamentos disponibles -->
                <div class="mb-4 p-3 bg-light border rounded">
                  <label class="form-label text-muted small fw-bold">Departamento Padre <span class="text-danger">*</span></label>
                  <v-select 
                    v-model="form.id_departamento" 
                    :options="departamentos" 
                    label="nombre" 
                    :reduce="dept => dept.id_departamento"
                    placeholder="Seleccione un departamento..."
                    @option:selected="validarFormulario"
                    class="bg-white"
                  ></v-select>
                  <div v-if="errores.id_departamento" class="text-danger small mt-1">{{ errores.id_departamento }}</div>
                </div>

                <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">Nombre de la categoría <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errores.nombre, 'is-valid': form.nombre && !errores.nombre }" v-model="form.nombre" @input="validarFormulario" placeholder="Ej. Refrescos de Cola" maxlength="100">
                  <div v-if="errores.nombre" class="invalid-feedback">{{ errores.nombre }}</div>
                </div>

                <div class="mb-2">
                  <label class="form-label text-muted small fw-bold">Descripción</label>
                  <textarea class="form-control" rows="3" v-model="form.descripcion" placeholder="Breve descripción de los productos que incluye..." maxlength="255"></textarea>
                </div>

              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!formularioValido"><i class="bi bi-save me-2"></i>{{ editando ? 'Guardar Cambios' : 'Registrar' }}</button>
              </div>
            </form>
            
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>