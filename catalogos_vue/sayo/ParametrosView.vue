<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { useGlobalStore } from '../stores/store.js'
import vSelect from 'vue-select'
import 'vue-select/dist/vue-select.css'

const store = useGlobalStore()
const url = store.baseUrl

const parametros = ref([])
const empresas = ref([]) 
const modalAbierto = ref(false)
const editando = ref(false)

const filtroEmpresa = ref('todas')
const busqueda = ref('')

const form = ref({ id_parametro: null, id_empresa: '', clave: '', valor: '', descripcion: '' })

// --- PAGINACIÓN ---
const paginaActual = ref(1)
const registrosPorPagina = ref(10)

watch([filtroEmpresa, busqueda], () => { paginaActual.value = 1 })

// ===============================
// PETICIONES Y SWEETALERT
// ===============================

const obtenerParametros = async () => {
  try {
    const respuesta = await axios.get(url + 'parametros')
    parametros.value = respuesta.data.data || respuesta.data.Datos || respuesta.data
  } catch (error) { console.error('Error:', error) }
}
const obtenerEmpresas = async () => {
  try {
    const respuesta = await axios.get(url + 'empresas')
    const dataLimpios = respuesta.data.data || respuesta.data.Datos || respuesta.data
    empresas.value = dataLimpios.filter(e => e.activa == 1)
  } catch (error) { console.error('Error:', error) }
}
const guardarParametro = async () => {
  try {
    if (editando.value) await axios.put(`${url}parametros/${form.value.id_parametro}`, form.value)
    else await axios.post(`${url}parametros`, form.value)
    cerrarModal()
    await obtenerParametros()
    Swal.fire({ icon: 'success', title: '¡Éxito!', timer: 2000, showConfirmButton: false })
  } catch (error) { Swal.fire({ icon: 'error', title: 'Error', text: 'Error al guardar.' }) }
}
const solicitarConfirmacionBorrado = async (id, clave) => {
  const result = await Swal.fire({ title: '¿Eliminar?', html: `El parámetro <b>${clave}</b> será eliminado.`, icon: 'error', showCancelButton: true, confirmButtonColor: '#dc3545' })
  if (result.isConfirmed) {
    try {
      await axios.delete(`${url}parametros/${id}`)
      await obtenerParametros()
      Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false })
    } catch (err) { Swal.fire('Error', 'No se pudo eliminar.', 'error') }
  }
}

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirModalCrear = () => { editando.value = false; form.value = { id_parametro: null, id_empresa: '', clave: '', valor: '', descripcion: '' }; modalAbierto.value = true }
const abrirModalEditar = (param) => { editando.value = true; form.value = { ...param }; modalAbierto.value = true }
const cerrarModal = () => { modalAbierto.value = false }

// MAGIA DE UX: Este filtro extrae SOLO las empresas que están siendo usadas en la tabla de parámetros
const opcionesFiltroEmpresa = computed(() => {
  const idsUsados = [...new Set(parametros.value.map(p => p.id_empresa))]
  const empresasConDatos = empresas.value.filter(e => idsUsados.includes(e.id_empresa))
  
  return [{ id_empresa: 'todas', razon_social: 'Todas...' }, ...empresasConDatos]
})

const parametrosFiltrados = computed(() => {
  let resultado = parametros.value
  if (filtroEmpresa.value !== 'todas') resultado = resultado.filter(p => p.id_empresa == filtroEmpresa.value)
  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase()
    resultado = resultado.filter(p => p.clave.toLowerCase().includes(termino) || (p.descripcion && p.descripcion.toLowerCase().includes(termino)))
  }
  return resultado
})

const totalPaginas = computed(() => Math.ceil(parametrosFiltrados.value.length / registrosPorPagina.value) || 1)
const parametrosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value
  return parametrosFiltrados.value.slice(inicio, inicio + registrosPorPagina.value)
})
const cambiarPagina = (pag) => { if (pag >= 1 && pag <= totalPaginas.value) paginaActual.value = pag }

onMounted(() => { obtenerEmpresas(); obtenerParametros() })
</script>

<template>
  <div class="container mt-5 mb-5 animate-fade-in">
    <h2 class="text-center mb-4 text-secondary">Parámetros del Sistema</h2>
    <div class="card shadow-sm p-4 border-0">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark">Configuraciones Globales</h4>
        <button class="btn btn-primary" @click="abrirModalCrear"><i class="bi bi-plus-circle me-1"></i> Nuevo Parámetro</button>
      </div>

      <div class="row mb-4 bg-light p-3 rounded shadow-sm border mx-0 align-items-end">
        <div class="col-md-6 mb-2 mb-md-0">
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
        <div class="col-md-6">
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
            <tr><th style="width: 25%">Empresa</th><th style="width: 20%">Clave</th><th style="width: 15%">Valor</th><th style="width: 25%">Descripción</th><th style="width: 15%" class="text-center">Opciones</th></tr>
          </thead>
          <tbody>
            <tr v-for="param in parametrosPaginados" :key="param.id_parametro">
              <td><div class="small fw-bold text-dark"><i class="bi bi-building me-1"></i>{{ param.empresa_nombre }}</div></td>
              <td><span class="badge bg-dark">{{ param.clave }}</span></td>
              <td class="fw-bold text-primary">{{ param.valor }}</td>
              <td><div class="small text-muted" style="display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ param.descripcion || 'Sin descripción' }}</div></td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirModalEditar(param)"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger" title="Eliminar" @click="solicitarConfirmacionBorrado(param.id_parametro, param.clave)"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
            <tr v-if="parametrosFiltrados.length === 0"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron parámetros.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- CONTROLES DE PAGINACIÓN -->
      <div class="d-flex justify-content-between align-items-center mt-3" v-if="parametrosFiltrados.length > 0">
        <span class="text-muted small">Mostrando {{ parametrosPaginados.length }} de {{ parametrosFiltrados.length }} registros</span>
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
              <h5 class="modal-title fw-semibold m-0"><i class="bi" :class="editando ? 'bi-pencil-square' : 'bi-gear'"></i> {{ editando ? 'Modificar' : 'Nuevo Parámetro' }}</h5>
              <button type="button" class="btn-close btn-close-white" @click="cerrarModal"></button>
            </div>
            <form @submit.prevent="guardarParametro">
              <div class="modal-body py-4 px-4">
                <div class="mb-4">
                  <label class="form-label text-muted small fw-bold">Empresa Matriz <span class="text-danger">*</span></label>
                  <v-select 
                    v-model="form.id_empresa" 
                    :options="empresas" 
                    label="razon_social" 
                    :reduce="emp => emp.id_empresa"
                    class="bg-white"
                  ></v-select>
                </div>
                <div class="row g-3 mb-4">
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Clave <span class="text-danger">*</span></label><input type="text" class="form-control text-uppercase" v-model="form.clave" required></div>
                  <div class="col-md-6"><label class="form-label text-muted small fw-bold">Valor</label><input type="text" class="form-control" v-model="form.valor"></div>
                </div>
                <div class="mb-2"><label class="form-label text-muted small fw-bold">Descripción</label><textarea class="form-control" rows="2" v-model="form.descripcion"></textarea></div>
              </div>
              <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4 me-2" @click="cerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold px-4" :disabled="!form.id_empresa || !form.clave"><i class="bi bi-save me-2"></i>Guardar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>