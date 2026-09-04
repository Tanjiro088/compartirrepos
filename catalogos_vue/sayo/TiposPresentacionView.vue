<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'

// Estado global
const store = useGlobalStore()
const url = store.baseUrl

// Estados del componente
const tipos = ref([])
const modalAbierto = ref(false)
const editando = ref(false)

// Filtros y Búsqueda
const filtroEstado = ref('todos')
const busqueda = ref('')

// Objeto para el formulario
const form = ref({ id_tipo_presentacion: null, nombre: '', descripcion: '', activo: true })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

// Reiniciar a la página 1 cuando el usuario busque o filtre
watch([filtroEstado, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerTipos = async () => {
  try {
    const respuesta = await axios.get(url + 'tipos-presentacion')
    tipos.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) {
    console.error('Error al obtener tipos de presentación:', error)
  }
}

const guardarTipo = async () => {
  try {
    if (editando.value) {
      await axios.put(`${url}tipos-presentacion/${form.value.id_tipo_presentacion}`, form.value)
    } else {
      await axios.post(`${url}tipos-presentacion`, form.value)
    }
    await obtenerTipos()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Tipo de presentación guardado.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    console.error('Error al guardar:', error)
    Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al guardar.' })
  }
}

const cambiarEstado = async (tipo) => {
  const accion = tipo.activo ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Tipo?`,
    text: `¿Deseas ${accion} el tipo ${tipo.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: tipo.activo ? '#dc3545' : '#198754',
    confirmButtonText: `Sí, ${accion}`
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...tipo, activo: !tipo.activo }
      await axios.put(`${url}tipos-presentacion/${tipo.id_tipo_presentacion}`, datosActualizados)
      await obtenerTipos()
      Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false })
    } catch (error) {
      console.error('Error al cambiar estado:', error)
      Swal.fire('Error', 'No se pudo cambiar el estado.', 'error')
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Eliminar Tipo?',
    html: `El tipo de presentación <b>${nombre}</b> será dado de baja.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}tipos-presentacion/${id}`)
      await obtenerTipos()
      Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false })
    } catch (err) {
      Swal.fire('Error', 'No se pudo eliminar.', 'error')
    }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => {
  editando.value = false
  form.value = { id_tipo_presentacion: null, nombre: '', descripcion: '', activo: true }
  modalAbierto.value = true
}

const abrirModalEditar = (tipo) => {
  editando.value = true
  form.value = { ...tipo, activo: tipo.activo == 1 }
  modalAbierto.value = true
}

const cerrarModal = () => {
  modalAbierto.value = false
}

// ===============================
// COMPUTEDS (Filtros y Paginación)
// ===============================

const tiposFiltrados = computed(() => {
  let resultado = tipos.value || []

  // 1. Filtro por Estado
  if (filtroEstado.value === 'activos') resultado = resultado.filter(t => t.activo == 1)
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(t => t.activo == 0)

  // 2. Filtro por Búsqueda de texto
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(t => t.nombre.toLowerCase().includes(termino))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(tiposFiltrados.value.length / registrosPorPagina.value) || 1)

const tiposPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return tiposFiltrados.value.slice(inicio, inicio + registrosPorPagina.value)
})

const cambiarPagina = (pag) => {
  if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag
}

onMounted(() => {
  obtenerTipos()
})
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Tipos de Presentación</h2>

    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Tipos</h4>
        <button class="btn btn-primary" @click="abrirModalCrear">
          <i class="bi bi-plus-circle me-1"></i> Nuevo Tipo
        </button>
      </div>

      <!-- PANEL DE FILTROS A 2 COLUMNAS -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        
        <!-- Filtro Estado -->
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosT" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodosT">Todos</label>

            <input type="radio" class="btn-check" id="estadoActivosT" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivosT">Activos</label>

            <input type="radio" class="btn-check" id="estadoInactivosT" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivosT">Inactivos</label>
          </div>
        </div>

        <!-- Búsqueda -->
        <div class="col-md-6">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar por nombre...">
          </div>
        </div>
      </div>

      <!-- TABLA -->
      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 10%">ID</th>
              <th style="width: 25%">Nombre del Tipo</th>
              <th style="width: 40%">Descripción</th>
              <th style="width: 10%" class="text-center">Estado</th>
              <th style="width: 15%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tipo in tiposPaginados" :key="tipo.id_tipo_presentacion">
              <td><span class="badge bg-dark mb-1">#{{ tipo.id_tipo_presentacion }}</span></td>
              <td class="fw-bold text-primary">{{ tipo.nombre }}</td>
              <td>
                <span v-if="tipo.descripcion" class="small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ tipo.descripcion }}</span>
                <span v-else class="small text-muted fst-italic">Sin descripción</span>
              </td>
              <td class="text-center">
                <span class="badge rounded-pill" :class="tipo.activo ? 'bg-success' : 'bg-danger'">
                  {{ tipo.activo ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(tipo)">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm me-2" :class="tipo.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="tipo.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(tipo)">
                  <i class="bi" :class="tipo.activo ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(tipo.id_tipo_presentacion, tipo.nombre)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="tiposFiltrados.length === 0">
              <td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron tipos registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="tiposFiltrados.length > 0">
        <span class="text-muted small">Mostrando {{ tiposPaginados.length }} de {{ tiposFiltrados.length }} registros</span>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-tags'"></i> {{ editando ? 'Actualizar Tipo' : 'Nuevo Tipo' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            
            <form @submit.prevent="guardarTipo">
              <div class="modal-body py-4 px-4">
                
                <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">Nombre del tipo <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.nombre" placeholder="Ej. Botella, Lata, Caja" required maxlength="100">
                </div>

                <div class="mb-2">
                  <label class="form-label text-muted small fw-bold">Descripción</label>
                  <textarea class="form-control" rows="3" v-model="form.descripcion" placeholder="Detalles de la presentación..." maxlength="255"></textarea>
                </div>

              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.nombre.trim()"><i class="bi bi-save me-2"></i>{{ editando ? 'Guardar Cambios' : 'Registrar' }}</button>
              </div>
            </form>
            
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>