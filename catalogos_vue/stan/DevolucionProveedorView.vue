<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Devoluciones a Proveedores</h3>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <button class="btn btn-outline-primary fw-bold" @click="abrirModalDevolucion">
                            + Registrar Devolución
                        </button>
                    </div>
                    <div class="w-50">
                        <input type="text" class="form-control" v-model="busquedaDevoluciones" placeholder="Buscar por folio o proveedor...">
                    </div>
                </div>

                <!-- TABLA DE HISTORIAL DE DEVOLUCIONES -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Folio Devolución</th>
                                <th>Orden Compra (OC)</th>
                                <th>Proveedor</th>
                                <th>Fecha Proceso</th>
                                <th>Monto Devuelto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="dev in devolucionesFiltradas" :key="dev.id_merma">
                                <td class="fw-bold text-primary">{{ dev.folio }}</td>
                                <td class="fw-bold">{{ dev.id_compra || 'N/A' }}</td>
                                <td>{{ dev.almacen || 'N/A' }}</td>
                                <td>{{ dev.fecha }}</td>
                                <td class="fw-bold" :class="'text-status-' + dev.estado">$ {{ $formatCurrency(dev.monto || 0) }}</td>
                                <td>
                                    <span class="badge" :class="getEstadoBadge(dev.estado)">
                                        {{ (dev.estado || '').toUpperCase() }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="devolucionesFiltradas.length === 0">
                                <td colspan="6" class="text-muted py-3">{{ busquedaDevoluciones ? 'No se encontraron devoluciones con ese criterio.' : 'No hay registros de devoluciones procesadas.' }}</td>
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

        <!-- MODAL: REGISTRAR NUEVA DEVOLUCIÓN -->
        <div class="modal fade" id="nuevaDevolucionModal" tabindex="-1" ref="devolucionModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-transparent text-primary border-bottom border-primary">
                        <h5 class="modal-title fw-bold">Registrar Devolución a Proveedor</h5>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 mb-3 text-center fw-bold fs-5">
                            Folio: {{ folioGenerado || 'Generando...' }}
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary">Fecha</label>
                                <input type="date" class="form-control" v-model="formDevolucion.fecha_merma">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-secondary">Almacén</label>
                                <select class="form-select" v-model="formDevolucion.id_almacen">
                                    <option value="">Seleccione...</option>
                                    <option v-for="a in almacenes" :key="a.id_almacen" :value="a.id_almacen">{{ a.nombre }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Seleccionar Compra de Origen (OC)</label>
                                <select class="form-select" v-model="formDevolucion.id_compra" @change="cargarMontoSugerido">
                                    <option value="">Seleccione una orden de compra...</option>
                                    <option v-for="compra in comprasDisponibles" :key="compra.id_compra" :value="compra.id_compra">
                                        {{ compra.folio }} - {{ compra.proveedor }} ($ {{$formatCurrency( compra.total) }})
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Monto a Devolver / Nota de Crédito ($)</label>
                                <input type="number" class="form-control text-danger fw-bold" v-model.number="formDevolucion.monto" min="0" placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">Motivo Detallado de la Devolución</label>
                            <textarea class="form-control" rows="3" v-model="formDevolucion.motivo" placeholder="Ej. Defecto de empaque, lote dañado de fábrica, productos vencidos al recibir..."></textarea>
                        </div>

                        <div class="alert alert-info border-0 rounded shadow-sm mb-0" style="background-color: #e3f2fd; color: #0d47a1;">
                            <strong>Nota Contable:</strong> Al confirmar la devolución, se emitirá una <strong>Nota de Crédito</strong> automática, reduciendo el saldo pendiente en <strong>Cuentas por Pagar</strong> (HU-MER-005).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar y Volver</button>
                        <button class="btn btn-outline-primary fw-bold"
                                :disabled="!formDevolucion.id_compra || !formDevolucion.monto || !formDevolucion.motivo"
                                @click="procesarDevolucionDefinitiva">
                            Procesar Devolución
                        </button>
                </div>

                <Paginador
                  :pagina-actual="pagina"
                  :total-paginas="totalPaginas"
                  @cambiar="cambiarPagina"
                />
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
    obtenerHistorialDevoluciones,
    obtenerComprasParaDevolucion,
    registrarDevolucionProveedor
} from '../services/devolucionesService'
import { obtenerSiguienteFolioMerma, obtenerAlmacenes } from '../services/mermasService'

const devolucionModalRef = ref(null)
const folioGenerado = ref('')

const historialDevoluciones = ref([])
const comprasDisponibles = ref([])
const almacenes = ref([])
const busquedaDevoluciones = ref('')

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value))
const formDevolucion = ref({
    id_compra: '',
    id_almacen: '1',
    id_usuario: 1,
    fecha_merma: new Date().toISOString().slice(0, 10),
    monto: null,
    motivo: ''
})

onMounted(async () => {
    await cargarDatosDesdeServidor()
})

const cargarDatosDesdeServidor = async () => {
    try {
        const respuesta = await obtenerHistorialDevoluciones({ pagina: pagina.value, por_pagina: porPagina.value })
        historialDevoluciones.value = Array.isArray(respuesta) ? respuesta : (respuesta.mermas || [])
        totalRegistros.value = respuesta.total || historialDevoluciones.value.length
        comprasDisponibles.value = await obtenerComprasParaDevolucion()
        if (almacenes.value.length === 0) {
            almacenes.value = await obtenerAlmacenes()
        }
    } catch (error) {
        console.error(error)
        alert('Error de red: No se pudo conectar con el servidor Laravel.')
    }
}

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    cargarDatosDesdeServidor()
}

const getEstadoBadge = (estado) => {
    const clases = { registrada: 'bg-secondary', aprobada: 'bg-success', rechazada: 'bg-danger' }
    return clases[estado] || 'bg-light text-dark'
}

const devolucionesFiltradas = computed(() => {
    if (!busquedaDevoluciones.value) return historialDevoluciones.value
    const q = busquedaDevoluciones.value.toLowerCase()
    return historialDevoluciones.value.filter(d =>
        (d.folio && d.folio.toLowerCase().includes(q)) ||
        (d.almacen && d.almacen.toLowerCase().includes(q))
    )
})

const abrirModalDevolucion = async () => {
    formDevolucion.value = {
        id_compra: '',
        id_almacen: almacenes.value[0]?.id_almacen ?? '1',
        id_usuario: 1,
        fecha_merma: new Date().toISOString().slice(0, 10),
        monto: null,
        motivo: ''
    }
    folioGenerado.value = ''
    Modal.getOrCreateInstance(devolucionModalRef.value).show()
    try { folioGenerado.value = await obtenerSiguienteFolioMerma('devolucion_proveedor') } catch { folioGenerado.value = 'DEV-XXXX-XXXX-XX' }
}

const cargarMontoSugerido = () => {
    const compraSeleccionada = comprasDisponibles.value.find(c => c.id_compra === formDevolucion.value.id_compra)
    if (compraSeleccionada) {
        formDevolucion.value.monto = compraSeleccionada.total
        formDevolucion.value.id_almacen = compraSeleccionada.id_almacen ?? '1'
    }
}

const procesarDevolucionDefinitiva = async () => {
    try {
        const payload = {
            id_compra: formDevolucion.value.id_compra,
            id_almacen: formDevolucion.value.id_almacen,
            id_usuario: formDevolucion.value.id_usuario,
            fecha_merma: formDevolucion.value.fecha_merma,
            motivo: formDevolucion.value.motivo,
            monto: formDevolucion.value.monto,
            detalles: []  // Se puede expandir para incluir productos específicos
        }

        await registrarDevolucionProveedor(payload)
        await cargarDatosDesdeServidor()
        Modal.getInstance(devolucionModalRef.value)?.hide()
    } catch (error) {
        console.error(error)
        alert('Error al intentar asentar el registro de devolución en el servidor.')
    }
}
</script>

<style scoped>
</style>

