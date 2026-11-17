<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Personalizado para Evitar Salida de Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h3 class="fw-bold text-dark mb-0">Módulo de Cuentas por Pagar</h3>
                    <button 
                        type="button" 
                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                        :disabled="isLoading || isSaving"
                        @click="cargarCuentasDesdeServidor"
                        title="Actualizar cuentas"
                    >
                        <span v-if="isLoading" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="bi bi-arrow-clockwise"></i>
                        <span>Actualizar</span>
                    </button>
                </div>

                <!-- Resumen Financiero -->
                <div class="row mb-4 text-center g-3">
                    <div class="col-md-6">
                        <div class="card border-warning shadow-sm h-100">
                            <div class="card-body py-3">
                                <small class="text-muted text-uppercase fw-bold">Total Adeudo</small>
                                <h4 class="fw-bold text-warning mb-0">$ {{ formatoMoneda(totalAdeudoGlobal) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-danger shadow-sm h-100">
                            <div class="card-body py-3">
                                <small class="text-muted text-uppercase fw-bold">Total Vencido</small>
                                <h4 class="fw-bold text-danger mb-0">$ {{ formatoMoneda(totalVencidoGlobal) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barra de filtros -->
                <div class="row g-3 align-items-center mb-3">
                    <div class="col-md-4">
                        <select class="form-select" v-model="filtroEstado" :disabled="isLoading">
                            <option value="todos">Todos los estados</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="parcial">Parciales</option>
                            <option value="vencido">Vencidos</option>
                            <option value="pagado">Pagados</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <input 
                            type="text" 
                            class="form-control" 
                            v-model="busquedaCuentas" 
                            :disabled="isLoading"
                            placeholder="Buscar por proveedor o folio..."
                        />
                    </div>
                </div>

                <!-- SPINNER DE CARGA -->
                <div v-if="isLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Consultando cuentas por pagar...</p>
                </div>

                <!-- TABLA DE CUENTAS -->
                <template v-else>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th>Folio Compra</th>
                                    <th>Proveedor</th>
                                    <th>Vencimiento</th>
                                    <th>Monto Total</th>
                                    <th>Saldo Pendiente</th>
                                    <th>Estado</th>
                                    <th>Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="cuenta in cuentasFiltradas" 
                                    :key="cuenta.id_cuenta_pagar || cuenta.id" 
                                    :class="{'table-danger': String(cuenta.estado).toLowerCase() === 'vencido'}"
                                >
                                    <td class="fw-bold">{{ cuenta.folio_compra || cuenta.folio || 'S/F' }}</td>
                                    <td>{{ cuenta.proveedor || 'Sin proveedor' }}</td>
                                    <td>
                                        {{ cuenta.fecha_vencimiento || '—' }}
                                        <small v-if="String(cuenta.estado).toLowerCase() === 'vencido'" class="text-danger fw-bold d-block">(Vencido)</small>
                                    </td>
                                    <td>$ {{ formatoMoneda(cuenta.monto_total) }}</td>
                                    <td class="fw-bold" :class="'text-status-' + cuenta.estado">$ {{ formatoMoneda(cuenta.saldo_pendiente) }}</td>
                                    <td>
                                        <span class="badge" :class="getEstadoBadgeClass(cuenta.estado)">
                                            {{ (cuenta.estado || '').toUpperCase() }}
                                        </span>
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-sm btn-outline-success fw-bold"
                                            :disabled="String(cuenta.estado).toLowerCase() === 'pagado' || Number(cuenta.saldo_pendiente) <= 0"
                                            @click="abrirModalPago(cuenta)"
                                        >
                                            $ Abonar
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="cuentasFiltradas.length === 0">
                                    <td colspan="7" class="text-muted py-4 fst-italic">
                                        {{ busquedaCuentas || filtroEstado !== 'todos' ? 'No se encontraron cuentas con los filtros aplicados.' : 'No se encontraron cuentas por pagar.' }}
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

        <!-- MODAL: REGISTRAR ABONO MULTI-MÉTODO -->
        <div class="modal fade" id="pagoModal" tabindex="-1" ref="pagoModalRef" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content shadow border-0">
                    <div class="modal-header bg-transparent text-success border-bottom border-success">
                        <h5 class="modal-title fw-bold">Registrar Pago a Proveedor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="isSaving" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-3 border-0 rounded shadow-sm" style="background-color: #e3f2fd; color: #0d47a1;">
                            <strong>Proveedor:</strong> {{ cuentaActiva.proveedor || 'No especificado' }}<br>
                            <strong>Folio:</strong> {{ cuentaActiva.folio_compra || cuentaActiva.folio || 'S/F' }}<br>
                            <span class="fs-5 fw-bold mt-1 d-block">Saldo Pendiente: $ {{ formatoMoneda(cuentaActiva.saldo_pendiente) }}</span>
                        </div>
                        
                        <h6 class="fw-bold text-dark mb-3 mt-4">Distribución del Abono</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Abono en Efectivo ($)</label>
                                <input 
                                    type="number" 
                                    class="form-control text-success fw-bold" 
                                    v-model.number="formPago.efectivo" 
                                    :disabled="isSaving"
                                    min="0" 
                                    step="0.01"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Abono en Tarjeta ($)</label>
                                <input 
                                    type="number" 
                                    class="form-control text-info fw-bold" 
                                    v-model.number="formPago.tarjeta" 
                                    :disabled="isSaving"
                                    min="0" 
                                    step="0.01"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-light rounded border border-light text-end">
                            <span class="text-muted small d-block">Suma total a abonar:</span>
                            <span class="fs-4 fw-bold text-dark">$ {{ formatoMoneda(totalMontoAbono) }}</span>
                        </div>

                        <div class="mt-3">
                            <span v-if="esAbonoValido" class="badge bg-success p-2 w-100 fs-7 text-wrap">
                                ✔ Monto válido para procesar el abono parcial.
                            </span>
                            <span v-else-if="totalMontoAbono > (Number(cuentaActiva.saldo_pendiente) || 0)" class="badge bg-danger p-2 w-100 fs-7 text-wrap">
                                ⚠️ Error: El abono supera al saldo pendiente de la cuenta.
                            </span>
                            <span v-else-if="totalMontoAbono === 0" class="badge bg-secondary p-2 w-100 fs-7 text-wrap">
                                Ingrese una cantidad en efectivo o tarjeta para abonar.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="isSaving">Cancelar</button>
                        <button 
                            class="btn btn-success fw-bold d-inline-flex align-items-center gap-2" 
                            :disabled="!esAbonoValido || isSaving"
                            @click="confirmarPago"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>{{ isSaving ? 'Procesando...' : 'Confirmar Abono' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import {
    obtenerHistorialCuentas, 
    ordenarCuentasPorPrioridad, 
    getEstadoBadgeClass, 
    validarDistribucionAbono,
    registrarAbonoCuenta
} from '@/services/cuentasService'

// ============================================================================
// VARIABLES REACTIVAS
// ============================================================================
const pagoModalRef = ref(null)
const modalConfirm = ref(null)

const filtroEstado = ref('todos')
const busquedaCuentas = ref('')

const historialCuentas = ref([])
const cuentaActiva = ref({})
const formPago = ref({ efectivo: 0, tarjeta: 0 })

const isLoading = ref(false)
const isSaving = ref(false)

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalAdeudo = ref(0)
const totalVencido = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value) || 1)

// ============================================================================
// UTILIDADES Y FORMATEO
// ============================================================================
const formatoMoneda = (valor) => {
    return Number(valor || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

// Extracción de mensajes amigables y errores 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error desconocido.') => {
    if (error?.response?.data) {
        const data = error.response.data
        if (data.errors && typeof data.errors === 'object') {
            const primerCampo = Object.keys(data.errors)[0]
            if (primerCampo && Array.isArray(data.errors[primerCampo]) && data.errors[primerCampo].length) {
                return data.errors[primerCampo][0]
            }
        }
        return data.Mensaje || data.message || data.Error || mensajeDefecto
    }
    return error?.message || mensajeDefecto
}

// ============================================================================
// CICLO DE VIDA (CONEXIÓN ASÍNCRONA A LA API)
// ============================================================================
onMounted(async () => {
    await cargarCuentasDesdeServidor()
})

onBeforeUnmount(() => {
    // Limpieza de posibles backdrops huérfanos de Bootstrap al salir de pantalla
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove())
    document.body.classList.remove('modal-open')
})

const cargarCuentasDesdeServidor = async () => {
    isLoading.value = true
    try {
        const respuesta = await obtenerHistorialCuentas({ pagina: pagina.value, por_pagina: porPagina.value })
        const cuentas = Array.isArray(respuesta) ? respuesta : (respuesta.cuentas || respuesta.resultados || respuesta.data || [])
        
        historialCuentas.value = typeof ordenarCuentasPorPrioridad === 'function' 
            ? ordenarCuentasPorPrioridad(cuentas) 
            : cuentas

        totalRegistros.value = respuesta.total || cuentas.length
        totalAdeudo.value = Number(respuesta.total_adeudo ?? 0)
        totalVencido.value = Number(respuesta.total_vencido ?? 0)
    } catch (error) {
        console.error('Error cuentas:', error)
        const msg = extraerMensajeError(error, 'No fue posible sincronizar las cuentas por pagar con el servidor.')
        
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error al cargar cuentas',
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

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    cargarCuentasDesdeServidor()
}

// ============================================================================
// PROPIEDADES COMPUTADAS
// ============================================================================
const totalAdeudoGlobal = computed(() => totalAdeudo.value)

const totalVencidoGlobal = computed(() => totalVencido.value)

const totalMontoAbono = computed(() => {
    const ef = parseFloat(formPago.value.efectivo) || 0
    const tj = parseFloat(formPago.value.tarjeta) || 0
    return Math.round((ef + tj) * 100) / 100
})

const esAbonoValido = computed(() => {
    if (typeof validarDistribucionAbono === 'function') {
        return validarDistribucionAbono(
            formPago.value.efectivo,
            formPago.value.tarjeta,
            cuentaActiva.value.saldo_pendiente
        )
    }
    const monto = totalMontoAbono.value
    const pendiente = Number(cuentaActiva.value.saldo_pendiente || 0)
    return monto > 0 && monto <= pendiente + 0.01
})

const cuentasFiltradas = computed(() => {
    const lista = Array.isArray(historialCuentas.value) ? historialCuentas.value : []
    const estado = filtroEstado.value.toLowerCase().trim()
    const q = busquedaCuentas.value.toLowerCase().trim()

    return lista.filter(c => {
        const estadoCuenta = String(c?.estado || '').toLowerCase().trim()
        const pasaEstado = estado === 'todos' || !estado || estadoCuenta === estado
        
        if (!q) return pasaEstado

        const matchProveedor = String(c?.proveedor || '').toLowerCase().includes(q)
        const matchFolio = String(c?.folio_compra || c?.folio || '').toLowerCase().includes(q)

        return pasaEstado && (matchProveedor || matchFolio)
    })
})

// ============================================================================
// MÉTODOS DE LA INTERFAZ
// ============================================================================
const abrirModalPago = (cuenta) => {
    cuentaActiva.value = cuenta || {}
    formPago.value = { efectivo: 0, tarjeta: 0 } 
    Modal.getOrCreateInstance(pagoModalRef.value).show()
}

const confirmarPago = async () => {
    if (isSaving.value || !esAbonoValido.value) return

    const abonoTxt = formatoMoneda(totalMontoAbono.value)
    const saldoPendienteActual = Number(cuentaActiva.value.saldo_pendiente || 0)
    const nuevoSaldo = Math.max(0, saldoPendienteActual - totalMontoAbono.value)

    if (modalConfirm.value) {
        const confirmado = await modalConfirm.value.open({
            titulo: 'Confirmar Abono a Proveedor',
            mensaje: `¿Deseas registrar este abono a la cuenta de ${cuentaActiva.value.proveedor || 'Proveedor'}?\n\n• Monto Abonado: $${abonoTxt}\n• Saldo Restante: $${formatoMoneda(nuevoSaldo)}`,
            tipo: 'warning',
            mostrarCancelar: true,
            textoConfirmar: 'Sí, Registrar Abono',
            textoCancelar: 'Cancelar'
        })
        if (!confirmado) return
    }

    isSaving.value = true
    try {
        const payloadPago = {
            id_usuario: 1,
            efectivo: Number(formPago.value.efectivo) || 0,
            tarjeta: Number(formPago.value.tarjeta) || 0,
            monto: totalMontoAbono.value,
            fecha_pago: new Date().toISOString().slice(0, 10)
        }

        const idCuenta = cuentaActiva.value.id_cuenta_pagar || cuentaActiva.value.id
        await registrarAbonoCuenta(idCuenta, payloadPago)
        Modal.getInstance(pagoModalRef.value)?.hide()

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Abono Registrado',
                mensaje: `El abono de $${abonoTxt} se procesó correctamente y se actualizó el saldo.`,
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
        await cargarCuentasDesdeServidor()
    } catch (error) {
        console.error('Error al registrar abono:', error)
        const msg = extraerMensajeError(error, 'Error de conexión con el servidor al registrar el pago.')
        
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error en la Operación',
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