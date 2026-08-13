<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Cuentas por Pagar</h3>

                <!-- Resumen Financiero -->
                <div class="row mb-4 text-center">
                    <div class="col-md-6">
                        <div class="card border-warning shadow-sm">
                            <div class="card-body py-3">
                                <small class="text-muted text-uppercase fw-bold">Total Adeudo</small>
                                <h4 class="fw-bold text-warning mb-0">$ {{$formatCurrency(totalAdeudoGlobal) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-danger shadow-sm">
                            <div class="card-body py-3">
                                <small class="text-muted text-uppercase fw-bold">Total Vencido</small>
                                <h4 class="fw-bold text-danger mb-0">$ {{$formatCurrency(totalVencidoGlobal) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barra de filtros -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="w-25">
                        <select class="form-select" v-model="filtroEstado">
                            <option value="todos">Todos los estados</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="parcial">Parciales</option>
                            <option value="vencido">Vencidos</option>
                            <option value="pagado">Pagados</option>
                        </select>
                    </div>
                    <div class="w-50">
                        <input type="text" class="form-control" v-model="busquedaCuentas" placeholder="Buscar por proveedor o folio...">
                    </div>
                </div>

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
                            <tr v-for="cuenta in cuentasFiltradas" :key="cuenta.id_cuenta_pagar" :class="{'table-danger': cuenta.estado === 'vencido'}">
                                <td class="fw-bold">{{ cuenta.folio_compra }}</td>
                                <td>{{ cuenta.proveedor }}</td>
                                <td>
                                    {{ cuenta.fecha_vencimiento }}
                                    <small v-if="cuenta.estado === 'vencido'" class="text-danger fw-bold d-block">(Vencido)</small>
                                </td>
                                <td>$ {{$formatCurrency(cuenta.monto_total) }}</td>
                                <td class="fw-bold" :class="'text-status-' + cuenta.estado">$ {{ $formatCurrency(cuenta.saldo_pendiente) }}</td>
                                <td>
                                    <span class="badge" :class="getEstadoBadgeClass(cuenta.estado)">
                                        {{ cuenta.estado.toUpperCase() }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-success fw-bold"
                                            :disabled="cuenta.estado === 'pagado'"
                                            @click="abrirModalPago(cuenta)">
                                        $ Abonar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="cuentasFiltradas.length === 0">
                                <td colspan="7" class="text-muted py-3">{{ busquedaCuentas || filtroEstado !== 'todos' ? 'No se encontraron cuentas con los filtros aplicados.' : 'No se encontraron cuentas por pagar.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Paginador
                  :pagina-actual="pagina"
                  :total-paginas="totalPaginas"
                  @cambiar="cambiarPagina"
                />
              </div>
            </div>

            <!-- MODAL: REGISTRAR ABONO MULTI-MÉTODO -->
        <div class="modal fade" id="pagoModal" tabindex="-1" ref="pagoModalRef" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-transparent text-success border-bottom border-success">
                        <h5 class="modal-title fw-bold">Registrar Pago a Proveedor</h5>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-3 border-0 rounded shadow-sm" style="background-color: #e3f2fd; color: #0d47a1;">
                            <strong>Proveedor:</strong> {{ cuentaActiva.proveedor }}<br>
                            <strong>Folio:</strong> {{ cuentaActiva.folio_compra }}<br>
                            <span class="fs-5 fw-bold mt-1 d-block">Saldo Pendiente: $ {{ cuentaActiva.saldo_pendiente ? $formatCurrency(cuentaActiva.saldo_pendiente) : '0.00' }}</span>
                        </div>
                        
                        <h6 class="fw-bold text-dark mb-3 mt-4">Distribución del Abono</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Abono en Efectivo ($)</label>
                                <input type="number" class="form-control text-success fw-bold" v-model.number="formPago.efectivo" min="0" placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Abono en Tarjeta ($)</label>
                                <input type="number" class="form-control text-info fw-bold" v-model.number="formPago.tarjeta" min="0" placeholder="0.00">
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-light rounded border border-light text-end">
                            <span class="text-muted small d-block">Suma total a abonar:</span>
                            <span class="fs-4 fw-bold text-dark">$ {{$formatCurrency(totalMontoAbono) }}</span>
                        </div>

                        <div class="mt-3">
                            <span v-if="esAbonoValido" class="badge bg-success p-2 w-100 fs-7">
                                ✔ Monto válido para procesar el abono parcial.
                            </span>
                            <span v-else-if="totalMontoAbono > cuentaActiva.saldo_pendiente" class="badge bg-danger p-2 w-100 fs-7">
                                ⚠️ Error: El abono supera al saldo pendiente de la cuenta.
                            </span>
                            <span v-else-if="totalMontoAbono === 0" class="badge bg-secondary p-2 w-100 fs-7">
                                Ingrese una cantidad en efectivo o tarjeta para abonar.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-outline-success fw-bold" 
                                :disabled="!esAbonoValido"
                                @click="confirmarPago">
                            Confirmar Abono
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import {
    obtenerHistorialCuentas, 
    ordenarCuentasPorPrioridad, 
    getEstadoBadgeClass, 
    validarDistribucionAbono,
    registrarAbonoCuenta
} from '../services/cuentasService';

// ============================================================================
// VARIABLES REACTIVAS
// ============================================================================
const pagoModalRef = ref(null);
const filtroEstado = ref('todos');
const busquedaCuentas = ref('');

const historialCuentas = ref([])
const cuentaActiva = ref({})
const formPago = ref({ efectivo: 0, tarjeta: 0 })

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalAdeudo = ref(0)
const totalVencido = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value))

// ============================================================================
// CICLO DE VIDA (CONEXIÓN ASÍNCRONA A LA API)
// ============================================================================

onMounted(async () => {
    await cargarCuentasDesdeServidor();
});

/**
 * Función: cargarCuentasDesdeServidor
 * Propósito: Descargar cuentas desde Laravel y aplicar ordenación por criticidad.
 */
const cargarCuentasDesdeServidor = async () => {
    try {
        const respuesta = await obtenerHistorialCuentas({ pagina: pagina.value, por_pagina: porPagina.value })
        const cuentas = Array.isArray(respuesta) ? respuesta : (respuesta.cuentas || [])
        historialCuentas.value = ordenarCuentasPorPrioridad(cuentas)
        totalRegistros.value = respuesta.total || cuentas.length
        totalAdeudo.value = respuesta.total_adeudo ?? 0
        totalVencido.value = respuesta.total_vencido ?? 0
    } catch (error) {
        console.error('Error cuentas:', error)
        const msg = error?.response?.data?.message
            || error?.response?.data?.Mensaje
            || error?.response?.statusText
            || error?.message
            || 'Error desconocido.'
        alert('Error al cargar cuentas: ' + msg)
    }
}

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    cargarCuentasDesdeServidor()
}

// ============================================================================
// PROPIEDADES COMPUTADAS
// ============================================================================

const totalAdeudoGlobal = computed(() => totalAdeudo.value);

const totalVencidoGlobal = computed(() => totalVencido.value);

const totalMontoAbono = computed(() => {
    return parseFloat(formPago.value.efectivo || 0) + parseFloat(formPago.value.tarjeta || 0);
});

const esAbonoValido = computed(() => {
    return validarDistribucionAbono(
        formPago.value.efectivo,
        formPago.value.tarjeta,
        cuentaActiva.value.saldo_pendiente
    );
});

const cuentasFiltradas = computed(() => {
    let resultado = historialCuentas.value;
    if (filtroEstado.value !== 'todos') {
        resultado = resultado.filter(c => c.estado === filtroEstado.value);
    }
    if (busquedaCuentas.value) {
        const q = busquedaCuentas.value.toLowerCase();
        resultado = resultado.filter(c =>
            (c.proveedor && c.proveedor.toLowerCase().includes(q)) ||
            (c.folio_compra && c.folio_compra.toLowerCase().includes(q))
        );
    }
    return resultado;
});

// ============================================================================
// MÉTODOS DE LA INTERFAZ
// ============================================================================

const abrirModalPago = (cuenta) => {
    cuentaActiva.value = cuenta;
    formPago.value = { efectivo: 0, tarjeta: 0 }; 
    Modal.getOrCreateInstance(pagoModalRef.value).show();
};

/**
 * Método: confirmarPago
 * Propósito: Transmitir el abono combinado mediante Axios al servidor. Si es exitoso,
 *            sincroniza las tablas del Frontend descargando la información actualizada de PostgreSQL.
 */
const confirmarPago = async () => {
    try {
        const abonoTotal = totalMontoAbono.value;
        
        const payloadPago = {
            id_usuario: 4, // Mock: Carlos Ruiz - Finanzas
            efectivo: formPago.value.efectivo,
            tarjeta: formPago.value.tarjeta,
            fecha_pago: new Date().toISOString().slice(0, 10)
        };

        await registrarAbonoCuenta(cuentaActiva.value.id_cuenta_pagar, payloadPago);
        
        await cargarCuentasDesdeServidor();
        Modal.getInstance(pagoModalRef.value)?.hide();
    } catch (error) {
        console.error(error);
        const msg = error?.response?.data?.Mensaje
            || error?.response?.data?.Error
            || error?.response?.data?.message
            || 'Error de conexión con el servidor.'
        alert(msg)
    }
};
</script>

<style scoped>
</style>
