<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Compras</h3>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <button class="btn btn-outline-primary fw-bold" @click="abrirModalNuevaCompra">
                            + Registrar Orden de Compra
                        </button>
                    </div>
                    <div class="w-50">
                        <input type="text" class="form-control" v-model="busquedaCompras" placeholder="Buscar por folio o proveedor...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Proveedor</th>
                                <th>Fecha Emisión</th>
                                <th>Monto Total</th>
                                <th>Estado</th>
                                <th>Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="compra in comprasFiltradas" :key="compra.id">
                                <td class="fw-bold">{{ compra.folio }}</td>
                                <td>{{ compra.proveedor }}</td>
                                <td>{{ compra.fecha }}</td>
                                <td class="fw-bold" :class="'text-status-' + compra.estado">$ {{ $formatCurrency(compra.total) }}</td>
                                <td>
                                    <span class="badge" :class="getBadgeClass(compra.estado)">
                                        {{ compra.estado.toUpperCase() }}
                                    </span>
                                </td>
                                <td>
                                    <button v-if="compra.estado === 'orden' || compra.estado === 'parcial'"
                                            class="btn btn-sm btn-outline-info me-2"
                                            @click="abrirModalRecepcion(compra)">
                                        Recibir
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" @click="abrirModalVerDetalle(compra)">
                                        Ver Detalle
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="comprasFiltradas.length === 0">
                                <td colspan="6" class="text-muted py-3">{{ busquedaCompras ? 'No se encontraron compras con ese criterio.' : 'No hay registros de órdenes de compra.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="isLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando órdenes de compra...</p>
                </div>

                <Paginador
                  :pagina-actual="pagina"
                  :total-paginas="totalPaginas"
                  @cambiar="cambiarPagina"
                />
              </div>
            </div>

        <!-- Modal: Registrar / Ver Detalle / Editar Compra -->
        <div class="modal fade" id="nuevaCompraModal" tabindex="-1" ref="nuevaCompraModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-transparent text-primary border-bottom border-primary">
                        <h5 class="modal-title fw-bold">
                            {{ modalModo === 'crear' ? 'Registrar Orden de Compra' : (modalModo === 'ver' ? 'Detalle de la Orden de Compra (Solo Lectura)' : 'Modificar Orden de Compra') }}
                        </h5>
                    </div>
                    <div class="modal-body p-4">

                        <div v-if="modalModo === 'crear'" class="alert alert-info py-2 mb-3 text-center fw-bold fs-5">
                            Folio: {{ folioGenerado || 'Generando...' }}
                        </div>
                        <div v-else class="alert alert-secondary py-2 mb-3 fw-bold fs-5">
                            Folio: {{ compraActiva.folio }}
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">1. Datos Generales (Maestro)</h5>
                        <div class="row mb-4">
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Proveedor <span class="text-danger">*</span></label>
                                <select class="form-select" :class="{'is-invalid': errors.id_proveedor}" v-model="formNueva.id_proveedor" @change="errors.id_proveedor=''" :disabled="modalModo === 'ver'">
                                    <option value="">Seleccione un proveedor...</option>
                                    <option v-for="prov in proveedores" :key="prov.id_proveedor" :value="prov.id_proveedor">{{ prov.nombre_comercial }}</option>
                                </select>
                                <div class="invalid-feedback">{{ errors.id_proveedor || 'Campo obligatorio.' }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Almacén <span class="text-danger">*</span></label>
                                <select class="form-select" :class="{'is-invalid': errors.id_almacen}" v-model.number="formNueva.id_almacen" @change="errors.id_almacen=''" :disabled="modalModo === 'ver'">
                                    <option value="">Seleccione un almacén...</option>
                                    <option v-for="a in almacenesCompras" :key="a.id_almacen" :value="a.id_almacen">{{ a.nombre }}</option>
                                </select>
                                <div class="invalid-feedback">{{ errors.id_almacen || 'Campo obligatorio.' }}</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">Fecha de Compra <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" :class="{'is-invalid': errors.fecha_compra}" v-model="formNueva.fecha_compra" :disabled="modalModo === 'ver'">
                                <div class="invalid-feedback">{{ errors.fecha_compra }}</div>
                            </div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">2. Productos (Detalle)</h5>

                        <div v-if="modalModo !== 'ver'" class="row mb-4 align-items-end bg-light p-3 rounded border shadow-sm">
                            <div class="col-md-8 position-relative">
                                <label class="form-label fw-bold text-primary">Buscar Artículo</label>
                                <input type="text" class="form-control"
                                       v-model="busquedaProducto"
                                       @input="filtrarProductos"
                                       @focus="abrirDropdownProductos"
                                       @blur="ocultarDropdownConRetraso"
                                       placeholder="Escriba para filtrar artículos...">
                                <ul class="dropdown-menu w-100 show shadow" v-if="mostrarDropdown && productosFiltrados.length">
                                    <li v-for="prod in productosFiltrados" :key="prod.id">
                                        <button class="dropdown-item py-2" type="button" @click.prevent="seleccionarProducto(prod)">
                                            {{ prod.nombre }} — <strong class="text-success">$ {{ $formatCurrency(prod.costo_promedio || 0) }}</strong>
                                        </button>
                                    </li>
                                </ul>
                                <div v-if="mostrarDropdown && busquedaProducto && !productosFiltrados.length" class="text-danger small position-absolute mt-1">
                                    No se encontraron artículos.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-primary w-100 fw-bold" @click="agregarProductoBuscado" :disabled="!productoSeleccionado">
                                    + Agregar Producto
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th width="12%">Cantidad <span class="text-danger">*</span></th>
                                        <th width="18%">Precio Unit. ($)</th>
                                        <th width="18%">Subtotal ($)</th>
                                        <th v-if="modalModo !== 'ver'" width="8%">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in detallesNueva" :key="index">
                                        <td class="text-start ps-3 fw-bold text-secondary">{{ item.producto }}</td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm text-center fw-bold"
                                                   :class="{'is-invalid': item.cantidadError}"
                                                   v-model.number="item.cantidad"
                                                   @input="validarCantidadItem(item)"
                                                   min="1" step="1" :disabled="modalModo === 'ver'">
                                            <div v-if="item.cantidadError" class="invalid-feedback d-block">{{ item.cantidadError }}</div>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm text-center" v-model.number="item.precio" min="0.01" step="0.01" :disabled="modalModo === 'ver'">
                                        </td>
                                        <td class="fw-bold text-dark">$ {{ $formatCurrency((item.cantidad || 0) * (item.precio || 0)) }}</td>
                                        <td v-if="modalModo !== 'ver'">
                                            <button class="btn btn-sm btn-outline-danger" @click="eliminarFilaCompra(index)">&times;</button>
                                        </td>
                                    </tr>
                                    <tr v-if="detallesNueva.length === 0">
                                        <td :colspan="modalModo === 'ver' ? 4 : 5" class="text-muted py-3">Ningún artículo agregado. Use el buscador superior para añadir productos.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sección de Pagos y Totales -->
                        <div class="row pt-3" v-if="detallesNueva.length > 0">
                            <div class="col-md-7 border-end border-light">
                                <h6 class="fw-bold text-dark mb-3">Distribución de Formas de Pago</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Efectivo ($)</label>
                                        <input type="number" class="form-control text-success fw-bold"
                                               :value="formNueva.pago_efectivo"
                                               @input="formNueva.pago_efectivo = Number($event.target.value) || 0"
                                               min="0" step="0.01" :disabled="modalModo === 'ver'">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Tarjeta ($)</label>
                                        <input type="number" class="form-control text-info fw-bold"
                                               :value="formNueva.pago_tarjeta"
                                               @input="formNueva.pago_tarjeta = Number($event.target.value) || 0"
                                               min="0" step="0.01" :disabled="modalModo === 'ver'">
                                    </div>
                                </div>

                                <div class="mt-3 p-3 bg-light rounded border">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark">Saldo a Crédito (Cuentas por Pagar):</span>
                                        <span class="fs-5 fw-bold" :class="creditoCalculado > 0 ? 'text-warning' : 'text-success'">
                                            $ {{$formatCurrency( creditoCalculado) }}
                                        </span>
                                    </div>
                                    <small v-if="creditoCalculado > 0" class="text-muted d-block mt-1">
                                        Este monto quedará pendiente y se reflejará en Cuentas por Pagar.
                                    </small>
                                </div>

                                <div class="mt-3">
                                    <span v-if="creditoCalculado > 0 && sumaPagosIntroducidos + creditoCalculado >= totales.total - 0.01" class="badge bg-info p-2 w-100 fs-6 text-dark">
                                        Pago parcial: se cubren ${{$formatCurrency( sumaPagosIntroducidos) }} ahora y ${{$formatCurrency( creditoCalculado) }} quedan a crédito.
                                    </span>
                                    <span v-else-if="creditoCalculado === 0 && sumaPagosIntroducidos >= totales.total - 0.01 && totales.total > 0" class="badge bg-success p-2 w-100 fs-6">
                                        Pago completo: el total ha sido cubierto sin saldo pendiente.
                                    </span>
                                    <span v-else-if="sumaPagosIntroducidos > totales.total" class="badge bg-danger p-2 w-100 fs-6">
                                        El pago excede el total por: $ {{ $formatCurrency(sumaPagosIntroducidos - totales.total) }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-5 bg-light p-3 rounded border">
                                <table class="table table-borderless table-sm text-end mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted text-start">Subtotal:</td>
                                            <td class="fw-bold text-dark">$ {{$formatCurrency( totales.subtotal) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted text-start">IVA (16%):</td>
                                            <td class="fw-bold text-dark">$ {{$formatCurrency( totales.impuesto) }}</td>
                                        </tr>
                                        <tr class="fs-5 border-top">
                                            <td class="fw-bold text-start pt-2">TOTAL:</td>
                                            <td class="fw-bold text-primary pt-2">$ {{$formatCurrency( totales.total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button v-if="modalModo === 'ver'" class="btn btn-outline-warning" @click="activarModoEdicion">
                            Habilitar Edición
                        </button>
                        <button v-else
                                class="btn btn-outline-success fw-bold"
                                @click="guardarNuevaCompra"
                                :disabled="detallesNueva.length === 0 || sumaPagosIntroducidos > totales.total || hayErrorCantidad">
                            {{ modalModo === 'crear' ? 'Guardar Orden de Compra' : 'Confirmar Cambios' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Recepción -->
        <div class="modal fade" id="recepcionModal" tabindex="-1" ref="recepcionModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-transparent text-info border-bottom border-info">
                        <h5 class="modal-title fw-bold">Recepción de Mercancía</h5>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-secondary mb-4 row">
                            <div class="col-md-3"><strong>Folio:</strong><br>{{ compraActiva.folio }}</div>
                            <div class="col-md-3"><strong>Proveedor:</strong><br>{{ compraActiva.proveedor }}</div>
                            <div class="col-md-3"><strong>Almacén:</strong><br>{{ compraActiva.almacen || nombreAlmacen(compraActiva.id_almacen) || '—' }}</div>
                            <div class="col-md-3"><strong>Fecha Esperada:</strong><br>{{ compraActiva.fecha_esperada || 'No especificada' }}</div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">Inspección de Productos</h5>
                        <table class="table table-bordered table-hover align-middle text-center mb-4">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad Pedida</th>
                                    <th width="18%">Cantidad Recibida</th>
                                    <th width="28%">Observaciones</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in detallesRecepcion" :key="index" :class="{'table-warning': item.recibida < item.pedida}">
                                    <td class="text-start ps-3">{{ item.nombre }}</td>
                                    <td class="fw-bold">{{ item.pedida }}</td>
                                    <td>
                                        <input type="number" class="form-control text-center fw-bold text-primary" v-model.number="item.recibida" min="0" :max="item.pedida">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" v-model="item.observacion" :placeholder="item.recibida < item.pedida ? 'Motivo del faltante...' : 'Todo en orden'">
                                    </td>
                                    <td>
                                        <span class="badge" :class="item.recibida >= item.pedida ? 'bg-success' : 'bg-warning text-dark'">
                                            {{ item.recibida >= item.pedida ? 'Completo' : 'Parcial' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="text-end fw-bold text-dark fs-6">
                            Estado Resultante:
                            <span class="badge ms-2" :class="estadoRecepcion === 'recibido' ? 'bg-success' : 'bg-warning text-dark'">
                                {{ estadoRecepcion.toUpperCase() }}
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-outline-info fw-bold" @click="guardarRecepcion">
                            Confirmar Recepción
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import { useLoading } from '../composables/useLoading.js'
import { useToast } from '../composables/useToast.js'
import {
    calcularTotalesCompra,
    determinarEstadoRecepcion,
    obtenerHistorialCompras,
    obtenerProductosDisponibles,
    validarDistribucionPagos,
    crearOrdenCompra,
    actualizarOrdenCompra,
    registrarRecepcionMercancia,
    obtenerSiguienteFolioCompra
} from '../services/comprasService'
import { obtenerProveedores } from '../services/proveedoresService'
import { obtenerAlmacenes } from '../services/mermasService'

const nuevaCompraModalRef = ref(null)
const recepcionModalRef = ref(null)

const historialCompras = ref([])
const productosMaestros = ref([])
const proveedores = ref([])
const almacenesCompras = ref([])
const busquedaCompras = ref('')

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value))

const modalModo = ref('crear')
const compraSeleccionadaId = ref(null)
const folioGenerado = ref('')

const formNueva = ref({
    id_proveedor: '',
    id_almacen: '',
    id_usuario: 2,
    fecha_compra: new Date().toISOString().split('T')[0],
    fecha_entrega: '',
    pago_efectivo: 0,
    pago_tarjeta: 0
})

const detallesNueva = ref([])
const compraActiva = ref({})
const detallesRecepcion = ref([])

const busquedaProducto = ref('')
const productoSeleccionado = ref(null)
const mostrarDropdown = ref(false)

const errors = ref({
    id_proveedor: '',
    id_almacen: '',
    fecha_compra: ''
})

const { isLoading, withLoading } = useLoading()
const { error: notifyError, success: notifySuccess } = useToast()

onMounted(async () => { await withLoading(cargarDatos) })

const cargarDatos = async () => {
    const data = await obtenerHistorialCompras({ pagina: pagina.value, por_pagina: porPagina.value })
    historialCompras.value = data.resultados
    totalRegistros.value = data.total
    productosMaestros.value = await obtenerProductosDisponibles()
    if (proveedores.value.length === 0) {
        try {
            const provData = await obtenerProveedores({ por_pagina: 100, activo: 1 })
            proveedores.value = provData.resultados || provData || []
        } catch { proveedores.value = [] }
    }
    await asegurarAlmacenes()
}

const asegurarAlmacenes = async (forzar = false) => {
    if (!forzar && almacenesCompras.value.length > 0) return
    try {
        almacenesCompras.value = await obtenerAlmacenes()
    } catch (error) {
        almacenesCompras.value = []
        const msg = error?.response?.data?.Mensaje || error?.message || 'Error al cargar almacenes.'
        notifyError(msg)
    }
}

const nombreAlmacen = (idAlmacen) => {
    const id = Number(idAlmacen)
    return almacenesCompras.value.find((a) => Number(a.id_almacen) === id)?.nombre || ''
}

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    withLoading(cargarDatos)
}

// --- Validaciones instantáneas ---

const validarCantidadItem = (item) => {
    if (!item.cantidad || item.cantidad < 1) {
        item.cantidadError = 'La cantidad debe ser al menos 1.'
    } else {
        item.cantidadError = ''
    }
}

// Watchers para validación instantánea de campos del formulario
watch(() => formNueva.value.id_proveedor, (v) => {
    if (!v) errors.value.id_proveedor = 'Seleccione un proveedor.'
    else errors.value.id_proveedor = ''
})

watch(() => formNueva.value.id_almacen, (v) => {
    if (!v) errors.value.id_almacen = 'Seleccione un almacén.'
    else errors.value.id_almacen = ''
})

watch(() => formNueva.value.fecha_compra, (v) => {
    if (!v) errors.value.fecha_compra = 'Seleccione una fecha.'
    else errors.value.fecha_compra = ''
})

// --- Computados ---

const productosFiltrados = computed(() => {
    const lista = Array.isArray(productosMaestros.value) ? productosMaestros.value : []
    if (!busquedaProducto.value) return lista
    return lista.filter(p =>
        p.nombre.toLowerCase().includes(busquedaProducto.value.toLowerCase())
    )
})

const totales = computed(() => calcularTotalesCompra(detallesNueva.value))

const sumaPagosIntroducidos = computed(() =>
    (parseFloat(formNueva.value.pago_efectivo) || 0) + (parseFloat(formNueva.value.pago_tarjeta) || 0)
)

const creditoCalculado = computed(() => {
    const falta = totales.value.total - sumaPagosIntroducidos.value
    return Math.max(0, Math.round(falta * 100) / 100)
})

const esPagoValido = computed(() =>
    validarDistribucionPagos(formNueva.value.pago_efectivo, formNueva.value.pago_tarjeta, creditoCalculado.value, totales.value.total)
)

const estadoRecepcion = computed(() => determinarEstadoRecepcion(detallesRecepcion.value))

const hayErrorCantidad = computed(() => detallesNueva.value.some(d => !d.cantidad || d.cantidad < 1))

const comprasFiltradas = computed(() => {
    if (!busquedaCompras.value) return historialCompras.value
    const q = busquedaCompras.value.toLowerCase()
    return historialCompras.value.filter(c =>
        c.folio.toLowerCase().includes(q) || c.proveedor.toLowerCase().includes(q)
    )
})

const getBadgeClass = (estado) => {
    const clases = { recibido: 'bg-success', parcial: 'bg-warning text-dark', orden: 'bg-secondary', pagado: 'bg-primary', pendiente: 'bg-secondary', cancelado: 'bg-danger' }
    return clases[estado] || 'bg-light text-dark'
}

// --- Métodos del buscador de productos (estilo v-select) ---

const abrirDropdownProductos = () => {
    mostrarDropdown.value = true
}

const filtrarProductos = () => {
    mostrarDropdown.value = true
}

const seleccionarProducto = (prod) => {
    productoSeleccionado.value = prod
    busquedaProducto.value = prod.nombre
    mostrarDropdown.value = false
}

const ocultarDropdownConRetraso = () => {
    setTimeout(() => { mostrarDropdown.value = false }, 200)
}

const agregarProductoBuscado = () => {
    if (!productoSeleccionado.value) return
    const prod = productoSeleccionado.value
    const idx = detallesNueva.value.findIndex(item => item.id_presentacion === prod.id)
    if (idx !== -1) {
        detallesNueva.value[idx].cantidad += 1
    } else {
        detallesNueva.value.push({
            id_presentacion: prod.id,
            producto: prod.nombre,
            cantidad: 1,
            precio: prod.costo_promedio || 0,
            descuento: 0,
            cantidadError: ''
        })
    }
    productoSeleccionado.value = null
    busquedaProducto.value = ''
}

const eliminarFilaCompra = (i) => { detallesNueva.value.splice(i, 1) }

// --- Autocompletar Folio ---

// --- Modal handlers ---

const abrirModalNuevaCompra = async () => {
    modalModo.value = 'crear'
    compraSeleccionadaId.value = null
    detallesNueva.value = []
    folioGenerado.value = ''
    Object.keys(errors.value).forEach(k => errors.value[k] = '')
    formNueva.value = {
        id_proveedor: '', id_almacen: '', id_usuario: 2,
        fecha_compra: new Date().toISOString().split('T')[0], fecha_entrega: '',
        pago_efectivo: 0, pago_tarjeta: 0
    }
    busquedaProducto.value = ''
    productoSeleccionado.value = null
    await asegurarAlmacenes(true)
    Modal.getOrCreateInstance(nuevaCompraModalRef.value).show()
    try { folioGenerado.value = await obtenerSiguienteFolioCompra() } catch { folioGenerado.value = 'OC-XXXX-XXXX-XX' }
}

const abrirModalVerDetalle = async (compra) => {
    modalModo.value = 'ver'
    compraSeleccionadaId.value = compra.id
    compraActiva.value = compra
    Object.keys(errors.value).forEach(k => errors.value[k] = '')
    formNueva.value = {
        id_proveedor: compra.id_proveedor,
        id_almacen: compra.id_almacen != null ? Number(compra.id_almacen) : '',
        id_usuario: 2,
        fecha_compra: compra.fecha, fecha_entrega: compra.fecha_esperada || '',
        pago_efectivo: Number(compra.monto_efectivo) || 0, pago_tarjeta: Number(compra.monto_tarjeta) || 0
    }
    detallesNueva.value = compra.detalles ? [...compra.detalles] : []
    await asegurarAlmacenes(true)
    Modal.getOrCreateInstance(nuevaCompraModalRef.value).show()
}

const activarModoEdicion = async () => {
    await asegurarAlmacenes(true)
    modalModo.value = 'editar'
}

const guardarNuevaCompra = async () => {
    if (!formNueva.value.id_proveedor || !formNueva.value.id_almacen || !formNueva.value.fecha_compra) {
        alert('Por favor, complete todos los campos obligatorios marcados en rojo.')
        return
    }
    if (detallesNueva.value.length === 0) {
        alert('Debe agregar al menos un producto al detalle.')
        return
    }
    if (hayErrorCantidad.value) {
        alert('Corrija las cantidades de los productos (deben ser al menos 1).')
        return
    }
    try {
        const payload = {
            id_proveedor: formNueva.value.id_proveedor,
            id_almacen: formNueva.value.id_almacen,
            id_usuario: formNueva.value.id_usuario,
            fecha_compra: formNueva.value.fecha_compra,
            fecha_entrega: formNueva.value.fecha_entrega || null,
            pago_efectivo: Number(formNueva.value.pago_efectivo) || 0,
            pago_tarjeta: Number(formNueva.value.pago_tarjeta) || 0,
            total: totales.value.total,
            detalles: detallesNueva.value.map(d => ({
                id_presentacion: d.id_presentacion,
                cantidad: Number(d.cantidad) || 1,
                precio: Number(d.precio) || 0,
                descuento: d.descuento || 0
            }))
        }
        console.log('Guardando compra:', JSON.stringify(payload, null, 2))
        if (modalModo.value === 'editar') {
            await actualizarOrdenCompra(compraSeleccionadaId.value, payload)
        } else {
            await crearOrdenCompra(payload)
        }
        await cargarDatos()
        Modal.getInstance(nuevaCompraModalRef.value)?.hide()
    } catch (error) {
        const msg = error?.response?.data?.Mensaje
            || error?.response?.data?.message
            || error?.message
            || 'Error desconocido.'
        notifyError('Error al guardar: ' + msg)
    }
}

const abrirModalRecepcion = (compra) => {
    compraActiva.value = compra
    detallesRecepcion.value = (compra.detalles || []).map(d => ({
        id_presentacion: d.id_presentacion,
        nombre: d.producto,
        pedida: d.cantidad,
        recibida: d.cantidad,
        observacion: ''
    }))
    Modal.getOrCreateInstance(recepcionModalRef.value).show()
}

const guardarRecepcion = async () => {
    try {
        await registrarRecepcionMercancia(compraActiva.value.id, detallesRecepcion.value)
        await cargarDatos()
        Modal.getInstance(recepcionModalRef.value)?.hide()
    } catch (error) {
        const msg = error?.response?.data?.Mensaje || error?.message || 'Error desconocido.'
        alert('Error al guardar recepción: ' + msg)
    }
}
</script>

<style scoped>
</style>

