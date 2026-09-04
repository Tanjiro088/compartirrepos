<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'

// Estado global
const store = useGlobalStore()
const url = store.baseUrl

// Estados del componente
const listas = ref([])
const modalAbierto = ref(false)
const editando = ref(false)

// Filtros y Búsqueda
const filtroEstado = ref('todas')
const busqueda = ref('')

// Objeto para el formulario
const form = ref({
  id_lista_precio: null,
  nombre: '',
  tipo: 'venta',
  descripcion: '',
  aplica_descuento: false,
  descuento_global: 0.00,
  activo: true
})

// Observador para limpiar el descuento si se apaga el switch
watch(() => form.value.aplica_descuento, (nuevoValor) => {
  if (!nuevoValor) {
    form.value.descuento_global = 0.00
  }
})

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

watch([filtroEstado, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES Y SWEETALERT
// ===============================

const obtenerListas = async () => {
  try {
    const respuesta = await axios.get(url + 'listas-precios')
    // Ajuste de seguridad por si tu API devuelve data anidada
    listas.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) {
    console.error('Error al obtener listas:', error)
  }
}

const guardarLista = async () => {
  try {
    if (editando.value) {
      await axios.put(`${url}listas-precios/${form.value.id_lista_precio}`, form.value)
    } else {
      await axios.post(`${url}listas-precios`, form.value)
    }
    await obtenerListas()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Lista guardada correctamente.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    console.error('Error al guardar:', error)
    Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al guardar la lista de precios.' })
  }
}

const cambiarEstado = async (lista) => {
  const accion = lista.activo ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Lista?`,
    text: `¿Deseas ${accion} la lista ${lista.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: lista.activo ? '#dc3545' : '#198754',
    cancelButtonColor: '#6c757d',
    confirmButtonText: `Sí, ${accion}`
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...lista, activo: !lista.activo }
      await axios.put(`${url}listas-precios/${lista.id_lista_precio}`, datosActualizados)
      await obtenerListas()
      Swal.fire({ icon: 'success', title: 'Actualizado', text: 'Estado modificado.', timer: 1500, showConfirmButton: false })
    } catch (error) {
      Swal.fire('Error', 'No se pudo cambiar el estado.', 'error')
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Confirmar Eliminación?',
    html: `La lista <b>${nombre}</b> será dada de baja.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}listas-precios/${id}`)
      await obtenerListas()
      Swal.fire({ icon: 'success', title: 'Eliminada', text: 'Lista eliminada.', timer: 1500, showConfirmButton: false })
    } catch (err) {
      Swal.fire('Error', 'No se pudo eliminar la lista.', 'error')
    }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => {
  editando.value = false
  form.value = { 
    id_lista_precio: null, nombre: '', tipo: 'venta', 
    descripcion: '', aplica_descuento: false, descuento_global: 0.00, activo: true 
  }
  modalAbierto.value = true
}

const abrirModalEditar = (lista) => {
  editando.value = true
  form.value = { 
    ...lista, 
    activo: lista.activo == 1,
    aplica_descuento: lista.aplica_descuento == 1
  }
  modalAbierto.value = true
}

const cerrarModal = () => { modalAbierto.value = false }

// Computed property para filtrar la tabla
const listasFiltradas = computed(() => {
  let resultado = listas.value

  if (filtroEstado.value === 'activas') resultado = resultado.filter(l => l.activo == 1)
  if (filtroEstado.value === 'inactivas') resultado = resultado.filter(l => l.activo == 0)

  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(l => l.nombre.toLowerCase().includes(termino) || (l.tipo && l.tipo.toLowerCase().includes(termino)))
  }

  return resultado
})

const totalPaginas = computed(() => Math.ceil(listasFiltradas.value.length / registrosPorPagina.value) || 1)

const listasPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return listasFiltradas.value.slice(inicio, inicio + registrosPorPagina.value)
})

const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag }

// Cargar datos al montar la vista
onMounted(() => { obtenerListas() })
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Listas de Precios</h2>

    <!-- VISTA DE TABLA -->
    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Listas</h4>
        <button class="btn btn-primary" @click="abrirModalCrear">
          <i class="bi bi-plus-circle me-1"></i> Nueva Lista
        </button>
      </div>

      <!-- PANEL DE FILTROS ESTANDARIZADO -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-4 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodasL" value="todas" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodasL">Todas</label>

            <input type="radio" class="btn-check" id="estadoActivasL" value="activas" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivasL">Activas</label>

            <input type="radio" class="btn-check" id="estadoInactivasL" value="inactivas" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivasL">Inactivas</label>
          </div>
        </div>
        <div class="col-md-8">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <input type="text" class="form-control form-control-sm" v-model="busqueda" placeholder="Buscar por nombre o tipo...">
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 10%">ID</th>
              <th style="width: 25%">Nombre de la Lista</th>
              <th style="width: 15%">Tipo</th>
              <th style="width: 15%" class="text-center">Descuento Global</th>
              <th style="width: 10%" class="text-center">Estado</th>
              <th style="width: 25%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="lista in listasPaginadas" :key="lista.id_lista_precio">
              <td><span class="badge bg-dark mb-1">#{{ lista.id_lista_precio }}</span></td>
              <td><div class="fw-bold text-dark">{{ lista.nombre }}</div></td>
              <td><span class="text-capitalize text-secondary fw-semibold">{{ lista.tipo }}</span></td>
              <td class="text-center">
                <span v-if="lista.aplica_descuento" class="badge bg-warning text-dark border border-warning px-3 py-2">
                  <i class="bi bi-tag-fill me-1"></i> {{ lista.descuento_global }}%
                </span>
                <span v-else class="text-muted small">No aplica</span>
              </td>
              <td class="text-center">
                <span class="badge rounded-pill" :class="lista.activo ? 'bg-success' : 'bg-danger'">
                  {{ lista.activo ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(lista)">
                  <i class="bi bi-pencil"></i>
                </button>
                <!-- Botón de estado corregido: solo icono y tooltip -->
                <button class="btn btn-sm me-2" :class="lista.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="lista.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(lista)">
                  <i class="bi" :class="lista.activo ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(lista.id_lista_precio, lista.nombre)">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="listasFiltradas.length === 0">
              <td colspan="6" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron listas registradas.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="listasFiltradas.length > 0">
        <span class="text-muted small">Mostrando {{ listasPaginadas.length }} de {{ listasFiltradas.length }} registros</span>
        <nav aria-label="Navegación de páginas" v-if="totalPaginas > 1">
          <ul class="pagination pagination-sm mb-0 shadow-sm">
            <li class="page-item" :class="{ disabled: paginaActual === 1 }"><button class="page-link text-secondary" @click="cambiarPagina(paginaActual - 1)"><i class="bi bi-chevron-left"></i></button></li>
            <li class="page-item" v-for="pag in totalPaginas" :key="pag" :class="{ active: paginaActual === pag }"><button class="page-link" @click="cambiarPagina(pag)">{{ pag }}</button></li>
            <li class="page-item" :class="{ disabled: paginaActual === totalPaginas }"><button class="page-link text-secondary" @click="cambiarPagina(paginaActual + 1)"><i class="bi bi-chevron-right"></i></button></li>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-tags'"></i> {{ editando ? 'Editar Lista de Precios' : 'Registrar Nueva Lista' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarLista">
              <div class="modal-body py-4 px-4">
                
                <div class="row g-3 mb-3">
                  <div class="col-md-8">
                    <label class="form-label text-muted small fw-bold">Nombre de la lista <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" v-model="form.nombre" placeholder="Ej. Público General" required maxlength="100">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Tipo <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="form.tipo">
                      <option value="venta">Venta</option>
                      <option value="mayoreo">Mayoreo</option>
                      <option value="especial">Especial</option>
                    </select>
                  </div>
                </div>
                
                <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">Descripción</label>
                  <textarea class="form-control" rows="2" v-model="form.descripcion" placeholder="Condiciones de esta lista..." maxlength="255"></textarea>
                </div>

                <div class="row g-3 mb-2 p-3 bg-light rounded border align-items-center">
                  <div class="col-md-7">
                    <div class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" id="checkDescuento" v-model="form.aplica_descuento">
                      <label class="form-check-label fw-bold small text-muted" for="checkDescuento">¿Aplica descuento global?</label>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <label class="form-label text-muted small mb-1 fw-bold">Porcentaje (%)</label>
                    <input type="number" class="form-control form-control-sm text-end fw-bold" v-model="form.descuento_global" placeholder="0.00" :disabled="!form.aplica_descuento" step="0.01" min="0" max="100">
                  </div>
                </div>

              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.nombre"><i class="bi bi-save me-2"></i>{{ editando ? 'Guardar Cambios' : 'Registrar Lista' }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>