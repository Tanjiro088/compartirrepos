<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const store = useGlobalStore()
const url = store.baseUrl

const folios = ref([])
const sucursales = ref([]) 
const modalAbierto = ref(false)
const editando = ref(false)

const filtroSucursal = ref('todas')
const busqueda = ref('')

const form = ref({ id_folio: null, id_sucursal: '', tipo_documento: 'factura', serie: '', correlativo_actual: 0 })

const opcionesDocumentos = [
  { id: 'factura', nombre: 'Factura' }, { id: 'boleta', nombre: 'Boleta' },
  { id: 'nota_credito', nombre: 'Nota de Crédito' }, { id: 'nota_debito', nombre: 'Nota de Débito' }, { id: 'ticket', nombre: 'Ticket Estándar' }
]

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

watch([filtroSucursal, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerFolios = async () => {
  try {
    const respuesta = await axios.get(url + 'folios')
    folios.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) { console.error('Error:', error) }
}
const obtenerSucursales = async () => {
  try {
    const respuesta = await axios.get(url + 'sucursales')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    sucursales.value = dataLimpios.filter(s => s.activa == 1)
  } catch (error) { console.error('Error:', error) }
}
const guardarFolio = async () => {
  try {
    if (editando.value) await axios.put(`${url}folios/${form.value.id_folio}`, form.value)
    else await axios.post(`${url}folios`, form.value)
    await obtenerFolios()
    cerrarModal()
    Swal.fire({ icon: 'success', title: '¡Éxito!', timer: 2000, showConfirmButton: false })
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar.' }) }
}
const eliminarFolio = async (id, serie) => {
  const result = await Swal.fire({ title: '¿Eliminar?', html: `La serie <b>${serie}</b> será eliminada.`, icon: 'error', showCancelButton: true, confirmButtonColor: '#dc3545' })
  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}folios/${id}`)
      await obtenerFolios()
      Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false })
    } catch (error) { Swal.fire('Error', 'No se pudo eliminar.', 'error') }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => { editando.value = false; form.value = { id_folio: null, id_sucursal: '', tipo_documento: 'factura', serie: '', correlativo_actual: 0 }; modalAbierto.value = true }
const abrirModalEditar = (folio) => { editando.value = true; form.value = { ...folio }; modalAbierto.value = true }
const cerrarModal = () => { modalAbierto.value = false }
const formatearTipoDocumento = (tipo) => { const doc = opcionesDocumentos.find(d => d.id === tipo); return doc ? doc.nombre : tipo }

// MAGIA DE UX: Este filtro extrae SOLO las sucursales que están siendo usadas en la tabla de folios
const opcionesFiltroSucursal = computed(() => {
  const idsUsados = [...new Set(folios.value.map(f => f.id_sucursal))]
  const sucursalesConDatos = sucursales.value.filter(s => idsUsados.includes(s.id_sucursal))
  
  return [{ id_sucursal: 'todas', nombre: 'Todas...' }, ...sucursalesConDatos]
})

const foliosFiltrados = computed(() => {
  let resultado = folios.value
  if (filtroSucursal.value !== 'todas') resultado = resultado.filter(f => f.id_sucursal == filtroSucursal.value)
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(f => f.serie.toLowerCase().includes(termino) || formatearTipoDocumento(f.tipo_documento).toLowerCase().includes(termino))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(foliosFiltrados.value.length / registrosPorPagina.value) || 1)
const foliosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return foliosFiltrados.value.slice(inicio, inicio + registrosPorPagina.value)
})
const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag }

onMounted(() => { obtenerFolios(); obtenerSucursales() })
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Control de Folios</h2>
    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Series y Folios Registrados</h4>
        <button class="btn btn-primary" @click="abrirModalCrear"><i class="bi bi-plus-circle me-1"></i> Nuevo Folio</button>
      </div>

      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-6 mb-2 mb-md-0">
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
        <div class="col-md-6">
          <label class="form-label text-muted small fw-bold">Búsqueda rápida</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar por serie o tipo...">
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr><th style="width: 8%">ID</th><th style="width: 25%">Sucursal</th><th style="width: 25%">Tipo</th><th style="width: 12%" class="text-center">Serie</th><th style="width: 15%" class="text-center">Correlativo</th><th style="width: 15%" class="text-center">Opciones</th></tr>
          </thead>
          <tbody>
            <tr v-for="folio in foliosPaginados" :key="folio.id_folio">
              <td><span class="badge bg-dark mb-1">#{{ folio.id_folio }}</span></td>
              <td><div class="small fw-bold text-dark"><i class="bi bi-shop me-1"></i>{{ folio.sucursal_nombre }}</div></td>
              <td><span class="text-secondary fw-semibold">{{ formatearTipoDocumento(folio.tipo_documento) }}</span></td>
              <td class="text-center"><span class="badge bg-info text-dark border border-info px-3 py-2">{{ folio.serie }}</span></td>
              <td class="text-center fw-bold fs-5 text-primary">{{ folio.correlativo_actual }}</td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(folio)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="eliminarFolio(folio.id_folio, folio.serie)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="foliosFiltrados.length === 0"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron folios.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="foliosFiltrados.length > 0">
        <span class="text-muted small">Mostrando {{ foliosPaginados.length }} de {{ foliosFiltrados.length }} registros</span>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-file-earmark-plus'"></i> {{ editando ? 'Ajustar Serie' : 'Nueva Serie' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarFolio">
              <div class="modal-body py-4 px-4">
                <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">Sucursal Asignada <span class="text-danger">*</span></label>
                  <v-select 
                    v-model="form.id_sucursal" 
                    :options="sucursales" 
                    label="nombre" 
                    :reduce="suc => suc.id_sucursal"
                    class="bg-white"
                  ></v-select>
                </div>
                <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">Tipo Documento <span class="text-danger">*</span></label>
                  <v-select 
                    v-model="form.tipo_documento" 
                    :options="opcionesDocumentos" 
                    label="nombre" 
                    :reduce="doc => doc.id" 
                    :clearable="false"
                    class="bg-white"
                  ></v-select>
                </div>
                <div class="row g-3 mb-2">
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Serie <span class="text-danger">*</span></label><input type="text" class="form-control text-uppercase" v-model="form.serie" required></div>
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Correlativo <span class="text-danger">*</span></label><input type="number" class="form-control text-end fw-bold" v-model="form.correlativo_actual" required></div>
                </div>
              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.id_sucursal || !form.tipo_documento || !form.serie"><i class="bi bi-save me-2"></i>Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>