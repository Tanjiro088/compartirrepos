<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Centralizado para Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Mermas</h3>
                
                <!-- Barra de Acciones y Búsqueda Principal -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="d-flex flex-wrap gap-2">
                        <button 
                            class="btn btn-outline-danger fw-semibold d-inline-flex align-items-center gap-1" 
                            @click="abrirModalRegistro"
                            :disabled="isLoading || isSaving"
                        >
                            <i class="bi bi-plus-lg"></i>
                            <span>Registrar Merma</span>
                        </button>
                        <button 
                            class="btn btn-outline-success fw-semibold d-inline-flex align-items-center gap-1" 
                            @click="exportarExcel"
                            :disabled="isLoading || exportando || isSaving"
                        >
                            <span v-if="exportando" class="spinner-border spinner-border-sm" role="status"></span>
                            <i v-else class="bi bi-file-earmark-excel"></i>
                            <span>{{ exportando ? 'Exportando...' : 'Exportar Excel' }}</span>
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                            :disabled="isLoading || isSaving"
                            @click="cargarDatosDesdeServidor"
                            title="Sincronizar mermas"
                        >
                            <span v-if="isLoading" class="spinner-border spinner-border-sm" role="status"></span>
                            <i v-else class="bi bi-arrow-clockwise"></i>
                            <span>Actualizar</span>
                        </button>
                    </div>
                    <div class="w-100 w-md-50" style="max-width: 380px;">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input 
                                type="text" 
                                class="form-control" 
                                v-model="busquedaMermas" 
                                placeholder="Buscar por folio, tipo o almacén..."
                                :disabled="isLoading"
                            >
                        </div>
                    </div>
                </div>

                <!-- SPINNER DE CARGA PRINCIPAL -->
                <div v-if="isLoading" class="text-center py-5">
                    <div class="spinner-border text-danger" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted mb-0">Consultando historial de mermas e inventarios...</p>
                </div>

                <!-- Tabla Principal de Historial -->
                <template v-else>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio</th>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Almacén</th>
                                    <th>Monto Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="merma in mermasFiltradas" :key="merma.id_merma || merma.id">
                                    <td class="fw-bold text-primary">{{ merma.folio || ('MER-' + (merma.id_merma || merma.id)) }}</td>
                                    <td>{{ merma.fecha || merma.fecha_merma || '—' }}</td>
                                    <td class="text-capitalize">{{ (merma.tipo || merma.tipo_merma || 'General').replace(/_/g, ' ') }}</td>
                                    <td>{{ merma.almacen_nombre || merma.almacen || 'Almacén general' }}</td>
                                    <td class="fw-bold text-danger">$ {{ formatoMoneda(merma.monto || merma.monto_total || 0) }}</td>
                                    <td>
                                        <span class="badge" :class="getEstadoMermaBadgeClass ? getEstadoMermaBadgeClass(merma.estado) : 'bg-secondary'">
                                            {{ (merma.estado || 'REGISTRADA').toUpperCase() }}
                                        </span>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" 
                                            @click="abrirModalVerMerma(merma)"
                                            :disabled="isSaving"
                                            title="Ver detalle del reporte"
                                        >
                                            <i class="bi bi-eye"></i>
                                            <span>Detalle</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="mermasFiltradas.length === 0">
                                    <td colspan="7" class="text-muted py-4 fst-italic">
                                        {{ busquedaMermas ? 'No se encontraron mermas con ese criterio.' : 'No existen registros de mermas almacenados en el sistema.' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Paginador
                      v-if="totalPaginas > 1"
                      :pagina-actual="pagina"
                      :total-paginas="totalPaginas"
                      @cambiar="cambiarPagina"
                    />
                </template>
            </div>
        </div>

        <!-- MODAL 1: REGISTRO DE MERMA -->
        <div class="modal fade" id="registroModal" tabindex="-1" ref="registroModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content shadow border-0">
                    <div class="modal-header bg-light border-bottom">
                        <h5 class="modal-title fw-bold text-danger m-0">Registrar Nueva Merma de Inventario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="isSaving" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 mb-3 text-center fw-bold fs-5">
                            Folio Asignado: {{ folioGenerado || 'Generando...' }}
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" v-model="formRegistro.fecha_merma" :disabled="isSaving">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Tipo de Merma</label>
                                <select class="form-select" v-model="formRegistro.tipo_merma" :disabled="isSaving">
                                    <option value="dañado">Dañado</option>
                                    <option value="vencido">Vencido</option>
                                    <option value="robo">Robo</option>
                                    <option value="extraviado">Extraviado</option>
                                    <option value="devolucion_proveedor">Devolución a Proveedor</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Almacén de Origen <span class="text-danger">*</span></label>
                                <select class="form-select" v-model.number="formRegistro.id_almacen" :disabled="isSaving">
                                    <option value="" disabled>-- Seleccione un almacén activo --</option>
                                    <option v-for="alm in almacenes" :key="alm.id_almacen || alm.id" :value="Number(alm.id_almacen || alm.id)">
                                        {{ alm.nombre }} ({{ alm.sucursal_nombre || 'Sucursal' }})
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label fw-bold">Motivo / Causa Detallada</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    v-model.trim="formRegistro.motivo" 
                                    placeholder="Explique la causa o siniestro reportado..."
                                    :disabled="isSaving"
                                >
                            </div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">Productos a Mermar</h5>
                        
                        <!-- BUSCADOR DE ARTÍCULOS MERMABLES -->
                        <div class="row mb-4 align-items-end bg-light p-3 rounded border shadow-sm">
                            <div class="col-md-8 position-relative">
                                <label class="form-label fw-bold text-danger">Buscar Artículo en Almacén</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    placeholder="Escriba el nombre del artículo para filtrar..."
                                    v-model="busquedaProducto"
                                    @focus="mostrarDropdown = true"
                                    @blur="ocultarDropdownConRetraso"
                                    :disabled="isSaving || !formRegistro.id_almacen"
                                >
                                
                                <ul class="dropdown-menu w-100 show shadow" v-if="mostrarDropdown && productosFiltrados.length">
                                    <li v-for="prod in productosFiltrados" :key="prod.id_presentacion || prod.id_producto || prod.id">
                                        <button class="dropdown-item py-2" type="button" @mousedown.prevent="seleccionarProducto(prod)">
                                            {{ prod.nombre || prod.producto }} — <strong class="text-secondary">$ {{ formatoMoneda(prod.costo_promedio || prod.costo || prod.precio || 0) }}</strong>
                                        </button>
                                    </li>
                                </ul>
                                <div v-if="mostrarDropdown && busquedaProducto && !productosFiltrados.length" class="text-danger small position-absolute mt-1">
                                    No se encontraron artículos disponibles en este almacén.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button 
                                    class="btn btn-danger w-100 fw-bold d-inline-flex align-items-center justify-content-center gap-2" 
                                    @click="agregarProductoBuscado" 
                                    :disabled="!productoSeleccionado || isSaving"
                                >
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Incluir en Merma</span>
                                </button>
                            </div>
                        </div>

                        <!-- TABLA DE DETALLE DE MERMAS -->
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th width="15%">Cantidad</th>
                                        <th width="20%">Costo Unit. ($)</th>
                                        <th width="20%">Subtotal ($)</th>
                                        <th width="10%">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(detalle, index) in detallesMerma" :key="index">
                                        <td class="text-start ps-3 fw-bold text-secondary">{{ detalle.producto }}</td>
                                        <td>
                                            <input 
                                                type="number" 
                                                class="form-control form-control-sm text-center fw-bold font-monospace" 
                                                :class="{ 'is-invalid': !detalle.cantidad || detalle.cantidad <= 0 }"
                                                v-model.number="detalle.cantidad" 
                                                min="1"
                                                step="1"
                                                :disabled="isSaving"
                                            >
                                        </td>
                                        <td>
                                            <input 
                                                type="number" 
                                                class="form-control form-control-sm text-center font-monospace" 
                                                v-model.number="detalle.precio_costo" 
                                                min="0" 
                                                step="0.01"
                                                :disabled="isSaving"
                                            >
                                        </td>
                                        <td class="fw-bold text-dark font-monospace">$ {{ formatoMoneda((detalle.cantidad || 0) * (detalle.precio_costo || 0)) }}</td>
                                        <td>
                                            <button 
                                                class="btn btn-sm btn-outline-danger" 
                                                @click="eliminarFilaMerma(index)"
                                                :disabled="isSaving"
                                                title="Remover renglón"
                                            >
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="detallesMerma.length === 0">
                                        <td colspan="5" class="text-muted py-3 fst-italic">No hay productos en el reporte. Use el buscador superior para agregar existencias.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- PANEL DE COSTO TOTAL -->
                        <div class="row justify-content-end pt-2">
                            <div class="col-md-5 bg-light p-3 rounded border text-end">
                                <span class="text-muted small d-block fw-bold">TOTAL PÉRDIDA ESTIMADA:</span>
                                <span class="fs-3 fw-bold text-danger font-monospace">$ {{ formatoMoneda(totalMermaForm) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="isSaving">Cancelar</button>
                        <button 
                            class="btn btn-danger fw-bold d-inline-flex align-items-center gap-2" 
                            @click="confirmarRegistroMerma" 
                            :disabled="detallesMerma.length === 0 || hayErrorCantidad || isSaving"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>Guardar Reporte de Merma</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 2: VER / EDITAR DETALLE DE MERMA -->
        <div class="modal fade" id="detalleMermaModal" tabindex="-1" ref="detalleModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content shadow border-0">
                    <div 
                        class="modal-header bg-light border-bottom"
                        :class="modalModoMerma === 'ver' ? 'border-secondary' : 'border-warning'"
                    >
                        <h5 class="modal-title fw-bold m-0" :class="modalModoMerma === 'ver' ? 'text-secondary' : 'text-warning'">
                            {{ modalModoMerma === 'ver' ? 'Detalle de la Merma (Solo Lectura)' : 'Modificar Reporte de Merma' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="isSaving" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-secondary py-2 mb-3 fw-bold fs-5">
                            Folio: {{ mermaActiva.folio || ('MER-' + (mermaActiva.id_merma || mermaActiva.id)) }}
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">1. Datos Generales</h5>
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Fecha</label>
                                <input v-if="modalModoMerma === 'editar'" type="date" class="form-control" v-model="formEdicion.fecha_merma" :disabled="isSaving">
                                <div v-else class="form-control-plaintext">{{ mermaActiva.fecha_merma || mermaActiva.fecha || '—' }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Tipo de Merma</label>
                                <select v-if="modalModoMerma === 'editar'" class="form-select" v-model="formEdicion.tipo_merma" :disabled="isSaving">
                                    <option value="dañado">Dañado</option>
                                    <option value="vencido">Vencido</option>
                                    <option value="robo">Robo</option>
                                    <option value="extraviado">Extraviado</option>
                                    <option value="devolucion_proveedor">Devolución a Proveedor</option>
                                </select>
                                <div v-else class="form-control-plaintext text-capitalize">{{ (mermaActiva.tipo_merma || mermaActiva.tipo || 'General').replace(/_/g, ' ') }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Almacén / Sucursal</label>
                                <select v-if="modalModoMerma === 'editar'" class="form-select" v-model.number="formEdicion.id_almacen" :disabled="isSaving">
                                    <option v-for="alm in almacenes" :key="alm.id_almacen || alm.id" :value="Number(alm.id_almacen || alm.id)">
                                        {{ alm.nombre }} ({{ alm.sucursal_nombre || 'Sucursal' }})
                                    </option>
                                </select>
                                <div v-else class="form-control-plaintext">{{ mermaActiva.almacen_nombre || mermaActiva.almacen || 'Almacén general' }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Estado</label>
                                <div class="form-control-plaintext">
                                    <span class="badge" :class="getEstadoMermaBadgeClass ? getEstadoMermaBadgeClass(mermaActiva.estado) : 'bg-secondary'">
                                        {{ (mermaActiva.estado || 'REGISTRADA').toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label fw-bold">Motivo</label>
                                <textarea v-if="modalModoMerma === 'editar'" class="form-control" rows="2" v-model.trim="formEdicion.motivo" :disabled="isSaving"></textarea>
                                <div v-else class="form-control-plaintext">{{ mermaActiva.motivo || 'Sin motivo registrado' }}</div>
                            </div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">2. Productos Afectados</h5>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th width="15%">Cantidad</th>
                                        <th width="20%">Costo Unit. ($)</th>
                                        <th width="20%">Subtotal ($)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="det in mermaActiva.detalles" :key="det.id_detalle_merma || det.id">
                                        <td class="text-start ps-3 fw-bold text-secondary">{{ det.producto || det.nombre || 'Producto' }}</td>
                                        <td class="fw-bold font-monospace">{{ det.cantidad }}</td>
                                        <td class="font-monospace">$ {{ formatoMoneda(det.precio_costo) }}</td>
                                        <td class="fw-bold text-dark font-monospace">$ {{ formatoMoneda(det.subtotal || (Number(det.cantidad || 0) * Number(det.precio_costo || 0))) }}</td>
                                    </tr>
                                    <tr v-if="!mermaActiva.detalles || mermaActiva.detalles.length === 0">
                                        <td colspan="4" class="text-muted py-3 fst-italic">Sin productos desglosados en este reporte.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row justify-content-end pt-2">
                            <div class="col-md-5 bg-light p-3 rounded border text-end">
                                <span class="text-muted small d-block fw-bold">TOTAL PÉRDIDA:</span>
                                <span class="fs-3 fw-bold text-danger font-monospace">$ {{ formatoMoneda(mermaActiva.monto_total || mermaActiva.monto || 0) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="isSaving">Cerrar</button>
                        <button 
                            v-if="modalModoMerma === 'ver' && String(mermaActiva.estado || '').toLowerCase() === 'registrada'"
                            class="btn btn-outline-warning fw-bold d-inline-flex align-items-center gap-1" 
                            @click="activarEdicionMerma"
                            :disabled="isSaving"
                        >
                            <i class="bi bi-pencil"></i>
                            <span>Editar</span>
                        </button>
                        <button 
                            v-if="modalModoMerma === 'editar'"
                            class="btn btn-outline-success fw-bold d-inline-flex align-items-center gap-1" 
                            @click="guardarEdicionMerma"
                            :disabled="isSaving"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>Guardar Cambios</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import { useGlobalStore } from '@/stores/store.js'

import { 
    obtenerHistorialMermas, 
    calcularTotalMerma, 
    getEstadoMermaBadgeClass, 
    obtenerProductosMermables,
    registrarMerma,
    exportarMermas,
    obtenerSiguienteFolioMerma,
    obtenerMerma,
    actualizarMerma
} from '@/services/mermasService'

const store = useGlobalStore()
const url = computed(() => {
    const base = store.baseUrl || ''
    return base.endsWith('/') ? base : `${base}/`
})

// ============================================================================
// VARIABLES REACTIVAS
// ============================================================================
const registroModalRef = ref(null)
const detalleModalRef = ref(null)
const modalConfirm = ref(null)
const folioGenerado = ref('')
const modalModoMerma = ref('ver')

const isLoading = ref(false)
const isSaving = ref(false)
const exportando = ref(false)

const mermaActiva = ref({})
const formEdicion = ref({
    fecha_merma: '',
    tipo_merma: '',
    motivo: '',
    id_almacen: ''
})

const historialMermas = ref([])
const productosMaestros = ref([])
const almacenes = ref([])
const busquedaMermas = ref('')

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value) || 1)

const formRegistro = ref({
    tipo_merma: 'dañado',
    motivo: '',
    fecha_merma: new Date().toISOString().slice(0, 10),
    id_almacen: ''
})
const detallesMerma = ref([])

const busquedaProducto = ref('')
const productoSeleccionado = ref(null)
const mostrarDropdown = ref(false)

const errors = ref({
    id_almacen: '',
    motivo: '',
    detalles: ''
})

// ============================================================================
// HELPERS Y FORMATOS
// ============================================================================
const safeModalAction = (elementRef, action = 'show') => {
    if (!elementRef) return
    try {
        if (Modal && typeof Modal.getOrCreateInstance === 'function') {
            const instance = Modal.getOrCreateInstance(elementRef)
            if (instance && typeof instance[action] === 'function') {
                instance[action]()
            }
        }
    } catch (e) {
        console.warn(`No se pudo ejecutar Modal.${action}:`, e)
    }
}

const formatoMoneda = (valor) => {
    return Number(valor || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error en la operación de mermas.') => {
    if (error?.response?.data) {
        const data = error.response.data
        if (data.errors && typeof data.errors === 'object') {
            const primerCampo = Object.keys(data.errors)[0]
            if (primerCampo && Array.isArray(data.errors[primerCampo]) && data.errors[primerCampo].length) {
                return data.errors[primerCampo][0]
            }
        }
        return data.message || data.Mensaje || data.error || mensajeDefecto
    }
    return error?.message || mensajeDefecto
}

// ============================================================================
// CARGA DE DATOS Y CICLO DE VIDA
// ============================================================================
const cargarAlmacenes = async () => {
    try {
        let datosAlms = []
        try {
            const res = await axios.get(`${url.value}inventarios/catalogo-almacenes`)
            datosAlms = Array.isArray(res.data) ? res.data : (res.data.data || [])
        } catch {
            const respuesta = await axios.get(`${url.value}almacenes`)
            datosAlms = Array.isArray(respuesta.data) ? respuesta.data : (respuesta.data.data || [])
        }

        almacenes.value = datosAlms.filter(alm => {
            const estaActivo = alm.activo === 1 || alm.activo === true || alm.activo === '1' || alm.activo === undefined
            const noEliminado = !alm.deleted_at
            return estaActivo && noEliminado
        })
    } catch (error) {
        almacenes.value = []
        console.error("Error al cargar almacenes activos en mermas:", error)
    }
}

const cargarDatosDesdeServidor = async () => {
    isLoading.value = true
    try {
        const respuesta = await obtenerHistorialMermas({ pagina: pagina.value, por_pagina: porPagina.value })
        historialMermas.value = Array.isArray(respuesta) ? respuesta : (respuesta.mermas || respuesta.data || respuesta.resultados || [])
        totalRegistros.value = respuesta.total || historialMermas.value.length
    } catch (error) {
        console.error('Error al consultar historial de mermas:', error)
        historialMermas.value = []
        const msg = extraerMensajeError(error, 'No se pudo obtener el historial de mermas desde el servidor.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error de Red',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } finally {
        isLoading.value = false
    }
}

const cargarProductosPorAlmacen = async (idAlmacen) => {
    if (!idAlmacen) {
        productosMaestros.value = []
        return
    }
    try {
        const prods = await obtenerProductosMermables(idAlmacen)
        productosMaestros.value = Array.isArray(prods) ? prods : (prods.data || prods.resultados || [])
    } catch {
        productosMaestros.value = []
    }
}

onMounted(async () => {
    await Promise.all([cargarAlmacenes(), cargarDatosDesdeServidor()])
})

onBeforeUnmount(() => {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove())
    document.body.classList.remove('modal-open')
})

watch(() => formRegistro.value.id_almacen, async (nuevo, viejo) => {
    if (nuevo && nuevo !== viejo) {
        if (detallesMerma.value.length > 0) {
            detallesMerma.value = [] // Limpia artículos si cambia de almacén físico
        }
        await cargarProductosPorAlmacen(nuevo)
    }
})

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    cargarDatosDesdeServidor()
}

const exportarExcel = async () => {
    if (exportando.value) return
    exportando.value = true
    try {
        if (typeof exportarMermas === 'function') {
            await exportarMermas({})
        } else if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Aviso',
                mensaje: 'Función de exportación no disponible en este momento.',
                tipo: 'info',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
    } catch (error) {
        console.error('Error al exportar mermas:', error)
        const msg = extraerMensajeError(error, 'Ocurrió un error al exportar los datos a Excel.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error de Exportación',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } finally {
        exportando.value = false
    }
}

// ============================================================================
// COMPUTADAS
// ============================================================================
const productosFiltrados = computed(() => {
    const lista = Array.isArray(productosMaestros.value) ? productosMaestros.value : []
    if (!busquedaProducto.value) return lista
    const q = busquedaProducto.value.toLowerCase().trim()
    return lista.filter(p => 
        (p.nombre || p.producto || '').toLowerCase().includes(q)
    )
})

const totalMermaForm = computed(() => {
    if (typeof calcularTotalMerma === 'function') {
        return Math.round(Number(calcularTotalMerma(detallesMerma.value) || 0) * 100) / 100
    }
    const total = detallesMerma.value.reduce((acc, d) => acc + ((Number(d.cantidad) || 0) * (Number(d.precio_costo) || 0)), 0)
    return Math.round(total * 100) / 100
})

const mermasFiltradas = computed(() => {
    const lista = Array.isArray(historialMermas.value) ? historialMermas.value : []
    if (!busquedaMermas.value.trim()) return lista
    const q = busquedaMermas.value.toLowerCase().trim()
    return lista.filter(m =>
        (m.folio && m.folio.toLowerCase().includes(q)) ||
        (m.tipo && m.tipo.toLowerCase().includes(q)) ||
        (m.tipo_merma && m.tipo_merma.toLowerCase().includes(q)) ||
        (m.almacen && m.almacen.toLowerCase().includes(q)) ||
        (m.almacen_nombre && m.almacen_nombre.toLowerCase().includes(q))
    )
})

const hayErrorCantidad = computed(() => {
    return detallesMerma.value.some(d => !d.cantidad || Number(d.cantidad) <= 0)
})

// ============================================================================
// MÉTODOS OPERATIVOS
// ============================================================================
const seleccionarProducto = (prod) => {
    productoSeleccionado.value = prod
    busquedaProducto.value = prod.nombre || prod.producto
    mostrarDropdown.value = false
}

const ocultarDropdownConRetraso = () => {
    setTimeout(() => { mostrarDropdown.value = false }, 250)
}

const agregarProductoBuscado = () => {
    if (!productoSeleccionado.value) return
    
    const prod = productoSeleccionado.value
    const idPres = prod.id_presentacion || prod.id_producto || prod.id
    
    const indiceExistente = detallesMerma.value.findIndex(item => Number(item.id_presentacion) === Number(idPres))
    
    if (indiceExistente !== -1) {
        detallesMerma.value[indiceExistente].cantidad += 1
    } else {
        detallesMerma.value.push({
            id_presentacion: Number(idPres),
            producto: prod.nombre || prod.producto,
            cantidad: 1,
            precio_costo: Number(prod.costo_promedio || prod.costo || prod.precio || 0)
        })
    }
    
    productoSeleccionado.value = null
    busquedaProducto.value = ''
}

const eliminarFilaMerma = (index) => {
    detallesMerma.value.splice(index, 1)
}

const abrirModalRegistro = async () => {
    detallesMerma.value = []
    errors.value = {}
    folioGenerado.value = ''
    
    await cargarAlmacenes()

    const primerAlmacen = almacenes.value[0]
    const idPrimerAlmacen = primerAlmacen ? Number(primerAlmacen.id_almacen || primerAlmacen.id) : ''

    formRegistro.value = {
        tipo_merma: 'dañado',
        motivo: '',
        fecha_merma: new Date().toISOString().slice(0, 10),
        id_almacen: idPrimerAlmacen
    }
    
    safeModalAction(registroModalRef.value, 'show')
    try { 
        folioGenerado.value = await obtenerSiguienteFolioMerma() 
    } catch { 
        folioGenerado.value = 'MER-' + Date.now().toString().slice(-6) 
    }
    
    if (idPrimerAlmacen) {
        await cargarProductosPorAlmacen(idPrimerAlmacen)
    }
}

const confirmarRegistroMerma = async () => {
    if (isSaving.value) return

    if (!formRegistro.value.id_almacen) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Campo Requerido',
                mensaje: 'Debe seleccionar un almacén válido.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }
    if (detallesMerma.value.length === 0) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Detalle Vacío',
                mensaje: 'Debe agregar al menos un producto al reporte de merma.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }
    if (hayErrorCantidad.value) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Cantidad Inválida',
                mensaje: 'Todas las cantidades deben ser enteros positivos mayores a cero.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }

    const almObj = almacenes.value.find(a => Number(a.id_almacen || a.id) === Number(formRegistro.value.id_almacen))
    const nombreAlm = almObj?.nombre || 'el almacén seleccionado'
    const montoTxt = formatoMoneda(totalMermaForm.value)

    if (modalConfirm.value) {
        const confirmado = await modalConfirm.value.open({
            titulo: 'Confirmar Registro de Merma',
            mensaje: `¿Deseas generar este reporte de merma para "${nombreAlm}" por un valor de $${montoTxt}?\n\nEl reporte quedará registrado para posterior aprobación.`,
            tipo: 'warning',
            mostrarCancelar: true,
            textoConfirmar: 'Sí, Registrar Merma',
            textoCancelar: 'Cancelar'
        })
        if (!confirmado) return
    }

    isSaving.value = true
    try {
        const payload = {
            id_almacen: Number(formRegistro.value.id_almacen),
            id_usuario: 1,
            fecha_merma: formRegistro.value.fecha_merma,
            tipo_merma: formRegistro.value.tipo_merma,
            motivo: String(formRegistro.value.motivo || '').trim(),
            monto: totalMermaForm.value,
            detalles: detallesMerma.value.map(d => ({
                id_presentacion: Number(d.id_presentacion),
                cantidad: Number(d.cantidad) || 1,
                precio_costo: Number(d.precio_costo) || 0,
                observaciones: ''
            }))
        }

        await registrarMerma(payload)
        safeModalAction(registroModalRef.value, 'hide')

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Merma Registrada',
                mensaje: 'Reporte de merma guardado exitosamente en el sistema.',
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
        await cargarDatosDesdeServidor()
    } catch (error) {
        console.error('Error al registrar merma:', error)
        const msg = extraerMensajeError(error, 'Error al intentar guardar el reporte de merma.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error en Registro',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } finally {
        isSaving.value = false
    }
}

const abrirModalVerMerma = async (merma) => {
    modalModoMerma.value = 'ver'
    try {
        const idMerma = merma.id_merma || merma.id
        const data = await obtenerMerma(idMerma)
        mermaActiva.value = data
        formEdicion.value = {
            fecha_merma: data.fecha_merma || data.fecha,
            tipo_merma: data.tipo_merma || data.tipo,
            motivo: data.motivo || '',
            id_almacen: Number(data.id_almacen)
        }
        safeModalAction(detalleModalRef.value, 'show')
    } catch (error) {
        console.error('Error al ver merma:', error)
        const msg = extraerMensajeError(error, 'Error al cargar el detalle de la merma.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error al Consultar',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    }
}

const activarEdicionMerma = () => {
    modalModoMerma.value = 'editar'
}

const guardarEdicionMerma = async () => {
    if (isSaving.value) return
    isSaving.value = true
    try {
        const idMerma = mermaActiva.value.id_merma || mermaActiva.value.id
        await actualizarMerma(idMerma, {
            ...formEdicion.value,
            id_almacen: Number(formEdicion.value.id_almacen)
        })
        await cargarDatosDesdeServidor()
        modalModoMerma.value = 'ver'
        const data = await obtenerMerma(idMerma)
        mermaActiva.value = data

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Actualización Completa',
                mensaje: 'La merma ha sido actualizada correctamente.',
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } catch (error) {
        console.error('Error al actualizar merma:', error)
        const msg = extraerMensajeError(error, 'Error al actualizar la merma.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error al Actualizar',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } finally {
        isSaving.value = false
    }
}
</script>

<style scoped>
</style>