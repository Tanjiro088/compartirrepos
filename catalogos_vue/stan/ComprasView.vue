<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Centralizado para Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Módulo de Compras</h3>

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <button 
                            class="btn btn-outline-primary fw-bold d-inline-flex align-items-center gap-2" 
                            @click="abrirModalNuevaCompra"
                            :disabled="isLoading || isSaving"
                            data-testid="btn-nueva-compra"
                        >
                            <i class="bi bi-plus-lg"></i>
                            <span>Registrar Orden de Compra</span>
                        </button>
                    </div>
                    <div class="w-100 w-md-50" style="max-width: 400px;">
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input 
                                type="text" 
                                class="form-control" 
                                v-model="busquedaCompras" 
                                placeholder="Buscar por folio o proveedor..."
                                :disabled="isLoading"
                            >
                        </div>
                    </div>
                </div>

                <!-- SPINNER DE CARGA PRINCIPAL -->
                <div v-if="isLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted mb-0">Cargando órdenes de compra e inventarios...</p>
                </div>

                <!-- TABLA PRINCIPAL -->
                <template v-else>
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
                                <tr v-for="compra in comprasFiltradas" :key="compra.id || compra.id_compra">
                                    <td class="fw-bold text-primary">{{ compra.folio || ('OC-' + (compra.id || compra.id_compra)) }}</td>
                                    <td class="text-start ps-3 fw-semibold text-dark">{{ compra.proveedor || compra.nombre_proveedor || 'Proveedor General' }}</td>
                                    <td>{{ compra.fecha || compra.fecha_compra || '—' }}</td>
                                    <td class="fw-bold text-dark">$ {{ formatoMoneda(compra.total) }}</td>
                                    <td>
                                        <span class="badge" :class="getBadgeClass(compra.estado)">
                                            {{ (compra.estado || 'orden').toUpperCase() }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex gap-1">
                                            <button 
                                                v-if="compra.estado === 'orden' || compra.estado === 'parcial'"
                                                class="btn btn-sm btn-outline-info d-inline-flex align-items-center gap-1"
                                                :disabled="isSaving"
                                                @click="abrirModalRecepcion(compra)"
                                                title="Ingresar mercancía física al almacén"
                                            >
                                                <i class="bi bi-box-arrow-in-down"></i>
                                                <span>Recibir</span>
                                            </button>
                                            <button 
                                                class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" 
                                                :disabled="isSaving"
                                                @click="abrirModalVerDetalle(compra)"
                                                title="Consultar desglose de la orden"
                                            >
                                                <i class="bi bi-eye"></i>
                                                <span>Ver Detalle</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="comprasFiltradas.length === 0">
                                    <td colspan="6" class="text-muted py-4 fst-italic">
                                        {{ busquedaCompras ? 'No se encontraron compras coincidentes con el criterio de búsqueda.' : 'No hay registros de órdenes de compra en el sistema.' }}
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

        <!-- MODAL: REGISTRAR / VER DETALLE / EDITAR COMPRA -->
        <div class="modal fade" id="nuevaCompraModal" tabindex="-1" ref="nuevaCompraModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content shadow border-0">
                    <div class="modal-header bg-light border-bottom">
                        <h5 class="modal-title fw-bold text-primary m-0">
                            {{ modalModo === 'crear' ? 'Registrar Orden de Compra' : (modalModo === 'ver' ? 'Detalle de la Orden de Compra (Solo Lectura)' : 'Modificar Orden de Compra') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="isSaving" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">

                        <div v-if="modalModo === 'crear'" class="alert alert-info py-2 mb-3 text-center fw-bold fs-5">
                            Folio Proyectado: {{ folioGenerado || 'Generando...' }}
                        </div>
                        <div v-else class="alert alert-secondary py-2 mb-3 fw-bold fs-5">
                            Folio: {{ compraActiva.folio || ('OC-' + (compraActiva.id || compraActiva.id_compra)) }}
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">1. Datos Generales (Maestro)</h5>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Proveedor <span class="text-danger">*</span></label>
                                <select 
                                    class="form-select" 
                                    :class="{'is-invalid': errors.id_proveedor}" 
                                    v-model="formNueva.id_proveedor" 
                                    @change="errors.id_proveedor=''" 
                                    :disabled="modalModo === 'ver' || isSaving"
                                    data-testid="select-proveedor"
                                >
                                    <option value="">Seleccione un proveedor...</option>
                                    <option 
                                        v-for="prov in proveedores" 
                                        :key="prov.id_proveedor || prov.id" 
                                        :value="prov.id_proveedor || prov.id"
                                    >
                                        {{ prov.razon_social || prov.nombre_comercial || prov.nombre }}
                                    </option>
                                </select>
                                <div class="invalid-feedback">{{ errors.id_proveedor || 'Campo obligatorio.' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Almacén de Entrada <span class="text-danger">*</span></label>
                                <select 
                                    class="form-select fw-semibold" 
                                    :class="{'is-invalid': errors.id_almacen}" 
                                    v-model.number="formNueva.id_almacen" 
                                    @change="errors.id_almacen=''" 
                                    :disabled="modalModo === 'ver' || isSaving"
                                    data-testid="select-almacen"
                                >
                                    <option value="" disabled>-- Seleccione un almacén activo --</option>
                                    <option 
                                        v-for="a in almacenesCompras" 
                                        :key="a.id_almacen || a.id" 
                                        :value="Number(a.id_almacen || a.id)"
                                    >
                                        {{ a.nombre }} ({{ a.sucursal_nombre || 'Sucursal' }})
                                    </option>
                                </select>
                                <div class="invalid-feedback">{{ errors.id_almacen || 'Campo obligatorio.' }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Fecha de Compra <span class="text-danger">*</span></label>
                                <input 
                                    type="date" 
                                    class="form-control" 
                                    :class="{'is-invalid': errors.fecha_compra}" 
                                    v-model="formNueva.fecha_compra" 
                                    @change="errors.fecha_compra=''"
                                    :disabled="modalModo === 'ver' || isSaving"
                                >
                                <div class="invalid-feedback">{{ errors.fecha_compra }}</div>
                            </div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">2. Productos (Detalle)</h5>

                        <div v-if="modalModo !== 'ver'" class="row mb-4 align-items-end bg-light p-3 rounded border shadow-sm">
                            <div class="col-md-8 position-relative">
                                <label class="form-label fw-bold text-primary">Buscar Artículo / Presentación</label>
                                <input 
                                    type="text" 
                                    class="form-control"
                                    v-model="busquedaProducto"
                                    @input="filtrarProductos"
                                    @focus="abrirDropdownProductos"
                                    @blur="ocultarDropdownConRetraso"
                                    :disabled="isSaving"
                                    placeholder="Escriba para filtrar artículos por nombre..."
                                >
                                <ul class="dropdown-menu w-100 show shadow" v-if="mostrarDropdown && productosFiltrados.length">
                                    <li v-for="prod in productosFiltrados" :key="prod.id_presentacion">
                                        <button class="dropdown-item py-2" type="button" @mousedown.prevent="seleccionarProducto(prod)">
                                            {{ prod.producto }} — {{ prod.tipo_presentacion || prod.presentacion || 'Presentación' }}
                                            (x{{ formatoNumero(prod.factor_conversion) }}) —
                                            <strong class="text-success">$ {{ formatoMoneda(prod.precio_compra) }}/u</strong>
                                        </button>
                                    </li>
                                </ul>
                                <div v-if="mostrarDropdown && busquedaProducto && !productosFiltrados.length" class="text-danger small position-absolute mt-1">
                                    No se encontraron artículos con esa descripción.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button 
                                    class="btn btn-primary w-100 fw-bold d-inline-flex align-items-center justify-content-center gap-2" 
                                    @click="agregarProductoBuscado" 
                                    :disabled="!productoSeleccionado || isSaving"
                                >
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Agregar al Detalle</span>
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered text-center align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th width="18%">Presentación</th>
                                        <th width="12%">Cantidad (pres.) <span class="text-danger">*</span></th>
                                        <th width="14%">Equivale (u)</th>
                                        <th width="16%">Precio Unit. ($/u)</th>
                                        <th width="16%">Subtotal ($)</th>
                                        <th v-if="modalModo !== 'ver'" width="8%">Quitar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, index) in detallesNueva" :key="index">
                                        <td class="text-start ps-3 fw-bold text-secondary">{{ item.producto }}</td>
                                        <td class="text-start">
                                            <span class="fw-semibold">{{ item.tipo_presentacion || presentacionDe(item)?.tipo_presentacion || item.presentacion || '—' }}</span>
                                            <small class="d-block text-muted">Total piezas: {{ formatoNumero(item.cantidad) }}</small>
                                        </td>
                                        <td>
                                            <input v-if="modalModo !== 'ver'"
                                                type="number" 
                                                class="form-control form-control-sm text-center fw-bold"
                                                :class="{'is-invalid': item.cantidadError}"
                                                v-model.number="item.cantidad_presentacion"
                                                @input="validarCantidadItem(item)"
                                                min="1" 
                                                step="1" 
                                                :disabled="isSaving"
                                            >
                                            <span v-else class="fw-bold">{{ item.cantidad_presentacion }}</span>
                                            <div v-if="item.cantidadError" class="invalid-feedback d-block">{{ item.cantidadError }}</div>
                                        </td>
                                        <!-- EQUIVALE (U) EDITABLE PARA AJUSTAR EL FACTOR DE PIEZAS POR CAJA -->
                                        <td>
                                            <input v-if="modalModo !== 'ver'"
                                                type="number" 
                                                class="form-control form-control-sm text-center font-monospace fw-bold"
                                                v-model.number="item.factor_conversion"
                                                @input="validarCantidadItem(item)"
                                                min="1"
                                                step="1"
                                                :disabled="isSaving"
                                            >
                                            <span v-else class="fw-bold font-monospace">{{ formatoNumero(item.factor_conversion) }}</span>
                                        </td>
                                        <td>
                                            <input 
                                                type="number" 
                                                class="form-control form-control-sm text-center font-monospace" 
                                                v-model.number="item.precio" 
                                                min="0.01" 
                                                step="0.01" 
                                                :disabled="modalModo === 'ver' || isSaving"
                                            >
                                        </td>
                                        <td class="fw-bold text-dark font-monospace">$ {{ formatoMoneda((item.cantidad || 0) * (item.precio || 0)) }}</td>
                                        <td v-if="modalModo !== 'ver'">
                                            <button 
                                                class="btn btn-sm btn-outline-danger" 
                                                :disabled="isSaving" 
                                                @click="eliminarFilaCompra(index)"
                                                title="Remover producto de la orden"
                                            >
                                                &times;
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="detallesNueva.length === 0">
                                        <td :colspan="modalModo === 'ver' ? 6 : 7" class="text-muted py-3 fst-italic">
                                            Ningún artículo agregado. Use el buscador superior para añadir productos.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- SECCIÓN DE PAGOS Y TOTALES -->
                        <div class="row pt-3" v-if="detallesNueva.length > 0">
                            <div class="col-md-7 border-end border-light">
                                <h6 class="fw-bold text-dark mb-3">Distribución de Formas de Pago</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Efectivo ($)</label>
                                        <input 
                                            type="number" 
                                            class="form-control text-success fw-bold font-monospace"
                                            :value="formNueva.pago_efectivo"
                                            @input="formNueva.pago_efectivo = Math.max(0, Number($event.target.value) || 0)"
                                            min="0" 
                                            step="0.01" 
                                            :disabled="modalModo === 'ver' || isSaving"
                                        >
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Tarjeta ($)</label>
                                        <input 
                                            type="number" 
                                            class="form-control text-info fw-bold font-monospace"
                                            :value="formNueva.pago_tarjeta"
                                            @input="formNueva.pago_tarjeta = Math.max(0, Number($event.target.value) || 0)"
                                            min="0" 
                                            step="0.01" 
                                            :disabled="modalModo === 'ver' || isSaving"
                                        >
                                    </div>
                                </div>

                                <div class="mt-3 p-3 bg-light rounded border">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark">Saldo a Crédito (Cuentas por Pagar):</span>
                                        <span class="fs-5 fw-bold font-monospace" :class="creditoCalculado > 0 ? 'text-warning' : 'text-success'">
                                            $ {{ formatoMoneda(creditoCalculado) }}
                                        </span>
                                    </div>
                                    <small v-if="creditoCalculado > 0" class="text-muted d-block mt-1">
                                        Este monto quedará pendiente y se reflejará en Cuentas por Pagar a favor del proveedor.
                                    </small>
                                </div>

                                <div class="mt-3">
                                    <span v-if="creditoCalculado > 0 && sumaPagosIntroducidos + creditoCalculado >= totales.total - 0.01" class="badge bg-info p-2 w-100 fs-6 text-dark text-wrap">
                                        Pago parcial: se cubren ${{ formatoMoneda(sumaPagosIntroducidos) }} ahora y${{ formatoMoneda(creditoCalculado) }} quedan a crédito.
                                    </span>
                                    <span v-else-if="creditoCalculado === 0 && sumaPagosIntroducidos >= totales.total - 0.01 && totales.total > 0" class="badge bg-success p-2 w-100 fs-6 text-wrap">
                                        Pago completo: el total de la orden ha sido cubierto en su totalidad.
                                    </span>
                                    <span v-else-if="sumaPagosIntroducidos > totales.total" class="badge bg-danger p-2 w-100 fs-6 text-wrap">
                                        El pago excede el total por: $ {{ formatoMoneda(sumaPagosIntroducidos - totales.total) }}
                                    </span>
                                </div>
                            </div>

                            <div class="col-md-5 bg-light p-3 rounded border">
                                <table class="table table-borderless table-sm text-end mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted text-start">Subtotal:</td>
                                            <td class="fw-bold text-dark font-monospace">$ {{ formatoMoneda(totales.subtotal) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted text-start">IVA (16%):</td>
                                            <td class="fw-bold text-dark font-monospace">$ {{ formatoMoneda(totales.impuesto) }}</td>
                                        </tr>
                                        <tr class="fs-5 border-top">
                                            <td class="fw-bold text-start pt-2">TOTAL:</td>
                                            <td class="fw-bold text-primary pt-2 font-monospace">$ {{ formatoMoneda(totales.total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="isSaving">Cerrar</button>
                        <button v-if="modalModo === 'ver'" class="btn btn-outline-warning" @click="activarModoEdicion">
                            Habilitar Edición
                        </button>
                        <button 
                            v-else
                            class="btn btn-success fw-bold d-inline-flex align-items-center gap-2"
                            @click="guardarNuevaCompra"
                            :disabled="detallesNueva.length === 0 || sumaPagosIntroducidos > totales.total || hayErrorCantidad || isSaving"
                            data-testid="btn-guardar-compra"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>{{ modalModo === 'crear' ? 'Guardar Orden de Compra' : 'Confirmar Cambios' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL DE RECEPCIÓN -->
        <div class="modal fade" id="recepcionModal" tabindex="-1" ref="recepcionModalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-xl">
                <div class="modal-content shadow border-0">
                    <div class="modal-header bg-light border-bottom">
                        <h5 class="modal-title fw-bold text-info m-0">Recepción de Mercancía</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :disabled="isSaving" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-secondary mb-4 row g-2">
                            <div class="col-md-3"><strong>Folio:</strong><br>{{ compraActiva.folio || ('OC-' + (compraActiva.id || compraActiva.id_compra)) }}</div>
                            <div class="col-md-3"><strong>Proveedor:</strong><br>{{ compraActiva.proveedor || '—' }}</div>
                            <div class="col-md-3"><strong>Almacén:</strong><br>{{ compraActiva.almacen || nombreAlmacen(compraActiva.id_almacen) || 'Almacén Principal' }}</div>
                            <div class="col-md-3"><strong>Fecha Esperada:</strong><br>{{ compraActiva.fecha_esperada || 'No especificada' }}</div>
                        </div>

                        <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">Inspección de Productos</h5>
                        <div class="table-responsive">
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
                                        <td class="text-start ps-3 fw-semibold">{{ item.nombre }}</td>
                                        <td class="fw-bold">{{ item.pedida }}</td>
                                        <td>
                                            <input 
                                                type="number" 
                                                class="form-control text-center fw-bold text-primary font-monospace" 
                                                v-model.number="item.recibida" 
                                                min="0" 
                                                :max="item.pedida" 
                                                :disabled="isSaving"
                                            >
                                        </td>
                                        <td>
                                            <input 
                                                type="text" 
                                                class="form-control form-control-sm" 
                                                v-model="item.observacion" 
                                                :disabled="isSaving" 
                                                :placeholder="item.recibida < item.pedida ? 'Motivo del faltante...' : 'Todo en orden'"
                                            >
                                        </td>
                                        <td>
                                            <span class="badge" :class="item.recibida >= item.pedida ? 'bg-success' : 'bg-warning text-dark'">
                                                {{ item.recibida >= item.pedida ? 'Completo' : 'Parcial' }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end fw-bold text-dark fs-6">
                            Estado Resultante de Recepción:
                            <span class="badge ms-2" :class="estadoRecepcion === 'recibido' ? 'bg-success' : 'bg-warning text-dark'">
                                {{ (estadoRecepcion || '').toUpperCase() }}
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" :disabled="isSaving">Cancelar</button>
                        <button 
                            class="btn btn-info fw-bold text-white d-inline-flex align-items-center gap-2" 
                            @click="guardarRecepcion" 
                            :disabled="isSaving"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>Confirmar Recepción</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { Modal } from 'bootstrap'
import axios from 'axios'
import { useGlobalStore } from '@/stores/store.js'
import Paginador from '@/components/Paginador.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
// Importaciones de servicios del módulo
import {
    calcularTotalesCompra,
    determinarEstadoRecepcion,
    obtenerHistorialCompras,
    obtenerCatalogoCompras,
    crearOrdenCompra,
    actualizarOrdenCompra,
    registrarRecepcionMercancia,
    obtenerSiguienteFolioCompra
} from '@/services/comprasService'
import { obtenerProveedores } from '@/services/proveedoresService'
import { obtenerAlmacenes } from '@/services/mermasService'

const store = useGlobalStore()
const url = computed(() => {
    const base = store.baseUrl || ''
    return base.endsWith('/') ? base : `${base}/`
})

// Referencia al modal de confirmación y alertas
const modalConfirm = ref(null)

// ESTADOS REACTIVOS
const nuevaCompraModalRef = ref(null)
const recepcionModalRef = ref(null)
const historialCompras = ref([])
const productosMaestros = ref([])
const proveedores = ref([])
const almacenesCompras = ref([])
const busquedaCompras = ref('')
const isLoading = ref(false)
const isSaving = ref(false)
const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value) || 1)
const modalModo = ref('crear')
const compraSeleccionadaId = ref(null)
const folioGenerado = ref('')

const formNueva = ref({
    id_proveedor: '',
    id_almacen: '',
    id_usuario: 1,
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

// Helper seguro para instancias de modal de Bootstrap
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

// Formato de moneda es-MX
const formatoMoneda = (valor) => {
    return Number(valor || 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })
}

const formatoNumero = (valor) => {
    const numero = Number(valor || 0)
    return Number.isInteger(numero) ? String(numero) : numero.toFixed(2).replace(/\.00$/, '')
}

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error en la operación.') => {
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

// ------------------------------
// CICLO DE VIDA Y CARGA
// ------------------------------
onMounted(async () => {
    await cargarDatos()
})

onBeforeUnmount(() => {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove())
    document.body.classList.remove('modal-open')
})

const cargarDatos = async () => {
    isLoading.value = true
    try {
        const data = await obtenerHistorialCompras({ pagina: pagina.value, por_pagina: porPagina.value })
        historialCompras.value = data.resultados || data.data || (Array.isArray(data) ? data : [])
        totalRegistros.value = data.total || historialCompras.value.length
        const prods = await obtenerCatalogoCompras()
        productosMaestros.value = Array.isArray(prods) ? prods : (prods.data || prods.resultados || [])
        if (proveedores.value.length === 0) {
            try {
                const provData = await obtenerProveedores({ por_pagina: 100, activo: 1 })
                proveedores.value = provData.resultados || provData.data || provData || []
            } catch {
                proveedores.value = []
            }
        }
        await asegurarAlmacenes(true)
    } catch (error) {
        console.error('Error al cargar datos de compras:', error)
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error de Conexión',
                mensaje: 'No fue posible sincronizar el historial de compras con el servidor.',
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
    } finally {
        isLoading.value = false
    }
}

const asegurarAlmacenes = async (forzar = false) => {
    if (!forzar && almacenesCompras.value.length > 0) return
    try {
        let datosAlms = []
        try {
            const res = await axios.get(`${url.value}inventarios/catalogo-almacenes`)
            datosAlms = Array.isArray(res.data) ? res.data : (res.data.data || [])
        } catch {
            const alms = await obtenerAlmacenes()
            datosAlms = Array.isArray(alms) ? alms : (alms.data || alms.resultados || [])
        }

        almacenesCompras.value = datosAlms.filter(alm => {
            const estaActivo = alm.activo === 1 || alm.activo === true || alm.activo === '1' || alm.activo === undefined
            const noEliminado = !alm.deleted_at
            return estaActivo && noEliminado
        })
    } catch (error) {
        almacenesCompras.value = []
        console.error('Error al cargar almacenes en compras:', error)
    }
}

const nombreAlmacen = (idAlmacen) => {
    const id = Number(idAlmacen)
    return almacenesCompras.value.find((a) => Number(a.id_almacen || a.id) === id)?.nombre || ''
}

const cambiarPagina = (nuevaPagina) => {
    pagina.value = nuevaPagina
    cargarDatos()
}

// ------------------------------
// CONVERSIONES Y VALIDACIONES DE FILA
// ------------------------------
const presentacionesMaestras = computed(() => {
    const productos = Array.isArray(productosMaestros.value) ? productosMaestros.value : []
    return productos.flatMap((producto) => (producto.presentaciones || []).map((presentacion) => ({
        id_producto: producto.id_producto,
        producto: producto.nombre,
        precio_compra: Number(producto.precio_compra) || 0,
        id_presentacion: presentacion.id_presentacion,
        presentacion: presentacion.presentacion,
        sku: presentacion.sku,
        id_tipo_presentacion: presentacion.id_tipo_presentacion,
        tipo_presentacion: presentacion.tipo_presentacion,
        factor_conversion: Number(presentacion.factor_conversion) || 1
    })))
})

const presentacionDe = (item) => presentacionesMaestras.value.find(
    (presentacion) => Number(presentacion.id_presentacion) === Number(item.id_presentacion)
)

const factorDe = (item) => Number(item.factor_conversion || presentacionDe(item)?.factor_conversion || 1)

const validarCantidadItem = (item) => {
    const cantPres = Number(item.cantidad_presentacion) || 0
    const factor = Number(item.factor_conversion) || 1
    
    // Total de piezas = Cantidad de presentaciones (cajas/paquetes) * Factor (Equivale u)
    item.cantidad = cantPres * factor

    if (!cantPres || cantPres < 1) {
        item.cantidadError = 'La cantidad debe ser al menos 1.'
    } else {
        item.cantidadError = ''
    }
}

watch(() => formNueva.value.id_proveedor, (v) => {
    errors.value.id_proveedor = !v ? 'Seleccione un proveedor.' : ''
})

watch(() => formNueva.value.id_almacen, (v) => {
    errors.value.id_almacen = !v ? 'Seleccione un almacén.' : ''
})

watch(() => formNueva.value.fecha_compra, (v) => {
    errors.value.fecha_compra = !v ? 'Seleccione una fecha.' : ''
})

const productosFiltrados = computed(() => {
    const lista = presentacionesMaestras.value
    if (!busquedaProducto.value) return lista
    const q = busquedaProducto.value.toLowerCase().trim()
    return lista.filter(p => [p.producto, p.presentacion, p.tipo_presentacion]
        .filter(Boolean)
        .some(valor => valor.toLowerCase().includes(q)))
})

const totales = computed(() => {
    if (typeof calcularTotalesCompra === 'function') {
        return calcularTotalesCompra(detallesNueva.value)
    }
    const subtotal = detallesNueva.value.reduce((acc, d) => acc + ((Number(d.cantidad) || 0) * (Number(d.precio) || 0)), 0)
    const impuesto = subtotal * 0.16
    return { subtotal, impuesto, total: subtotal + impuesto }
})

const sumaPagosIntroducidos = computed(() => {
    const ef = parseFloat(formNueva.value.pago_efectivo) || 0
    const tj = parseFloat(formNueva.value.pago_tarjeta) || 0
    return Math.round((ef + tj) * 100) / 100
})

const creditoCalculado = computed(() => {
    const falta = totales.value.total - sumaPagosIntroducidos.value
    return Math.max(0, Math.round(falta * 100) / 100)
})

const estadoRecepcion = computed(() => {
    if (typeof determinarEstadoRecepcion === 'function') {
        return determinarEstadoRecepcion(detallesRecepcion.value)
    }
    const completo = detallesRecepcion.value.every(d => Number(d.recibida) >= Number(d.pedida))
    return completo ? 'recibido' : 'parcial'
})

const hayErrorCantidad = computed(() => detallesNueva.value.some(d => !d.cantidad_presentacion || Number(d.cantidad_presentacion) < 1))

const comprasFiltradas = computed(() => {
    const lista = Array.isArray(historialCompras.value) ? historialCompras.value : []
    if (!busquedaCompras.value.trim()) return lista
    const q = busquedaCompras.value.toLowerCase().trim()
    return lista.filter(c =>
        (c.folio && c.folio.toLowerCase().includes(q)) ||
        (c.proveedor && c.proveedor.toLowerCase().includes(q))
    )
})

const getBadgeClass = (estado) => {
    const st = String(estado || '').toLowerCase()
    const clases = {
        recibido: 'bg-success',
        recibida: 'bg-success',
        parcial: 'bg-warning text-dark',
        orden: 'bg-secondary',
        pagado: 'bg-primary',
        pendiente: 'bg-secondary',
        cancelado: 'bg-danger',
        cancelada: 'bg-danger'
    }
    return clases[st] || 'bg-light text-dark border'
}

// ------------------------------
// BUSCADOR Y SELECCIÓN DE PRODUCTOS
// ------------------------------
const abrirDropdownProductos = () => { mostrarDropdown.value = true }
const filtrarProductos = () => { mostrarDropdown.value = true }

const seleccionarProducto = (prod) => {
    productoSeleccionado.value = prod
    busquedaProducto.value = `${prod.producto} — ${prod.tipo_presentacion || prod.presentacion || 'Presentación'}`
    mostrarDropdown.value = false
}

const ocultarDropdownConRetraso = () => {
    setTimeout(() => { mostrarDropdown.value = false }, 250)
}

const agregarProductoBuscado = () => {
    if (!productoSeleccionado.value) return
    const prod = productoSeleccionado.value
    const idProd = prod.id_presentacion
    
    if (!idProd) {
        console.error("El producto seleccionado no tiene un ID de presentación válido:", prod)
        return
    }

    const idx = detallesNueva.value.findIndex(item => Number(item.id_presentacion) === Number(idProd))
    const factorInicial = Number(prod.factor_conversion) || 1

    if (idx !== -1) {
        detallesNueva.value[idx].cantidad_presentacion = (Number(detallesNueva.value[idx].cantidad_presentacion) || 0) + 1
        validarCantidadItem(detallesNueva.value[idx])
    } else {
        detallesNueva.value.push({
            id_presentacion: Number(idProd),
            producto: prod.producto || 'Producto sin nombre',
            presentacion: prod.presentacion || '',
            tipo_presentacion: prod.tipo_presentacion || '',
            factor_conversion: factorInicial,
            cantidad_presentacion: 1,
            cantidad: factorInicial, // Total de piezas inicial (1 * factor)
            precio: Number(prod.precio_compra) || 0,
            descuento: 0,
            cantidadError: ''
        })
    }
    productoSeleccionado.value = null
    busquedaProducto.value = ''
}

const eliminarFilaCompra = (i) => { detallesNueva.value.splice(i, 1) }

// ------------------------------
// MODALES Y ACCIONES
// ------------------------------
const abrirModalNuevaCompra = async () => {
    modalModo.value = 'crear'
    compraSeleccionadaId.value = null
    detallesNueva.value = []
    folioGenerado.value = ''
    Object.keys(errors.value).forEach(k => errors.value[k] = '')
    
    await asegurarAlmacenes(true)

    const primerAlmacenId = almacenesCompras.value[0] ? (almacenesCompras.value[0].id_almacen || almacenesCompras.value[0].id) : ''
    
    formNueva.value = {
        id_proveedor: '', 
        id_almacen: primerAlmacenId, 
        id_usuario: 1,
        fecha_compra: new Date().toISOString().split('T')[0], 
        fecha_entrega: '',
        pago_efectivo: 0, 
        pago_tarjeta: 0
    }
    
    busquedaProducto.value = ''
    productoSeleccionado.value = null
    safeModalAction(nuevaCompraModalRef.value, 'show')
    
    try { 
        folioGenerado.value = await obtenerSiguienteFolioCompra() 
    } catch { 
        folioGenerado.value = 'OC-' + Date.now().toString().slice(-6) 
    }
}

const abrirModalVerDetalle = async (compra) => {
    modalModo.value = 'ver'
    compraSeleccionadaId.value = compra.id || compra.id_compra
    compraActiva.value = compra
    Object.keys(errors.value).forEach(k => errors.value[k] = '')
    
    await asegurarAlmacenes(true)

    formNueva.value = {
        id_proveedor: compra.id_proveedor,
        id_almacen: compra.id_almacen != null ? Number(compra.id_almacen) : '',
        id_usuario: 1,
        fecha_compra: compra.fecha || compra.fecha_compra, 
        fecha_entrega: compra.fecha_esperada || compra.fecha_entrega || '',
        pago_efectivo: Number(compra.monto_efectivo) || 0, 
        pago_tarjeta: Number(compra.monto_tarjeta) || 0
    }
    
    detallesNueva.value = compra.detalles ? compra.detalles.map((detalle) => {
        const presMaestra = presentacionDe(detalle)
        const factor = Number(detalle.factor_conversion || presMaestra?.factor_conversion) || 1
        const totalPiezas = Number(detalle.cantidad) || 0
        return {
            ...detalle,
            producto: detalle.producto || presMaestra?.producto || 'Producto sin nombre',
            presentacion: detalle.presentacion || presMaestra?.presentacion || '',
            tipo_presentacion: detalle.tipo_presentacion || presMaestra?.tipo_presentacion || '',
            factor_conversion: factor,
            cantidad_presentacion: factor > 0 ? totalPiezas / factor : totalPiezas,
            cantidad: totalPiezas,
            precio: Number(detalle.precio) || 0,
            cantidadError: ''
        }
    }) : []

    safeModalAction(nuevaCompraModalRef.value, 'show')
}

const activarModoEdicion = async () => {
    await asegurarAlmacenes(true)
    modalModo.value = 'editar'
}

const guardarNuevaCompra = async () => {
    if (isSaving.value) return

    if (!formNueva.value.id_proveedor || !formNueva.value.id_almacen || !formNueva.value.fecha_compra) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Campos requeridos',
                mensaje: 'Por favor, complete todos los campos obligatorios del encabezado (Proveedor, Almacén y Fecha).',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }
    if (detallesNueva.value.length === 0) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Detalle vacío',
                mensaje: 'Debe agregar al menos un producto al detalle de la compra.',
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
                titulo: 'Cantidad inválida',
                mensaje: 'Corrija las cantidades de los productos (deben ser mayores a 0).',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Entendido'
            })
        }
        return
    }

    const provObj = proveedores.value.find(p => String(p.id_proveedor || p.id) === String(formNueva.value.id_proveedor))
    const nombreProv = provObj ? (provObj.razon_social || provObj.nombre || provObj.nombre_comercial) : 'el proveedor seleccionado'
    const totalTxt = formatoMoneda(totales.value.total)
    const esEdicion = modalModo.value === 'editar'

    if (modalConfirm.value) {
        const confirmado = await modalConfirm.value.open({
            titulo: esEdicion ? 'Confirmar Modificación de Orden' : 'Confirmar Orden de Compra',
            mensaje: `¿Deseas ${esEdicion ? 'actualizar' : 'generar'} la orden de compra a favor de "${nombreProv}" por un monto total de $${totalTxt}?`,
            tipo: 'warning',
            mostrarCancelar: true,
            textoConfirmar: esEdicion ? 'Sí, Actualizar' : 'Sí, Generar Orden',
            textoCancelar: 'Cancelar'
        })
        if (!confirmado) return
    }

    isSaving.value = true
    try {
        const payload = {
            id_proveedor: Number(formNueva.value.id_proveedor),
            id_almacen: Number(formNueva.value.id_almacen),
            id_usuario: Number(formNueva.value.id_usuario) || 1,
            fecha_compra: formNueva.value.fecha_compra,
            fecha_entrega: formNueva.value.fecha_entrega || null,
            pago_efectivo: Number(formNueva.value.pago_efectivo) || 0,
            pago_tarjeta: Number(formNueva.value.pago_tarjeta) || 0,
            total: totales.value.total,
            detalles: detallesNueva.value.map(d => ({
                id_presentacion: Number(d.id_presentacion),
                cantidad: Number(d.cantidad) || 1, // Envía las piezas totales calculadas
                precio: Number(d.precio) || 0,
                descuento: Number(d.descuento) || 0
            }))
        }

        if (esEdicion) {
            await actualizarOrdenCompra(compraSeleccionadaId.value, payload)
        } else {
            await crearOrdenCompra(payload)
        }

        safeModalAction(nuevaCompraModalRef.value, 'hide')

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Operación Exitosa',
                mensaje: `Orden de compra ${esEdicion ? 'actualizada' : 'registrada'} exitosamente.`,
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }

        await cargarDatos()
    } catch (error) {
        console.error('Error al guardar compra:', error)
        const msg = extraerMensajeError(error, 'Error al procesar la compra.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error al Guardar',
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

const abrirModalRecepcion = (compra) => {
    compraActiva.value = compra
    detallesRecepcion.value = (compra.detalles || []).map(d => ({
        id_presentacion: d.id_presentacion,
        nombre: d.producto || d.nombre || d.presentacion?.nombre || 'Producto',
        pedida: Number(d.cantidad || 0),
        recibida: Number(d.cantidad || 0),
        observacion: ''
    }))
    safeModalAction(recepcionModalRef.value, 'show')
}

const guardarRecepcion = async () => {
    if (isSaving.value) return

    const idCompra = compraActiva.value.id || compraActiva.value.id_compra
    const folioTxt = compraActiva.value.folio ? ` (Folio: ${compraActiva.value.folio})` : ''

    if (modalConfirm.value) {
        const confirmado = await modalConfirm.value.open({
            titulo: 'Confirmar Entrada a Inventario',
            mensaje: `¿Deseas registrar la recepción física de mercancía para la compra${folioTxt}?\n\nEsta acción sumará las existencias al almacén correspondiente.`,
            tipo: 'warning',
            mostrarCancelar: true,
            textoConfirmar: 'Sí, Ingresar a Almacén',
            textoCancelar: 'Cancelar'
        })
        if (!confirmado) return
    }

    isSaving.value = true
    try {
        await registrarRecepcionMercancia(idCompra, detallesRecepcion.value)
        safeModalAction(recepcionModalRef.value, 'hide')

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Recepción Completada',
                mensaje: 'Mercancía recibida e inventario actualizado con éxito en el almacén.',
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }

        await cargarDatos()
    } catch (error) {
        console.error('Error en recepción:', error)
        const msg = extraerMensajeError(error, 'Error al registrar la recepción de mercancía.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error en Recepción',
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
