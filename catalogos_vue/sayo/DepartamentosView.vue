<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'

// Estado global
const store = useGlobalStore()
const url = store.baseUrl

// Estados del componente
const departamentos = ref([])
const modalAbierto = ref(false)
const editando = ref(false)

// Filtros y Búsqueda
const filtroEstado = ref('todos')
const busqueda = ref('')

// Objeto para el formulario y validación
const form = ref({ id_departamento: null, nombre: '', descripcion: '', activo: true })
const errores = ref({ nombre: '' })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

// Reiniciar a la página 1 cuando el usuario busque o filtre
watch([filtroEstado, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerDepartamentos = async () => {
  try {
    const respuesta = await axios.get(url + 'departamentos')
    departamentos.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) {
    console.error('Error al obtener datos:', error)
  }
}

const guardarDepartamento = async () => {
  if (!formularioValido.value) return
  
  try {
    if (editando.value) {
      await axios.put(`${url}departamentos/${form.value.id_departamento}`, form.value)
    } else {
      await axios.post(`${url}departamentos`, form.value)
    }
    await obtenerDepartamentos()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Departamento guardado correctamente.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    console.error('Error al guardar:', error)
    Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al guardar el departamento.' })
  }
}

const cambiarEstado = async (dept) => {
  const accion = dept.activo ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Departamento?`,
    text: `¿Deseas ${accion} el departamento ${dept.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: dept.activo ? '#dc3545' : '#198754',
    confirmButtonText: `Sí, ${accion}`
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...dept, activo: !dept.activo }
      await axios.put(`${url}departamentos/${dept.id_departamento}`, datosActualizados)
      await obtenerDepartamentos()
      Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false })
    } catch (error) {
      console.error('Error al cambiar estado:', error)
      Swal.fire('Error', 'No se pudo cambiar el estado.', 'error')
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Eliminar Departamento?',
    html: `El departamento <b>${nombre}</b> será dado de baja del sistema.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}departamentos/${id}`)
      await obtenerDepartamentos()
      Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false })
    } catch (err) {
      Swal.fire('Error', 'No se pudo eliminar el departamento.', 'error')
    }
  }
}

// ===============================
// CONTROLADORES Y VALIDACIÓN
// ===============================

const validarNombreInput = () => {
  const nombreLimpio = form.value.nombre.trim()
  if (!nombreLimpio) errores.value.nombre = 'El nombre del departamento es obligatorio.'
  else if (nombreLimpio.length > 100) errores.value.nombre = 'El nombre no puede exceder los 100 caracteres.'
  else if (/^\d+$/.test(nombreLimpio)) errores.value.nombre = 'No puede estar compuesto únicamente por números.'
  else errores.value.nombre = ''
}

const formularioValido = computed(() => {
  return form.value.nombre.trim().length > 0 && !errores.value.nombre
})

const abrirModalCrear = () => {
  editando.value = false
  form.value = { id_departamento: null, nombre: '', descripcion: '', activo: true }
  errores.value.nombre = ''
  modalAbierto.value = true
}

const abrirModalEditar = (dept) => {
  editando.value = true
  form.value = { ...dept, activo: dept.activo == 1 }
  errores.value.nombre = ''
  modalAbierto.value = true
}

const cerrarModal = () => {
  modalAbierto.value = false
}

// ===============================
// COMPUTEDS (Filtros y Paginación)
// ===============================

const departamentosFiltrados = computed(() => {
  let resultado = departamentos.value

  if (filtroEstado.value === 'activos') resultado = resultado.filter(d => d.activo == 1)
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(d => d.activo == 0)

  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(d => d.nombre.toLowerCase().includes(termino))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(departamentosFiltrados.value.length / registrosPorPagina.value) || 1)

const departamentosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return departamentosFiltrados.value.slice(inicio, inicio + registrosPorPagina.value)
})

const cambiarPagina = (pag) => {
  if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag
}

onMounted(() => {
  obtenerDepartamentos()
})
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Módulo de Departamentos</h2>

    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Departamentos</h4>
        <button class="btn btn-primary" @click="abrirModalCrear">
          <i class="bi bi-plus-circle me-1"></i> Nuevo Departamento
        </button>
      </div>

      <!-- PANEL DE FILTROS -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-4 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodos" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodos">Todos</label>

            <input type="radio" class="btn-check" id="estadoActivos" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivos">Activos</label>

            <input type="radio" class="btn-check" id="estadoInactivos" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivos">Inactivos</label>
          </div>
        </div>
        
        <div class="col-md-8">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <input type="text" class="form-control form-control-sm" v-model="busqueda" placeholder="Buscar por nombre...">
        </div>
      </div>

      <!-- TABLA -->
      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 8%">ID</th>
              <th style="width: 30%">Nombre del Departamento</th>
              <th style="width: 35%">Descripción</th>
              <th style="width: 12%" class="text-center">Estado</th>
              <th style="width: 15%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="dept in departamentosPaginados" :key="dept.id_departamento">
              <td><span class="badge bg-dark mb-1">#{{ dept.id_departamento }}</span></td>
              <td class="fw-bold text-primary">{{ dept.nombre }}</td>
              <td>
                <span v-if="dept.descripcion" class="small text-muted">{{ dept.descripcion }}</span>
                <span v-else class="small text-muted fst-italic">Sin descripción</span>
              </td>
              <td class="text-center">
                <span class="badge rounded-pill" :class="dept.activo ? 'bg-success' : 'bg-danger'">
                  {{ dept.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(dept)">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm me-2" :class="dept.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="dept.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(dept)">
                  <i class="bi" :class="dept.activo ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(dept.id_departamento, dept.nombre)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="departamentosFiltrados.length === 0">
              <td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron departamentos.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="departamentosFiltrados.length > 0">
        <span class="text-muted small">Mostrando {{ departamentosPaginados.length }} de {{ departamentosFiltrados.length }} registros</span>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-diagram-3'"></i> {{ editando ? 'Actualizar Departamento' : 'Nuevo Departamento' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            
            <form @submit.prevent="guardarDepartamento">
              <div class="modal-body py-4 px-4">
                
                <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">Nombre del departamento <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errores.nombre, 'is-valid': form.nombre && !errores.nombre }" v-model="form.nombre" @input="validarNombreInput" placeholder="Ej. Ferretería" maxlength="100">
                  <div v-if="errores.nombre" class="invalid-feedback">{{ errores.nombre }}</div>
                </div>

                <div class="mb-2">
                  <label class="form-label text-muted small fw-bold">Descripción</label>
                  <textarea class="form-control" rows="3" v-model="form.descripcion" placeholder="Breve descripción del área..." maxlength="255"></textarea>
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