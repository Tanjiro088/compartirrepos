<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2' 
import { useGlobalStore } from '../stores/store.js'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const store = useGlobalStore()
const API_URL = store.baseUrl + 'empresas'

const listaEmpresas = ref([])
const busqueda = ref('')
const filtroRegimen = ref('Todos')
const filtroEstado = ref('todos')
const modalAbierto = ref(false)
const editando = ref(false)
const idSeleccionado = ref(null)

// Opciones estáticas para el formulario (cuando creas una nueva empresa, necesitas ver todas)
const opcionesRegimen = ['General de Ley Personas Morales', 'Simplificado de Confianza', 'General de Ley', 'RESICO']

const formulario = ref({
  ruc: '', razon_social: '', nombre_comercial: '', direccion: '', 
  telefono: '', email: '', sitio_web: '', regimen_fiscal: 'General de Ley Personas Morales', activa: true
})

const errores = ref({ ruc: '', razon_social: '', telefono: '', email: '' })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

watch([filtroEstado, filtroRegimen, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES Y SWEETALERT
// ===============================

const cargarEmpresas = async () => {
  try {
    const response = await axios.get(API_URL)
    const dataLimpios = response.data.data || response.data.Datos || response.data
    listaEmpresas.value = dataLimpios
  } catch (err) { console.error("Error cargando empresas:", err) }
}

const guardarEmpresa = async () => {
  if (!formularioValido.value) return
  try {
    if (editando.value) {
      await axios.put(`${API_URL}/${idSeleccionado.value}`, formulario.value)
    } else {
      await axios.post(API_URL, formulario.value)
    }
    cerrarModal()
    await cargarEmpresas()
    Swal.fire({ icon: 'success', title: '¡Éxito!', text: 'La empresa se guardó correctamente.', timer: 2000, showConfirmButton: false })
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Oops...', text: 'Error de validación o comunicación con el servidor al guardar.' })
  }
}

const cambiarEstado = async (empresa) => {
  const accionText = empresa.activa ? 'deshabilitar' : 'habilitar'
  const result = await Swal.fire({
    title: `¿${accionText.charAt(0).toUpperCase() + accionText.slice(1)} Empresa?`,
    text: `¿Estás seguro de ${accionText} a ${empresa.razon_social}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: empresa.activa ? '#dc3545' : '#198754',
    cancelButtonColor: '#6c757d',
    confirmButtonText: `Sí, ${accionText}`,
    cancelButtonText: 'Cancelar'
  })

  if (result.isConfirmed) {
    try {
      const datosActualizados = { ...empresa, activa: !empresa.activa }
      await axios.put(`${API_URL}/${empresa.id_empresa}`, datosActualizados)
      await cargarEmpresas()
      Swal.fire({ icon: 'success', title: 'Actualizado', text: 'El estado ha sido modificado.', timer: 1500, showConfirmButton: false })
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cambiar el estado.' })
    }
  }
}

const solicitarConfirmacionBorrado = async (id, nombre) => {
  const result = await Swal.fire({
    title: '¿Confirmar Eliminación?',
    html: `La empresa <b>${nombre}</b> será dada de baja del sistema.`,
    icon: 'error',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-trash"></i> Eliminar',
    cancelButtonText: 'Cancelar'
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`${API_URL}/${id}`)
      await cargarEmpresas()
      Swal.fire({ icon: 'success', title: 'Eliminada', text: 'La empresa ha sido eliminada lógicamente.', timer: 1500, showConfirmButton: false })
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo eliminar la empresa.' })
    }
  }
}

// ===============================
// VALIDACIONES Y UI
// ===============================

const validarRucInput = () => {
  formulario.value.ruc = formulario.value.ruc.toUpperCase().replace(/[^A-Z0-9Ñ&]/g, '')
  const ruc = formulario.value.ruc
  if (!ruc) errores.value.ruc = 'El RUC / RFC es obligatorio.'
  else if (ruc.length < 11) errores.value.ruc = 'Longitud insuficiente. Mínimo 11 caracteres.'
  else errores.value.ruc = ''
}
const validarRazonSocialInput = () => {
  if (!formulario.value.razon_social.trim()) errores.value.razon_social = 'La Razón Social es obligatoria.'
  else if (formulario.value.razon_social.trim().length < 3) errores.value.razon_social = 'Debe contener al menos 3 caracteres.'
  else errores.value.razon_social = ''
}
const validarTelefonoInput = () => {
  formulario.value.telefono = formulario.value.telefono.replace(/\D/g, '')
  const tel = formulario.value.telefono
  if (tel && tel.length !== 10) errores.value.telefono = 'El teléfono debe ser de exactamente 10 dígitos.'
  else errores.value.telefono = ''
}
const validarEmailInput = () => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (formulario.value.email && !emailRegex.test(formulario.value.email)) errores.value.email = 'Formato de correo inválido.'
  else errores.value.email = ''
}

const formularioValido = computed(() => {
  const rucValido = formulario.value.ruc.length >= 11
  const razonSocialValida = formulario.value.razon_social.trim().length >= 3
  const sinErrores = !errores.value.ruc && !errores.value.razon_social && !errores.value.telefono && !errores.value.email
  return rucValido && razonSocialValida && sinErrores
})

const abrirModalCrear = () => {
  editando.value = false
  formulario.value = { ruc: '', razon_social: '', nombre_comercial: '', direccion: '', telefono: '', email: '', sitio_web: '', regimen_fiscal: 'General de Ley Personas Morales', activa: true }
  errores.value = { ruc: '', razon_social: '', telefono: '', email: '' }
  modalAbierto.value = true
}

const abrirModalEditar = (emp) => {
  editando.value = true
  idSeleccionado.value = emp.id_empresa
  formulario.value = { ...emp, activa: emp.activa == 1 }
  errores.value = { ruc: '', razon_social: '', telefono: '', email: '' }
  modalAbierto.value = true
}

const cerrarModal = () => { modalAbierto.value = false }

// ===============================
// COMPUTEDS (Filtros y Paginación)
// ===============================

// MAGIA DE UX: Este filtro extrae SOLO los regímenes que están siendo usados en la tabla
const opcionesFiltroRegimen = computed(() => {
  const regimenesUsados = [...new Set(listaEmpresas.value.map(e => e.regimen_fiscal).filter(r => r))]
  return ['Todos', ...regimenesUsados]
})

const empresasFiltradas = computed(() => {
  return listaEmpresas.value.filter(e => {
    const pasaEstado = filtroEstado.value === 'todos' || (filtroEstado.value === 'activos' && e.activa == 1) || (filtroEstado.value === 'inactivos' && e.activa == 0)
    const pasaRegimen = filtroRegimen.value === 'Todos' || e.regimen_fiscal === filtroRegimen.value
    const coincideBusqueda = e.razon_social.toLowerCase().includes(busqueda.value.toLowerCase()) || e.ruc.includes(busqueda.value.toUpperCase())
    return pasaEstado && pasaRegimen && coincideBusqueda
  })
})

const totalPaginas = computed(() => Math.ceil(empresasFiltradas.value.length / registrosPorPagina.value) || 1)

const empresasPaginadas = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  const fin = inicio + registrosPorPagina.value
  return empresasFiltradas.value.slice(inicio, fin)
})

const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag }

onMounted(() => { cargarEmpresas() })
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Gestión y Configuración de Empresas</h2>

    <div class="card shadow-sm p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Catálogo Empresarial</h4>
        <button class="btn btn-primary" @click="abrirModalCrear"><i class="bi bi-plus-circle me-1"></i> Nueva Empresa</button>
      </div>

      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-3 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold d-block">Estado</label>
          <div class="btn-group w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" id="estadoTodosEmp" value="todos" v-model="filtroEstado">
            <label class="btn btn-outline-secondary btn-sm" for="estadoTodosEmp">Todos</label>
            <input type="radio" class="btn-check" id="estadoActivosEmp" value="activos" v-model="filtroEstado">
            <label class="btn btn-outline-success btn-sm" for="estadoActivosEmp">Activas</label>
            <input type="radio" class="btn-check" id="estadoInactivosEmp" value="inactivos" v-model="filtroEstado">
            <label class="btn btn-outline-danger btn-sm" for="estadoInactivosEmp">Inactivas</label>
          </div>
        </div>
        
        <!-- Filtro con lógica dinámica -->
        <div class="col-md-4 mb-2 mb-md-0">
          <label class="form-label text-muted small fw-bold">Filtrar por Régimen Fiscal</label>
          <v-select v-model="filtroRegimen" :options="opcionesFiltroRegimen" :clearable="false" class="bg-white"></v-select>
        </div>
        
        <!-- Búsqueda con Input Group y Lupa -->
        <div class="col-md-5">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" placeholder="Código, RUC o Razón Social..." v-model="busqueda" />
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th style="width: 15%">RUC / RFC</th>
              <th style="width: 25%">Razón Social</th>
              <th style="width: 25%">Contacto / Régimen</th>
              <th style="width: 10%" class="text-center">Estado</th>
              <th style="width: 25%" class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="empresa in empresasPaginadas" :key="empresa.id_empresa">
              <td><div class="fw-bold">{{ empresa.ruc }}</div><div class="small text-muted">Identificador</div></td>
              <td>
                <div class="fw-semibold">{{ empresa.razon_social }}</div>
                <span class="badge bg-light text-dark border mt-1" v-if="empresa.nombre_comercial">{{ empresa.nombre_comercial }}</span>
                <span class="badge bg-light text-muted border mt-1" v-else>Sin Nombre Comercial</span>
              </td>
              <td>
                <div class="text-truncate small text-muted" style="max-width: 220px;" v-if="empresa.email"><i class="bi bi-envelope"></i> {{ empresa.email }}</div>
                <div class="text-truncate small text-muted" style="max-width: 220px;" v-if="empresa.telefono"><i class="bi bi-telephone"></i> {{ empresa.telefono }}</div>
                <div class="small fw-semibold mt-1" style="color: #6c757d;">{{ empresa.regimen_fiscal || 'Sin régimen' }}</div>
              </td>
              <td class="text-center"><span class="badge" :class="empresa.activa ? 'bg-success' : 'bg-danger'">{{ empresa.activa ? 'Activa' : 'Inactiva' }}</span></td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(empresa)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm me-2" :class="empresa.activa ? 'btn-outline-danger' : 'btn-outline-success'" :title="empresa.activa ? 'Deshabilitar' : 'Habilitar'" @click="cambiarEstado(empresa)"><i class="bi" :class="empresa.activa ? 'bi-x-circle' : 'bi-check-circle'"></i></button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(empresa.id_empresa, empresa.razon_social)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="empresasFiltradas.length === 0"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron empresas.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="empresasFiltradas.length > 0">
        <span class="text-muted small">Mostrando {{ empresasPaginadas.length }} de {{ empresasFiltradas.length }} registros</span>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-building-add'"></i> {{ editando ? 'Actualizar Datos' : 'Nueva Empresa' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarEmpresa" novalidate>
              <div class="modal-body py-4 px-4" style="max-height: 75vh; overflow-y: auto;">
                <h6 class="text-primary mb-3 border-bottom pb-2 fw-bold">1. Información Fiscal</h6>
                <div class="row g-3 mb-4">
                  <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">RUC / RFC <span class="text-danger">*</span></label>
                    <input type="text" class="form-control text-uppercase" :class="{ 'is-invalid': errores.ruc, 'is-valid': formulario.ruc && !errores.ruc }" v-model="formulario.ruc" @input="validarRucInput" maxlength="20" placeholder="Ej. ABC..." />
                    <div class="invalid-feedback" v-if="errores.ruc">{{ errores.ruc }}</div>
                  </div>
                  <div class="col-md-8">
                    <label class="form-label fw-bold small text-muted">Razón Social <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" :class="{ 'is-invalid': errores.razon_social }" v-model="formulario.razon_social" @input="validarRazonSocialInput" placeholder="Nombre legal" />
                    <div class="invalid-feedback" v-if="errores.razon_social">{{ errores.razon_social }}</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nombre Comercial</label>
                    <input type="text" class="form-control" v-model="formulario.nombre_comercial" />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Régimen Fiscal</label>
                    <v-select v-model="formulario.regimen_fiscal" :options="opcionesRegimen" :clearable="false" class="bg-white"></v-select>
                  </div>
                  <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted">Dirección Fiscal</label>
                    <input type="text" class="form-control" v-model="formulario.direccion" />
                  </div>
                </div>
                <h6 class="text-primary mb-3 border-bottom pb-2 fw-bold">2. Datos de Contacto</h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Teléfono</label>
                    <input type="text" class="form-control" :class="{ 'is-invalid': errores.telefono }" v-model="formulario.telefono" @input="validarTelefonoInput" maxlength="10" />
                    <div class="invalid-feedback" v-if="errores.telefono">{{ errores.telefono }}</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Correo Electrónico</label>
                    <input type="email" class="form-control" :class="{ 'is-invalid': errores.email }" v-model="formulario.email" @input="validarEmailInput" />
                    <div class="invalid-feedback" v-if="errores.email">{{ errores.email }}</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Sitio Web</label>
                    <input type="text" class="form-control" v-model="formulario.sitio_web" />
                  </div>
                </div>
              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-success fw-bold px-4" :disabled="!formularioValido"><i class="bi bi-save me-2"></i>Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>