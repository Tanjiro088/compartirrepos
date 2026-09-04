<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const store = useGlobalStore()
const url = store.baseUrl

const almacenes = ref([])
const sucursales = ref([]) 
const empresas = ref([]) 
const modalAbierto = ref(false)
const editando = ref(false)

const filtroEstado = ref('todos')
const filtroEmpresa = ref('todas')
const filtroSucursal = ref('todas')
const busqueda = ref('')
const empresaForm = ref('')

const form = ref({ id_almacen: null, id_sucursal: '', nombre: '', descripcion: '', responsable: '', activo: true })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

watch([filtroEstado, filtroEmpresa, filtroSucursal, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES Y SWEETALERT
// ===============================

const obtenerAlmacenes = async () => {
  try {
    const respuesta = await axios.get(url + 'almacenes')
    almacenes.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) { console.error('Error:', error) }
}
const obtenerSucursales = async () => {
  try {
    const respuesta = await axios.get(url + 'sucursales')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    sucursales.value = dataLimpios.filter(s => s.activa == 1)
  } catch (error) { console.error('Error:', error) }
}
const obtenerEmpresas = async () => {
  try {
    const respuesta = await axios.get(url + 'empresas')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    empresas.value = dataLimpios.filter(e => e.activa == 1)
  } catch (error) { console.error('Error:', error) }
}
const guardarAlmacen = async () => {
  try {
    if (editando.value) await axios.put(`${url}almacenes/${form.value.id_almacen}`, form.value)
    else await axios.post(`${url}almacenes`, form.value)
    cerrarModal()
    await obtenerAlmacenes()
    Swal.fire({ icon: 'success', title: '¡Éxito!', timer: 2000, showConfirmButton: false })
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar.' }) }
}
const cambiarEstado = async (almacen) => {
  const accion = almacen.activo ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({ title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)}?`, text: `¿Deseas ${accion} el ${almacen.nombre}?`, icon: 'warning', showCancelButton: true, confirmButtonColor: almacen.activo ? '#dc3545' : '#198754' })
  if (result.isConfirmed) {
    try {
      await axios.put(`${url}almacenes/${almacen.id_almacen}`, { ...almacen, activo: !almacen.activo })
      await obtenerAlmacenes()
      Swal.fire({ icon: 'success', title: 'Actualizado', timer: 1500, showConfirmButton: false })
    } catch (error) { Swal.fire('Error', 'No se pudo cambiar el estado.', 'error') }
  }
}
const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({ title: '¿Eliminar?', html: `El almacén <b>${nombre}</b> será dado de baja.`, icon: 'error', showCancelButton: true, confirmButtonColor: '#dc3545' })
  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}almacenes/${id}`)
      await obtenerAlmacenes()
      Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false })
    } catch (err) { Swal.fire('Error', 'No se pudo eliminar.', 'error') }
  }
}

// ===============================
// CONTROLADORES Y COMPUTED
// ===============================

const abrirModalCrear = () => { editando.value = false; empresaForm.value = ''; form.value = { id_almacen: null, id_sucursal: '', nombre: '', descripcion: '', responsable: '', activo: true }; modalAbierto.value = true }
const abrirModalEditar = (almacen) => { editando.value = true; empresaForm.value = almacen.id_empresa; form.value = { ...almacen, activo: almacen.activo == 1 }; modalAbierto.value = true }
const cerrarModal = () => { modalAbierto.value = false }

// Opciones dinámicas para el formulario (cascada)
const sucursalesDropdownForm = computed(() => { return !empresaForm.value ? [] : sucursales.value.filter(s => s.id_empresa === empresaForm.value) })

// MAGIA DE UX: Filtros inteligentes para la tabla
const opcionesFiltroEmpresa = computed(() => {
  const idsEmpresasUsados = [...new Set(almacenes.value.map(a => a.id_empresa))]
  const empresasConDatos = empresas.value.filter(e => idsEmpresasUsados.includes(e.id_empresa))
  return [{ id_empresa: 'todas', razon_social: 'Todas...' }, ...empresasConDatos]
})

const opcionesFiltroSucursal = computed(() => {
  let sucursalesDisponibles = almacenes.value.map(a => ({ id_sucursal: a.id_sucursal, nombre: a.sucursal_nombre, id_empresa: a.id_empresa }))
  // Eliminar duplicados
  sucursalesDisponibles = sucursalesDisponibles.filter((v, i, a) => a.findIndex(t => (t.id_sucursal === v.id_sucursal)) === i)
  
  if (filtroEmpresa.value !== 'todas') {
    sucursalesDisponibles = sucursalesDisponibles.filter(s => s.id_empresa === filtroEmpresa.value)
  }
  return [{ id_sucursal: 'todas', nombre: 'Todas...' }, ...sucursalesDisponibles]
})

const almacenesFiltrados = computed(() => {
  let resultado = almacenes.value
  if (filtroEstado.value === 'activos') resultado = resultado.filter(a => a.activo == 1)
  if (filtroEstado.value === 'inactivos') resultado = resultado.filter(a => a.activo == 0)
  if (filtroEmpresa.value !== 'todas') resultado = resultado.filter(a => a.id_empresa === filtroEmpresa.value)
  if (filtroSucursal.value !== 'todas') resultado = resultado.filter(a => a.id_sucursal === filtroSucursal.value)
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(a => a.nombre.toLowerCase().includes(termino) || (a.responsable && a.responsable.toLowerCase().includes(termino)))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(almacenesFiltrados.value.length / registrosPorPagina.value) || 1)
const almacenesPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return almacenesFiltrados.value.slice(inicio, inicio + registrosPorPagina.value)
})
const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag }

onMounted(() => { obtenerEmpresas(); obtenerSucursales(); obtenerAlmacenes() })
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Gestión de Almacenes</h2>
    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo de Almacenes</h4>
        <button class="btn btn-primary" @click="abrirModalCrear"><i class="bi bi-plus-circle me-1"></i> Nuevo Almacén</button>
      </div>

      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosAlm" value="todos" v-model="filtroEstado"><label class="btn btn-outline-secondary btn-sm" for="estadoTodosAlm">Todos</label>
            <input type="radio" class="btn-check" id="estadoActivosAlm" value="activos" v-model="filtroEstado"><label class="btn btn-outline-success btn-sm" for="estadoActivosAlm">Activos</label>
            <input type="radio" class="btn-check" id="estadoInactivosAlm" value="inactivos" v-model="filtroEstado"><label class="btn btn-outline-danger btn-sm" for="estadoInactivosAlm">Inactivos</label>
          </div>
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold">Filtrar por Empresa</label>
          <v-select v-model="filtroEmpresa" :options="opcionesFiltroEmpresa" label="razon_social" :reduce="emp => emp.id_empresa" :clearable="false" @update:modelValue="filtroSucursal = 'todas'" class="bg-white"></v-select>
        </div>
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold">Filtrar por Sucursal</label>
          <v-select v-model="filtroSucursal" :options="opcionesFiltroSucursal" label="nombre" :reduce="suc => suc.id_sucursal" :clearable="false" class="bg-white"></v-select>
        </div>
        <div class="col-md-3">
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
              <th style="width: 8%">ID</th><th style="width: 25%">Empresa / Sucursal</th><th style="width: 30%">Nombre y Responsable</th><th style="width: 12%" class="text-center">Estado</th><th style="width: 25%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="almacen in almacenesPaginados" :key="almacen.id_almacen">
              <td><span class="badge bg-dark mb-1">#{{ almacen.id_almacen }}</span></td>
              <td><div class="small fw-bold text-dark"><i class="bi bi-building me-1"></i>{{ almacen.empresa_nombre }}</div><div class="small text-muted mt-1"><i class="bi bi-shop me-1"></i>{{ almacen.sucursal_nombre }}</div></td>
              <td><div class="fw-bold text-primary">{{ almacen.nombre }}</div><div class="small text-muted"><i class="bi bi-person me-1"></i>{{ almacen.responsable || 'Sin asignar' }}</div></td>
              <td class="text-center"><span class="badge rounded-pill" :class="almacen.activo ? 'bg-success' : 'bg-danger'">{{ almacen.activo ? 'Activo' : 'Inactivo' }}</span></td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(almacen)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm me-2" :class="almacen.activo ? 'btn-outline-danger' : 'btn-outline-success'" :title="almacen.activo ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(almacen)"><i class="bi" :class="almacen.activo ? 'bi-x-circle' : 'bi-check-circle'"></i></button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(almacen.id_almacen, almacen.nombre)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="almacenesFiltrados.length === 0"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron almacenes.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="almacenesFiltrados.length > 0">
        <span class="text-muted small">Mostrando {{ almacenesPaginados.length }} de {{ almacenesFiltrados.length }} registros</span>
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
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content shadow-sm border-0">
            <div class="modal-header py-3 px-4 text-white bg-primary">
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-box-seam'"></i> {{ editando ? 'Actualizar' : 'Nuevo Almacén' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarAlmacen" novalidate>
              <div class="modal-body py-4 px-4">
                <div class="row g-3 mb-4">
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Empresa Matriz</label>
                    <v-select v-model="empresaForm" :options="empresas" label="razon_social" :reduce="emp => emp.id_empresa" @update:modelValue="form.id_sucursal = ''" class="bg-white"></v-select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">Sucursal <span class="text-danger">*</span></label>
                    <v-select v-model="form.id_sucursal" :options="sucursalesDropdownForm" label="nombre" :reduce="suc => suc.id_sucursal" :disabled="!empresaForm" class="bg-white"></v-select>
                  </div>
                </div>
                <div class="mb-3"><label class="form-label text-muted small fw-bold">Nombre <span class="text-danger">*</span></label><input type="text" class="form-control" v-model="form.nombre" required></div>
                <div class="mb-3"><label class="form-label text-muted small fw-bold">Responsable</label><input type="text" class="form-control" v-model="form.responsable"></div>
                <div class="mb-2"><label class="form-label text-muted small fw-bold">Descripción</label><textarea class="form-control" rows="3" v-model="form.descripcion"></textarea></div>
              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.id_sucursal || !form.nombre"><i class="bi bi-save me-2"></i>Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>