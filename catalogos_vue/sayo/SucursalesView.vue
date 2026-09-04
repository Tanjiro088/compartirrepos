<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const store = useGlobalStore()
const url = store.baseUrl

const sucursales = ref([])
const empresas = ref([]) 
const modalAbierto = ref(false)
const editando = ref(false)

const filtroEstado = ref('todos')
const filtroEmpresa = ref('todas')
const busqueda = ref('')

const form = ref({
  id_sucursal: null, id_empresa: '', nombre: '', clave: '', responsable: '',
  telefono: '', correo: '', calle: '', numero_exterior: '', numero_interior: '',
  colonia: '', codigo_postal: '', ciudad: '', estado: '', pais: 'México', activa: true
})

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

watch([filtroEstado, filtroEmpresa, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES Y SWEETALERT
// ===============================

const obtenerSucursales = async () => {
  try {
    const respuesta = await axios.get(url + 'sucursales')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    sucursales.value = dataLimpios
  } catch (error) { console.error('Error:', error) }
}
const obtenerEmpresas = async () => {
  try {
    const respuesta = await axios.get(url + 'empresas')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    empresas.value = dataLimpios.filter(e => e.activa == 1)
  } catch (error) { console.error('Error:', error) }
}
const guardarSucursal = async () => {
  try {
    if (editando.value) {
      await axios.put(`${url}sucursales/${form.value.id_sucursal}`, form.value)
    } else {
      await axios.post(`${url}sucursales`, form.value)
    }
    cerrarModal()
    await obtenerSucursales()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'Sucursal guardada.', timer: 2000, showConfirmButton: false })
  } catch (error) {
    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar la sucursal.' })
  }
}
const cambiarEstado = async (sucursal) => {
  const accion = sucursal.activa ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} Sucursal?`,
    text: `¿Deseas ${accion} la sucursal ${sucursal.nombre}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: sucursal.activa ? '#dc3545' : '#198754',
    confirmButtonText: `Sí, ${accion}`
  })
  if (result.isConfirmed) {
    try {
      await axios.put(`${url}sucursales/${sucursal.id_sucursal}`, { ...sucursal, activa: !sucursal.activa })
      await obtenerSucursales()
      Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false })
    } catch (error) { Swal.fire('Error', 'No se pudo cambiar el estado.', 'error') }
  }
}
const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Eliminar?',
    html: `La sucursal <b>${nombre}</b> será dada de baja.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar'
  })
  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}sucursales/${id}`)
      await obtenerSucursales()
      Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false })
    } catch (err) { Swal.fire('Error', 'No se pudo eliminar.', 'error') }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => {
  editando.value = false
  form.value = { id_sucursal: null, id_empresa: '', nombre: '', clave: '', responsable: '', telefono: '', correo: '', calle: '', numero_exterior: '', numero_interior: '', colonia: '', codigo_postal: '', ciudad: '', estado: '', pais: 'México', activa: true }
  modalAbierto.value = true
}
const abrirModalEditar = (sucursal) => {
  editando.value = true
  form.value = { ...sucursal, activa: sucursal.activa == 1 }
  modalAbierto.value = true
}
const cerrarModal = () => { modalAbierto.value = false }

// ===============================
// COMPUTEDS (Filtros y Paginación)
// ===============================

// MAGIA DE UX: Este filtro extrae SOLO las empresas que están siendo usadas por sucursales en la tabla
const opcionesFiltroEmpresa = computed(() => {
  const idsUsados = [...new Set(sucursales.value.map(s => s.id_empresa))]
  const empresasConDatos = empresas.value.filter(e => idsUsados.includes(e.id_empresa))
  
  return [{ id_empresa: 'todas', razon_social: 'Todas las empresas...' }, ...empresasConDatos]
})

const sucursalesFiltradas = computed(() => {
  let resultado = sucursales.value
  
  if (filtroEstado.value === 'activos') resultado = resultado.filter(s => s.activa == 1)
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(s => s.activa == 0)
  
  if (filtroEmpresa.value !== 'todas') resultado = resultado.filter(s => s.id_empresa == filtroEmpresa.value)
  
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(s => s.nombre.toLowerCase().includes(termino) || (s.clave && s.clave.toLowerCase().includes(termino)) || (s.responsable && s.responsable.toLowerCase().includes(termino)))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(sucursalesFiltradas.value.length / registrosPorPagina.value) || 1)

const sucursalesPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  const fin = inicio + registrosPorPagina.value
  return sucursalesFiltradas.value.slice(inicio, fin)
})

const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag }

onMounted(() => { obtenerSucursales(); obtenerEmpresas() })
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Gestión de Sucursales</h2>
    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Sucursales</h4>
        <button class="btn btn-primary" @click="abrirModalCrear"><i class="bi bi-plus-circle me-1"></i> Nueva Sucursal</button>
      </div>

      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-4 mb-3 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosSuc" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodosSuc">Todos</label>
            <input type="radio" class="btn-check" id="estadoActivosSuc" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivosSuc">Activas</label>
            <input type="radio" class="btn-check" id="estadoInactivosSuc" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivosSuc">Inactivas</label>
          </div>
        </div>
        
        <div class="col-md-4 mb-3 mb-md-0">
          <label class="form-label text-muted small fw-bold">Filtrar por Empresa</label>
          <v-select 
            v-model="filtroEmpresa" 
            :options="opcionesFiltroEmpresa" 
            label="razon_social" 
            :reduce="emp => emp.id_empresa" 
            :clearable="false"
            class="bg-white"
          ></v-select>
        </div>
        
        <div class="col-md-4">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar...">
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 15%">Clave / Empresa</th>
              <th style="width: 25%">Sucursal y Responsable</th>
              <th style="width: 25%">Contacto y Ubicación</th>
              <th style="width: 10%" class="text-center">Estado</th>
              <th style="width: 25%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sucursal in sucursalesPaginadas" :key="sucursal.id_sucursal">
              <td><span class="badge bg-dark mb-1">{{ sucursal.clave || 'S/C' }}</span><div class="small fw-semibold text-secondary">{{ sucursal.empresa_nombre }}</div></td>
              <td><div class="fw-bold text-dark">{{ sucursal.nombre }}</div><div class="small text-muted"><i class="bi bi-person me-1"></i>{{ sucursal.responsable || 'Sin asignar' }}</div></td>
              <td>
                <div class="text-truncate small text-muted mb-1"><i class="bi bi-geo-alt me-1"></i>{{ sucursal.ciudad || 'Ciudad S/D' }}, {{ sucursal.estado || 'Estado S/D' }}</div>
                <div class="text-truncate small text-muted" v-if="sucursal.telefono || sucursal.correo"><span v-if="sucursal.telefono"><i class="bi bi-telephone me-1"></i>{{ sucursal.telefono }} &nbsp;</span><span v-if="sucursal.correo"><i class="bi bi-envelope me-1"></i>{{ sucursal.correo }}</span></div>
              </td>
              <td class="text-center"><span class="badge rounded-pill" :class="sucursal.activa ? 'bg-success' : 'bg-danger'">{{ sucursal.activa ? 'Activa' : 'Inactiva' }}</span></td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(sucursal)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm me-2" :class="sucursal.activa ? 'btn-outline-danger' : 'btn-outline-success'" :title="sucursal.activa ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(sucursal)"><i class="bi" :class="sucursal.activa ? 'bi-x-circle' : 'bi-check-circle'"></i></button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(sucursal.id_sucursal, sucursal.nombre)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="sucursalesFiltradas.length === 0"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron sucursales.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="sucursalesFiltradas.length > 0">
        <span class="text-muted small">Mostrando {{ sucursalesPaginadas.length }} de {{ sucursalesFiltradas.length }} registros</span>
        <nav aria-label="Navegación de páginas" v-if="totalPaginas > 1">
          <ul class="pagination pagination-sm mb-0 shadow-sm">
            <li class="page-item" :class="{ disabled: paginaActual === 1 }"><button class="page-link text-secondary" @click="cambiarPagina(paginaActual - 1)"><i class="bi bi-chevron-left"></i></button></li>
            <li class="page-item" v-for="pag in totalPaginas" :key="pag" :class="{ active: paginaActual === pag }"><button class="page-link" @click="cambiarPagina(pag)">{{ pag }}</button></li>
            <li class="page-item" :class="{ disabled: paginaActual === totalPaginas }"><button class="page-link text-secondary" @click="cambiarPagina(paginaActual + 1)"><i class="bi bi-chevron-right"></i></button></li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- MODAL -->
    <Teleport to="body">
      <div v-if="modalAbierto" class="modal fade show d-block" style="background-color: rgba(0, 0, 0, 0.55);" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content shadow-sm border-0">
            <div class="modal-header py-3 px-4 text-white bg-primary">
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-shop'"></i> {{ editando ? 'Actualizar' : 'Nueva Sucursal' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarSucursal" novalidate>
              <div class="modal-body py-4 px-4" style="max-height: 75vh; overflow-y: auto;">
                <h6 class="text-primary mb-3 border-bottom pb-2 fw-bold">1. Información General</h6>
                <div class="row g-3 mb-4">
                  <!-- Muestra TODAS las empresas activas en el formulario -->
                  <div class="col-md-12">
                    <label class="form-label text-muted small fw-bold">Empresa Matriz <span class="text-danger">*</span></label>
                    <v-select v-model="form.id_empresa" :options="empresas" label="razon_social" :reduce="emp => emp.id_empresa" class="bg-white"></v-select>
                  </div>
                  <div class="col-md-8"><label class="form-label text-muted small fw-bold">Nombre <span class="text-danger">*</span></label><input type="text" class="form-control" v-model="form.nombre" required></div>
                  <div class="col-md-4"><label class="form-label text-muted small fw-bold">Clave Única</label><input type="text" class="form-control text-uppercase" v-model="form.clave"></div>
                  <div class="col-md-12"><label class="form-label text-muted small fw-bold">Responsable</label><input type="text" class="form-control" v-model="form.responsable"></div>
                </div>
                <h6 class="text-primary mb-3 border-bottom pb-2 fw-bold">2. Contacto y Ubicación</h6>
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Teléfono</label><input type="text" class="form-control" v-model="form.telefono"></div>
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Correo</label><input type="email" class="form-control" v-model="form.correo"></div>
                  <div class="col-md-8"><label class="form-label text-muted small fw-bold">Calle</label><input type="text" class="form-control" v-model="form.calle"></div>
                  <div class="col-md-2"><label class="form-label text-muted small fw-bold">N° Ext.</label><input type="text" class="form-control" v-model="form.numero_exterior"></div>
                  <div class="col-md-2"><label class="form-label text-muted small fw-bold">N° Int.</label><input type="text" class="form-control" v-model="form.numero_interior"></div>
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Colonia</label><input type="text" class="form-control" v-model="form.colonia"></div>
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Código Postal</label><input type="text" class="form-control" v-model="form.codigo_postal"></div>
                  <div class="col-md-4"><label class="form-label text-muted small fw-bold">Ciudad</label><input type="text" class="form-control" v-model="form.ciudad"></div>
                  <div class="col-md-4"><label class="form-label text-muted small fw-bold">Estado</label><input type="text" class="form-control" v-model="form.estado"></div>
                  <div class="col-md-4"><label class="form-label text-muted small fw-bold">País</label><input type="text" class="form-control" v-model="form.pais"></div>
                </div>
              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.id_empresa || !form.nombre"><i class="bi bi-save me-2"></i>Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>