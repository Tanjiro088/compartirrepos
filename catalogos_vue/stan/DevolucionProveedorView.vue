<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Personalizado para Evitar Salida de Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Devoluciones a Proveedores</h3>

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-primary fw-bold" @click="abrirModalDevolucion" :disabled="isLoading || isSaving">
                            <i class="bi bi-plus-lg me-1"></i> Registrar Devolución
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                            :disabled="isLoading || isSaving"
                            @click="cargarDatosDesdeServidor"
                            title="Actualizar datos"
                        >
                            <span v-if="isLoading" class="spinner-border spinner-border-sm"></span>
                            <i v-else class="bi bi-arrow-clockwise"></i>
                            <span>Actualizar</span>
                        </button>
                    </div>
                    <div class="w-100 w-md-50" style="max-width: 400px;">
                        <input type="text" class="form-control" v-model="busquedaDevoluciones" :disabled="isLoading" placeholder="Buscar por folio, proveedor o almacén...">
                    </div>
                </div>

                <!-- SPINNER DE CARGA -->
                <div v-if="isLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Consultando devoluciones a proveedores...</p>
                </div>

                <!-- TABLA DE HISTORIAL DE DEVOLUCIONES -->
                <template v-else>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio Devolución</th>
                                    <th>Orden Compra (OC)</th>
                                    <th>Proveedor</th>
                                    <th>Almacén / Sucursal</th>
                                    <th>Fecha Proceso</th>
                                    <th>Monto Devuelto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="dev in devolucionesFiltradas" :key="dev.id_merma || dev.id">
                                    <td class="fw-bold text-primary">{{ dev.folio || 'S/F' }}</td>
                                    <td class="fw-bold">{{ dev.folio_compra || dev.id_compra || 'N/A' }}</td>
                                    <td>{{ dev.proveedor || 'N/A' }}</td>
                                    <td>{{ dev.almacen_nombre || dev.almacen || 'N/A' }}</td>
                                    <td>{{ dev.fecha || dev.fecha_merma || '—' }}</td>
                                    <td class="fw-bold" :class="'text-status-' + dev.estado">$ {{ formatoMoneda(dev.monto || 0) }}</td>
                                    <td>
                                        <span class="badge" :class="getEstadoBadge(dev.estado)">
                                            {{ (dev.estado || '').toUpperCase() }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="devolucionesFiltradas.length === 0">
                                    <td colspan="7" class="text-muted py-4 fst-italic">
                                        {{ busquedaDevoluciones ? 'No se encontraron devoluciones con ese criterio.' : 'No hay registros de devoluciones procesadas.' }}
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

        <!-- MODAL: REGISTRAR NUEVA DEVOLUCIÓN -->
        <div class="modal fade" id="nuevaDevolucionModal" tabindex="-1" ref="devolucionModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content shadow border-0">
                    <div class="modal-header bg-transparent text-primary border-bottom border-primary">
                        <h5 class="modal-title fw-bold">Registrar Devolución a Proveedor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="isSaving" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 mb-3 text-center fw-bold fs-5">
                            Folio: {{ folioGenerado || 'Generando...' }}
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" v-model="formDevolucion.fecha_merma" :disabled="isSaving">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Almacén / Sucursal <span class="text-danger">*</span></label>
                                <select class="form-select fw-semibold" v-model.number="formDevolucion.id_almacen" :disabled="isSaving" required>
                                    <option value="" disabled>-- Seleccione un almacén activo --</option>
                                    <option v-for="alm in almacenes" :key="alm.id_almacen || alm.id" :value="Number(alm.id_almacen || alm.id)">
                                        {{ alm.nombre }} ({{ alm.sucursal_nombre || 'Sucursal' }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Seleccionar Compra de Origen (OC) <span class="text-danger">*</span></label>
                                <select class="form-select" v-model="formDevolucion.id_compra" @change="cargarMontoSugerido" :disabled="isSaving">
                                    <option value="">Seleccione una orden de compra...</option>
                                    <option v-for="compra in comprasDisponibles" :key="compra.id_compra || compra.id" :value="Number(compra.id_compra || compra.id)">
                                        {{ compra.folio }} - {{ compra.proveedor }} ($ {{ formatoMoneda(compra.total) }})
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Monto a Devolver / Nota de Crédito ($) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control text-danger fw-bold" v-model.number="formDevolucion.monto" min="0.01" step="0.01" placeholder="0.00" :disabled="isSaving">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">Motivo Detallado de la Devolución <span class="text-danger">*</span></label>
                            <textarea class="form-control" rows="3" v-model="formDevolucion.motivo" :disabled="isSaving" placeholder="Ej. Defecto de empaque, lote dañado de fábrica, productos vencidos al recibir..."></textarea>
                        </div>

                        <div class="alert alert-info border-0 rounded shadow-sm mb-0" style="background-color: #e3f2fd; color: #0d47a1;">
                            <strong>Nota Contable:</strong> Al confirmar la devolución, se emitirá una <strong>Nota de Crédito</strong> automática, reduciendo el saldo pendiente en <strong>Cuentas por Pagar</strong> (HU-MER-005).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="isSaving">Cancelar y Volver</button>
                        <button 
                            class="btn btn-primary fw-bold d-inline-flex align-items-center gap-2"
                            :disabled="!formDevolucion.id_compra || !formDevolucion.monto || !formDevolucion.motivo || !formDevolucion.id_almacen || isSaving"
                            @click="procesarDevolucionDefinitiva"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>{{ isSaving ? 'Procesando...' : 'Procesar Devolución' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import { useGlobalStore } from '@/stores/store.js'

import {
    obtenerHistorialDevoluciones,
    obtenerComprasParaDevolucion,
    registrarDevolucionProveedor
} from '@/services/devolucionesService'
import { obtenerSiguienteFolioMerma } from '@/services/mermasService'

const store = useGlobalStore()
const url = computed(() => {
    const base = store.baseUrl || ''
    return base.endsWith('/') ? base : `${base}/`
})

const devolucionModalRef = ref(null)
const modalConfirm = ref(null)
const folioGenerado = ref('')

const historialDevoluciones = ref([])
const comprasDisponibles = ref([])
const almacenes = ref([])
const busquedaDevoluciones = ref('')

const isLoading = ref(false)
const isSaving = ref(false)

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value) || 1)

const formDevolucion = ref({
    id_compra: '',
    id_almacen: '',
    id_usuario: 1,
    fecha_merma: new Date().toISOString().slice(0, 10),
    monto: null,
    motivo: ''
})

// ------------------------------
// UTILIDADES Y FORMATEO
// ------------------------------
const formatoMoneda = (valor) => {
    return Number(valor || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

// Extrae mensajes amigables y de validación 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error al registrar la devolución en el servidor.') => {
    if (error?.response?.data) {
        const data = error.response.data
        if (data.errors && typeof data.errors === 'object') {
            const primerCampo = Object.keys(data.errors)[0]
            if (primerCampo && Array.isArray(data.errors[primerCampo]) && data.errors[primerCampo].length) {
                return data.errors[primerCampo][0]
            }
        }
        return data.Mensaje || data.message || data.error || mensajeDefecto
    }
    return error?.message || mensajeDefecto
}

// ------------------------------
// CARGA DE ALMACENES FILTRANDO ACTIVOS
// ------------------------------
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
        console.error("Error al cargar almacenes activos en devoluciones:", error)
    }
}

// ------------------------------
// CICLO DE VIDA
// ------------------------------
onMounted(async () => {
    isLoading.value = true
    await Promise.all([cargarAlmacenes(), cargarDatosDesdeServidor()])
    isLoading.value = false
})

onBeforeUnmount(() => {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove())
    document.body.classList.remove('modal-open')
})

const cargarDatosDesdeServidor = async () => {
    try {
        const respuesta = await obtenerHistorialDevoluciones({ pagina: pagina.value, por_pagina: porPagina.value })
        historialDevoluciones.value = Array.isArray(respuesta) ? respuesta : (respuesta.mermas || respuesta.data || respuesta.resultados || [])
        totalRegistros.value = respuesta.total || historialDevoluciones.value.length
        
        const compras = await obtenerComprasParaDevolucion()
        comprasDisponibles.value = Array.isArray(compras) ? compras : (compras.data || compras.resultados || [])
    } catch (error) {
        console.error('Error al cargar datos de devoluciones:', error)
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error de Red',
                mensaje: 'No se pudo conectar con el servidor para consultar las devoluciones.',
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    }
}

const cambiarPagina = async (nuevaPagina) => {
    pagina.value = nuevaPagina
    isLoading.value = true
    await cargarDatosDesdeServidor()
    isLoading.value = false
}

const getEstadoBadge = (estado) => {
    const est = String(estado || '').toLowerCase()
    const clases = { registrada: 'bg-secondary', aprobada: 'bg-success', rechazada: 'bg-danger' }
    return clases[est] || 'bg-light text-dark'
}

const devolucionesFiltradas = computed(() => {
    const lista = Array.isArray(historialDevoluciones.value) ? historialDevoluciones.value : []
    if (!busquedaDevoluciones.value) return lista
    const q = busquedaDevoluciones.value.toLowerCase().trim()
    return lista.filter(d =>
        String(d?.folio || '').toLowerCase().includes(q) ||
        String(d?.almacen || '').toLowerCase().includes(q) ||
        String(d?.almacen_nombre || '').toLowerCase().includes(q) ||
        String(d?.proveedor || '').toLowerCase().includes(q)
    )
})

const abrirModalDevolucion = async () => {
    await cargarAlmacenes()

    const primerAlmacen = almacenes.value[0]
    const idPrimerAlmacen = primerAlmacen ? Number(primerAlmacen.id_almacen || primerAlmacen.id) : ''

    formDevolucion.value = {
        id_compra: '',
        id_almacen: idPrimerAlmacen,
        id_usuario: 1,
        fecha_merma: new Date().toISOString().slice(0, 10),
        monto: null,
        motivo: ''
    }
    folioGenerado.value = ''
    Modal.getOrCreateInstance(devolucionModalRef.value).show()
    try { 
        folioGenerado.value = await obtenerSiguienteFolioMerma('devolucion_proveedor') 
    } catch { 
        folioGenerado.value = 'DEV-' + Date.now().toString().slice(-6) 
    }
}

const cargarMontoSugerido = () => {
    const compraSeleccionada = comprasDisponibles.value.find(c => Number(c.id_compra || c.id) === Number(formDevolucion.value.id_compra))
    if (compraSeleccionada) {
        formDevolucion.value.monto = Number(compraSeleccionada.total || 0)
        if (compraSeleccionada.id_almacen) {
            const existeEnActivos = almacenes.value.some(a => Number(a.id_almacen || a.id) === Number(compraSeleccionada.id_almacen))
            if (existeEnActivos) {
                formDevolucion.value.id_almacen = Number(compraSeleccionada.id_almacen)
            }
        }
    }
}

const procesarDevolucionDefinitiva = async () => {
    if (isSaving.value) return

    if (!formDevolucion.value.id_compra) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Compra Requerida',
                mensaje: 'Debe seleccionar una orden de compra para efectuar la devolución.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }

    if (!formDevolucion.value.id_almacen) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Campo Requerido',
                mensaje: 'Debe seleccionar un almacén válido para la salida de inventario.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }

    const montoNum = Number(formDevolucion.value.monto) || 0
    if (montoNum <= 0) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Monto Inválido',
                mensaje: 'El importe monetario de la devolución debe ser mayor a $0.00.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }

    if (!String(formDevolucion.value.motivo || '').trim()) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Motivo Requerido',
                mensaje: 'Debe ingresar el motivo o justificación de la devolución.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }

    if (modalConfirm.value) {
        const confirmado = await modalConfirm.value.open({
            titulo: 'Confirmar Devolución',
            mensaje: `¿Desea registrar esta devolución por $${formatoMoneda(montoNum)} al proveedor?\n\nEsta acción registrará la salida del almacén y actualizará el inventario.`,
            tipo: 'warning',
            mostrarCancelar: true,
            textoConfirmar: 'Sí, Registrar Devolución',
            textoCancelar: 'Cancelar'
        })
        if (!confirmado) return
    }

    isSaving.value = true
    try {
        const payload = {
            id_compra: Number(formDevolucion.value.id_compra),
            id_almacen: Number(formDevolucion.value.id_almacen),
            id_usuario: Number(formDevolucion.value.id_usuario) || 1,
            fecha_merma: formDevolucion.value.fecha_merma,
            motivo: String(formDevolucion.value.motivo).trim(),
            monto: montoNum,
            tipo_merma: 'devolucion_proveedor',
            detalles: []
        }

        await registrarDevolucionProveedor(payload)
        Modal.getInstance(devolucionModalRef.value)?.hide()

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Devolución Registrada',
                mensaje: 'La devolución al proveedor se ha registrado correctamente en el sistema.',
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }

        isLoading.value = true
        await cargarDatosDesdeServidor()
    } catch (error) {
        console.error('Error en devolución:', error)
        const msg = extraerMensajeError(error, 'Error al registrar la devolución en el servidor.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error en Devolución',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } finally {
        isSaving.value = false
        isLoading.value = false
    }
}
</script>

<style scoped>
</style>
