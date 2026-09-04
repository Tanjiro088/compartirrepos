<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'

// Estado global
const store = useGlobalStore()
const url = store.baseUrl

// Estados del componente
const marcas = ref([])
const modalAbierto = ref(false)
const editando = ref(false)

// Filtros y Búsqueda
const filtroEstado = ref('todos')
const busqueda = ref('')

// Objeto para el formulario
const form = ref({ id_marca: null, nombre: '', descripcion: '', activo: true })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

// Reiniciar a la página 1 cuando el usuario busque o filtre
watch([filtroEstado, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerMarcas = async () => {
  try {
    const respuesta = await axios.get(url + 'marcas')
    marcas.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) {
    console.error('Error al obtener marcas:', error)
  }
}

const guardarMarca = async () => {
  try {
    if (editando.value) {
      await axios.put(`${url}marcas/${form.value.id_marca}`, form.value)
    } else {
      await axios.post(`${url}marcas`, form.value)
    }
    await obtenerMarcas()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Marca guardada correctamente.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    console.error('Error al guardar:', error)
    Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al guardar la marca.' })
  }
}

const cambiarEstado = async (marca) => {
  const accion = marca.activo ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Marca?`,
    text: `¿Deseas ${accion} la marca ${marca.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: marca.activo ? '#dc3545' : '#198754',
    confirmButtonText: `Sí, ${accion}`
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...marca, activo: !marca.activo }
      await axios.put(`${url}marcas/${marca.id_marca}`, datosActualizados)
      await obtenerMarcas()
      Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false })
    } catch (error) {
      console.error('Error al cambiar estado:', error)
      Swal.fire('Error', 'No se pudo cambiar el estado.', 'error')
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Eliminar Marca?',
    html: `La marca <b>${nombre}</b> será dada de baja.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}marcas/${id}`)
      await obtenerMarcas()
      Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false })
    } catch (err) {
      Swal.fire('Error', 'No se pudo eliminar la marca.', 'error')
    }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => {
  editando.value = false
  form.value = { id_marca: null, nombre: '', descripcion: '', activo: true }
  modalAbierto.value = true
}

const abrirModalEditar = (marca) => {
  editando.value = true
  form.value = { ...marca, activo: marca.activo == 1 }
  modalAbierto.value = true
}

const cerrarModal = () => {
  modalAbierto.value = false
}

// ===============================
// COMPUTEDS (Filtros y Paginación)
// ===============================

const marcasFiltradas = computed(() => {
  let resultado = marcas.value || []

  // 1. Filtro por Estado
  if (filtroEstado.value === 'activas') resultado = resultado.filter(m => m.activo == 1)
  if (filtroEstado.value === 'inactivas') resultado = resultado.filter(m => m.activo == 0)

  // 2. Filtro por Búsqueda de texto
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(m => m.nombre.toLowerCase().includes(termino))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(marcasFiltradas.value.length / registrosPorPagina.value) || 1)

const marcasPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return marcasFiltradas.value.slice(inicio, inicio + registrosPorPagina.value)
})

const cambiarPagina = (pag) => {
  if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag
}

onMounted(() => {
  obtenerMarcas()
})
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Módulo de Marcas</h2>

    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Marcas</h4>
        <button class="btn btn-primary" @click="abrirModalCrear">
          <i class="bi bi-plus-circle me-1"></i> Nueva Marca
        </button>
      </div>

      <!-- PANEL DE FILTROS A 2 COLUMNAS -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        
        <!-- Filtro Estado -->
        <div class="col-md-6 mb-3 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosM" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodosM">Todas</label>

            <input type="radio" class="btn-check" id="estadoActivosM" value="activas" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivosM">Activas</label>

            <input type="radio" class="btn-check" id="estadoInactivosM" value="inactivas" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivosM">Inactivas</label>
          </div>
        </div>

        <!-- Búsqueda -->
        <div class="col-md-6">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar por nombre de marca...">
          </div>
        </div>
      </div>

      <!-- TABLA -->
      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 10%">ID</th>
              <th style="width: 30%">Nombre de la Marca</th>
              <th style="width: 35%">Descripción</th>
              <th style="width: 10%" class="text-center">Estado</th>
              <th style="width: 15%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="marca in marcasPaginadas" :key="marca.id_marca">
              <td><span class="badge bg-dark mb-1">#{{ marca.id_marca }}</span></td>
              <td class="fw-bold text-primary">{{ marca.nombre }}</td>
              <td>
                <span v-if="marca.descripcion" class="small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ marca.descripcion }}</span>
                <span v-else class="small text-muted fst-italic">Sin descripción</span>
              </td>
              <td class="text-center">
                <span class="badge rounded-pill" :class="marca.activo ? 'bg-success' : 'bg-danger'">
                  {{ marca.activo ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(marca)">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm me-2" :class="marca.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="marca.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(marca)">
                  <i class="bi" :class="marca.activo ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(marca.id_marca, marca.nombre)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="marcasFiltradas.length === 0">
              <td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron marcas registradas.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="marcasFiltradas.length > 0">
        <span class="text-muted small">Mostrando {{ marcasPaginadas.length }} de {{ marcasFiltradas.length }} registros</span>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-award'"></i> {{ editando ? 'Actualizar Marca' : 'Nueva Marca' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            
            <form @submit.prevent="guardarMarca">
              <div class="modal-body py-4 px-4">
                
                <div class="mb-3">
                  <label class="form-label text-muted small fw-bold">Nombre de la marca <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.nombre" placeholder="Ej. Coca-Cola" required maxlength="100">
                </div>

                <div class="mb-2">
                  <label class="form-label text-muted small fw-bold">Descripción</label>
                  <textarea class="form-control" rows="3" v-model="form.descripcion" placeholder="Breve descripción de la marca..." maxlength="255"></textarea>
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