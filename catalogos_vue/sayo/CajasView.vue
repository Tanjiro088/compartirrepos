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
const cajas = ref([])
const sucursales = ref([]) 
const modalAbierto = ref(false)
const editando = ref(false)

// Filtros y Búsqueda
const filtroEstado = ref('todos') // Filtro de botones Switch
const filtroSucursal = ref('todas')
const busqueda = ref('')

// --- VARIABLES DE PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10) // Aquí definimos los 10 registros por página

// Reiniciar a la página 1 cuando el usuario cambie algún filtro o busque algo
watch([filtroEstado, filtroSucursal, busqueda], () => {
  paginaActual.value = 1
})

// Calcular el total de páginas
const totalPaginas = computed(() => {
  return Math.ceil(cajasFiltradas.value.length / registrosPorPagina.value) || 1
})

// Cortar el arreglo para mostrar solo los de la página actual
const cajasPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  const fin = inicio + registrosPorPagina.value
  return cajasFiltradas.value.slice(inicio, fin)
})

// Función para los botones de cambiar página
const cambiarPagina = (pagina) => {
  if (pagina >= 1 && pagina <= totalPaginas.value) {
    paginaActual.value = pagina
  }
}

// Objeto para el formulario
const form = ref({
  id_caja: null,
  id_sucursal: '',
  nombre: '',
  tipo: 'principal',
  descripcion: '',
  serie_ticket: '',
  numero_ticket_inicial: 1,
  numero_ticket_actual: 1,
  activa: true
})

const opcionesTipoCaja = [
  { id: 'principal', nombre: 'Principal' },
  { id: 'secundaria', nombre: 'Secundaria' },
  { id: 'movil', nombre: 'Móvil' }
]

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerCajas = async () => {
  try {
    const respuesta = await axios.get(url + 'cajas')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    cajas.value = dataLimpios
  } catch (error) {
    console.error('Error al obtener cajas:', error)
  }
}

const obtenerSucursales = async () => {
  try {
    const respuesta = await axios.get(url + 'sucursales')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    // Guardamos solo las sucursales activas
    sucursales.value = dataLimpios.filter(s => s.activa == 1)
  } catch (error) {
    console.error('Error al obtener sucursales:', error)
  }
}

const guardarCaja = async () => {
  try {
    if (editando.value) {
      await axios.put(`${url}cajas/${form.value.id_caja}`, form.value)
    } else {
      await axios.post(`${url}cajas`, form.value)
    }
    await obtenerCajas()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Caja guardada correctamente.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    console.error('Error al guardar:', error)
    Swal.fire({ icon: 'error', title: 'Error', text: 'Ocurrió un error al guardar la caja.' })
  }
}

const cambiarEstado = async (caja) => {
  const accion = caja.activa ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Caja?`,
    text: `¿Deseas ${accion} la ${caja.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: caja.activa ? '#dc3545' : '#198754',
    cancelButtonColor: '#6c757d',
    confirmButtonText: `Sí, ${accion}`
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...caja, activa: !caja.activa }
      await axios.put(`${url}cajas/${caja.id_caja}`, datosActualizados)
      await obtenerCajas()
      Swal.fire({ icon: 'success', title: 'Actualizado', text: 'Estado modificado.', timer: 1500, showConfirmButton: false })
    } catch (error) {
      console.error('Error al cambiar estado:', error)
      Swal.fire('Error', 'No se pudo cambiar el estado.', 'error')
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Confirmar Eliminación?',
    html: `La caja <b>${nombre}</b> será dada de baja del sistema (borrado lógico).`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}cajas/${id}`)
      await obtenerCajas()
      Swal.fire({ icon: 'success', title: 'Eliminada', text: 'La caja ha sido eliminada lógicamente.', timer: 1500, showConfirmButton: false })
    } catch (err) {
      console.error("Error eliminando caja:", err)
      Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar la caja.' })
    }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => {
  editando.value = false
  form.value = { 
    id_caja: null, id_sucursal: '', nombre: '', tipo: 'principal', 
    descripcion: '', serie_ticket: '', numero_ticket_inicial: 1, 
    numero_ticket_actual: 1, activa: true 
  }
  modalAbierto.value = true
}

const abrirModalEditar = (caja) => {
  editando.value = true
  form.value = { ...caja, activa: caja.activa == 1 }
  modalAbierto.value = true
}

const cerrarModal = () => {
  modalAbierto.value = false
}

// ===============================
// COMPUTEDS (Filtros Inteligentes)
// ===============================

// Extrae SOLO las sucursales que están siendo usadas en la tabla
const opcionesFiltroSucursal = computed(() => {
  const idsUsados = [...new Set(cajas.value.map(c => c.id_sucursal))]
  const sucursalesConDatos = sucursales.value.filter(s => idsUsados.includes(s.id_sucursal))
  
  return [{ id_sucursal: 'todas', nombre: 'Todas las sucursales...' }, ...sucursalesConDatos]
})

// Computed property para filtrar la tabla
const cajasFiltradas = computed(() => {
  let resultado = cajas.value

  // Filtro de Estado
  if (filtroEstado.value === 'activos') resultado = resultado.filter(c => c.activa == 1)
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(c => c.activa == 0)

  // Filtro de Sucursal
  if (filtroSucursal.value !== 'todas') {
    resultado = resultado.filter(c => c.id_sucursal == filtroSucursal.value)
  }

  // Búsqueda Rápida
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(c => 
      c.nombre.toLowerCase().includes(termino) || 
      (c.serie_ticket && c.serie_ticket.toLowerCase().includes(termino))
    )
  }

  return resultado
})

// Cargar datos al montar la vista
onMounted(() => {
  obtenerCajas()
  obtenerSucursales()
})
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Puntos de Venta (Cajas)</h2>

    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Cajas Registradas</h4>
        <button class="btn btn-primary" @click="abrirModalCrear">
          <i class="bi bi-plus-circle me-1"></i> Nueva Caja
        </button>
      </div>

      <!-- PANEL DE FILTROS -->
      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-4 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosCaja" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodosCaja">Todas</label>

            <input type="radio" class="btn-check" id="estadoActivosCaja" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivosCaja">Activas</label>

            <input type="radio" class="btn-check" id="estadoInactivosCaja" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivosCaja">Inactivas</label>
          </div>
        </div>
        
        <div class="col-md-4 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold">Filtrar por Sucursal</label>
          <v-select 
            v-model="filtroSucursal" 
            :options="opcionesFiltroSucursal" 
            label="nombre" 
            :reduce="suc => suc.id_sucursal"
            :clearable="false"
            class="bg-white"
          ></v-select>
        </div>

        <div class="col-md-4">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar por nombre o serie...">
          </div>
        </div>
      </div>

      <!-- TABLA REDISTRIBUIDA PARA MAYOR ESPACIO A LA DERECHA -->
      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 6%">ID</th>
              <th style="width: 24%">Sucursal</th>
              <th style="width: 22%">Nombre / Tipo</th>
              <th style="width: 10%" class="text-center">Serie</th>
              <th style="width: 11%" class="text-center">Ticket Actual</th>
              <th style="width: 11%" class="text-center">Estado</th>
              <th style="width: 16%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="caja in cajasPaginadas" :key="caja.id_caja">
              <td><span class="badge bg-dark mb-1">#{{ caja.id_caja }}</span></td>
              <td>
                 <div class="small fw-bold text-dark"><i class="bi bi-shop me-1"></i>{{ caja.sucursal_nombre }}</div>
              </td>
              <td>
                <div class="fw-bold text-primary">{{ caja.nombre }}</div>
                <div class="small text-muted text-capitalize">{{ caja.tipo }}</div>
              </td>
              <td class="text-center">
                <span v-if="caja.serie_ticket" class="badge bg-info text-dark">{{ caja.serie_ticket }}</span>
                <span v-else class="text-muted small">N/A</span>
              </td>
              <td class="text-center fw-bold fs-6">{{ caja.numero_ticket_actual || 0 }}</td>
              <td class="text-center">
                <span class="badge rounded-pill" :class="caja.activa ? 'bg-success' : 'bg-danger'">
                  {{ caja.activa ? 'Activa' : 'Inactiva' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(caja)">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm me-2" :class="caja.activa ? 'btn-outline-danger' : 'btn-outline-success'" :title="caja.activa ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(caja)">
                    <i class="bi" :class="caja.activa ? 'bi-x-circle' : 'bi-check-circle'"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(caja.id_caja, caja.nombre)">
                    <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
            <tr v-if="cajasFiltradas.length === 0">
              <td colspan="7" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron cajas registradas.</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="cajasFiltradas.length > 0">
        <span class="text-muted small">
          Mostrando {{ cajasPaginadas.length }} de {{ cajasFiltradas.length }} registros
        </span>
        
        <nav aria-label="Navegación de páginas" v-if="totalPaginas > 1">
          <ul class="pagination pagination-sm mb-0 shadow-sm">
            <!-- Botón Anterior -->
            <li class="page-item" :class="{ disabled: paginaActual === 1 }">
              <button class="page-link text-secondary" @click="cambiarPagina(paginaActual - 1)">
                <i class="bi bi-chevron-left"></i>
              </button>
            </li>
            
            <!-- Números de Página -->
            <li class="page-item" v-for="pag in totalPaginas" :key="pag" :class="{ active: paginaActual === pag }">
              <button class="page-link" @click="cambiarPagina(pag)">{{ pag }}</button>
            </li>
            
            <!-- Botón Siguiente -->
            <li class="page-item" :class="{ disabled: paginaActual === totalPaginas }">
              <button class="page-link text-secondary" @click="cambiarPagina(paginaActual + 1)">
                <i class="bi bi-chevron-right"></i>
              </button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- MODAL REGISTRAR / EDITAR -->
    <Teleport to="body">
      <div v-if="modalAbierto" class="modal fade show d-block" style="background-color: rgba(0, 0, 0, 0.55);" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content shadow-sm border-0">
            <div class="modal-header py-3 px-4 text-white bg-primary">
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-pc-display'"></i> {{ editando ? 'Editar Caja Registradora' : 'Registrar Nueva Caja' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarCaja">
              <div class="modal-body py-4 px-4">
                
                <div class="row g-3 mb-4">
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Sucursal Asignada <span class="text-danger">*</span></label>
                    <v-select 
                      v-model="form.id_sucursal" 
                      :options="sucursales" 
                      label="nombre" 
                      :reduce="suc => suc.id_sucursal"
                      placeholder="Seleccione..."
                      class="bg-white"
                    ></v-select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Tipo de Caja <span class="text-danger">*</span></label>
                    <v-select 
                        v-model="form.tipo" 
                        :options="opcionesTipoCaja" 
                        label="nombre" 
                        :reduce="tipo => tipo.id"
                        :clearable="false"
                        class="bg-white"
                    ></v-select>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">Nombre de la Caja <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.nombre" placeholder="Ej. Caja Principal" required maxlength="100">
                </div>

                <h6 class="text-primary mb-3 border-bottom pb-2 fw-bold">Configuración de Tickets</h6>
                <div class="row g-3 mb-2">
                  <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Serie del Ticket</label>
                    <input type="text" class="form-control text-uppercase" v-model="form.serie_ticket" placeholder="Ej. A" maxlength="10">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold">Ticket Inicial <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" v-model="form.numero_ticket_inicial" min="1" required :disabled="form.id_caja !== null">
                    <div v-if="!form.id_caja" class="form-text small mt-1">Con el que iniciará operaciones.</div>
                  </div>
                  <!-- Solo mostramos el Ticket Actual si estamos editando -->
                  <div class="col-md-4" v-if="form.id_caja">
                    <label class="form-label text-muted small fw-bold">Ticket Actual (Ajuste)</label>
                    <input type="number" class="form-control text-primary fw-bold" v-model="form.numero_ticket_actual" min="1">
                  </div>
                </div>

              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.id_sucursal || !form.tipo || !form.nombre"><i class="bi bi-save me-2"></i>{{ editando ? 'Guardar Cambios' : 'Registrar Caja' }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>