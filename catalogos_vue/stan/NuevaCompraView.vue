<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Centralizado para Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-header bg-transparent text-primary border-bottom border-primary text-center py-3">
                <h3 class="mb-0 fw-bold">Registrar Orden de Compra</h3>
            </div>
            
            <!-- SPINNER DE CARGA INICIAL -->
            <div v-if="isLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando catálogos...</span>
                </div>
                <p class="mt-2 text-muted mb-0">Cargando proveedores, almacenes y catálogo de artículos...</p>
            </div>

            <div v-else class="card-body p-4">
                
                <!-- SECCIÓN MAESTRO: Datos Generales de la Compra -->
                <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">1. Datos Generales (Maestro)</h5>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Proveedor <span class="text-danger">*</span></label>
                        <select 
                            class="form-select" 
                            v-model="compra.id_proveedor"
                            :disabled="isSaving"
                        >
                            <option value="" disabled>Seleccione un proveedor...</option>
                            <option 
                                v-for="prov in proveedores" 
                                :key="prov.id_proveedor || prov.id" 
                                :value="prov.id_proveedor || prov.id"
                            >
                                {{ prov.razon_social || prov.nombre_comercial || prov.nombre }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Almacén Destino <span class="text-danger">*</span></label>
                        <select 
                            class="form-select" 
                            v-model.number="compra.id_almacen"
                            :disabled="isSaving"
                        >
                            <option value="" disabled>Seleccione un almacén...</option>
                            <option 
                                v-for="alm in almacenes" 
                                :key="alm.id_almacen || alm.id" 
                                :value="Number(alm.id_almacen || alm.id)"
                            >
                                {{ alm.nombre }} ({{ alm.sucursal_nombre || 'Sucursal' }})
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Folio Proyectado</label>
                        <input 
                            type="text" 
                            class="form-control bg-light font-monospace fw-bold text-primary" 
                            v-model="compra.folio" 
                            placeholder="Generando folio..." 
                            readonly
                        >
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Compra <span class="text-danger">*</span></label>
                        <input 
                            type="date" 
                            class="form-control" 
                            v-model="compra.fecha_compra"
                            :disabled="isSaving"
                        >
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Entrega (Estimada)</label>
                        <input 
                            type="date" 
                            class="form-control" 
                            v-model="compra.fecha_entrega"
                            :disabled="isSaving"
                        >
                    </div>
                </div>

                <!-- SECCIÓN DETALLE: Productos a comprar -->
                <div class="d-flex justify-content-between align-items-end border-bottom pb-2 mb-3">
                    <h5 class="text-dark mb-0 fw-bold">2. Productos (Detalle)</h5>
                    <button 
                        type="button"
                        class="btn btn-sm btn-outline-primary fw-bold d-inline-flex align-items-center gap-1" 
                        @click="agregarFila"
                        :disabled="isSaving"
                    >
                        <i class="bi bi-plus-lg"></i>
                        <span>Agregar Producto</span>
                    </button>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th width="32%">Producto / Presentación <span class="text-danger">*</span></th>
                                <th width="14%">Cantidad <span class="text-danger">*</span></th>
                                <th width="16%">Precio Unit. ($) <span class="text-danger">*</span></th>
                                <th width="14%">Descuento ($)</th>
                                <th width="16%">Subtotal ($)</th>
                                <th width="8%">Quitar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(detalle, index) in detalles" :key="index">
                                <td>
                                    <select 
                                        class="form-select form-select-sm" 
                                        :class="{ 'is-invalid': !detalle.id_presentacion }"
                                        v-model="detalle.id_presentacion" 
                                        @change="alCambiarProducto(detalle, index)"
                                        :disabled="isSaving"
                                    >
                                        <option value="" disabled>Seleccione un artículo...</option>
                                        <option 
                                            v-for="prod in productos" 
                                            :key="prod.id_presentacion || prod.id_producto || prod.id" 
                                            :value="prod.id_presentacion || prod.id_producto || prod.id"
                                        >
                                            {{ prod.nombre || prod.producto }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input 
                                        type="number" 
                                        class="form-control form-control-sm text-center fw-bold font-monospace" 
                                        :class="{ 'is-invalid': Number(detalle.cantidad) < 1 }"
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
                                        :class="{ 'is-invalid': Number(detalle.precio_unitario) <= 0 }"
                                        v-model.number="detalle.precio_unitario" 
                                        step="0.01" 
                                        min="0.01"
                                        :disabled="isSaving"
                                    >
                                </td>
                                <td>
                                    <input 
                                        type="number" 
                                        class="form-control form-control-sm text-center font-monospace" 
                                        v-model.number="detalle.descuento" 
                                        step="0.01" 
                                        min="0"
                                        :disabled="isSaving"
                                    >
                                </td>
                                <td class="fw-bold bg-light text-dark font-monospace">
                                    $ {{ formatoMoneda(calcularSubtotalFila(detalle)) }}
                                </td>
                                <td>
                                    <button 
                                        type="button"
                                        class="btn btn-sm btn-outline-danger" 
                                        @click="eliminarFila(index)" 
                                        :disabled="isSaving || detalles.length <= 1"
                                        title="Quitar Fila"
                                    >
                                        &times;
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="detalles.length === 0">
                                <td colspan="6" class="text-muted py-4 fst-italic">
                                    No hay productos agregados. Haga clic en "+ Agregar Producto".
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- SECCIÓN TOTALES -->
                <div class="row justify-content-end pt-2">
                    <div class="col-md-5 col-lg-4">
                        <ul class="list-group mb-3 shadow-sm">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold font-monospace">$ {{ formatoMoneda(totales.subtotal) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-muted">IVA (16%):</span>
                                <span class="fw-bold font-monospace">$ {{ formatoMoneda(totales.impuesto) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                <h5 class="mb-0 fw-bold">TOTAL:</h5>
                                <h5 class="mb-0 text-primary fw-bold font-monospace">$ {{ formatoMoneda(totales.total) }}</h5>
                            </li>
                        </ul>
                        <div class="d-grid gap-2">
                            <button 
                                type="button"
                                class="btn btn-success btn-lg fw-bold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm text-white" 
                                :disabled="!formularioValido || isSaving"
                                @click="guardarCompra"
                            >
                                <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                                <i v-else class="bi bi-check-circle"></i>
                                <span>{{ isSaving ? 'Guardando Orden...' : 'Guardar Orden de Compra' }}</span>
                            </button>
                            <button 
                                type="button"
                                class="btn btn-outline-secondary btn-lg" 
                                :disabled="isSaving"
                                @click="volverAlHistorial"
                            >
                                Cancelar y Volver
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import ConfirmModal from '@/components/ConfirmModal.vue'
import { crearOrdenCompra, obtenerSiguienteFolioCompra, obtenerProductosDisponibles } from '@/services/comprasService'
import { obtenerProveedores } from '@/services/proveedoresService'
import { obtenerAlmacenes } from '@/services/mermasService'

const router = useRouter()
const modalConfirm = ref(null)

const proveedores = ref([])
const almacenes = ref([])
const productos = ref([])

const isLoading = ref(false)
const isSaving = ref(false)

const compra = ref({
  id_proveedor: '',
  id_almacen: '',
  folio: '',
  fecha_compra: new Date().toISOString().split('T')[0],
  fecha_entrega: ''
})

const detalles = ref([])

const formatoMoneda = (valor) => {
  return Number(valor || 0).toLocaleString('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error al registrar la orden de compra.') => {
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

const cargarCatalogos = async () => {
  isLoading.value = true
  try {
    const [resProv, resAlm, resProd] = await Promise.all([
      obtenerProveedores({ por_pagina: 100, activo: 1 }),
      obtenerAlmacenes(),
      obtenerProductosDisponibles()
    ])

    proveedores.value = Array.isArray(resProv) ? resProv : (resProv.resultados || resProv.data || [])
    almacenes.value = Array.isArray(resAlm) ? resAlm : (resAlm.data || resAlm.resultados || [])
    productos.value = Array.isArray(resProd) ? resProd : (resProd.data || resProd.resultados || [])

    if (almacenes.value.length > 0 && !compra.value.id_almacen) {
      compra.value.id_almacen = Number(almacenes.value[0].id_almacen || almacenes.value[0].id)
    }

    try {
      compra.value.folio = await obtenerSiguienteFolioCompra()
    } catch {
      compra.value.folio = 'OC-' + Date.now().toString().slice(-6)
    }
  } catch (error) {
    console.error('Error al cargar catálogos en Nueva Compra:', error)
    const msg = extraerMensajeError(error, 'No fue posible sincronizar los catálogos del servidor.')
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Error de Catálogos',
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

onMounted(async () => {
  await cargarCatalogos()
  if (detalles.value.length === 0) {
    agregarFila()
  }
})

const agregarFila = () => {
  detalles.value.push({
    id_presentacion: '',
    cantidad: 1,
    precio_unitario: 0,
    descuento: 0
  })
}

const eliminarFila = (index) => {
  detalles.value.splice(index, 1)
}

const alCambiarProducto = (detalle, index) => {
  const prod = productos.value.find(p => Number(p.id_presentacion || p.id_producto || p.id) === Number(detalle.id_presentacion))
  if (prod) {
    // Validar si el producto ya está en otra fila previa
    const duplicado = detalles.value.some((d, idx) => idx !== index && Number(d.id_presentacion) === Number(detalle.id_presentacion))
    if (duplicado) {
      detalle.id_presentacion = ''
      detalle.precio_unitario = 0
      if (modalConfirm.value) {
        modalConfirm.value.open({
          titulo: 'Producto Duplicado',
          mensaje: `El artículo "${prod.nombre || prod.producto}" ya está agregado en la orden. Puedes incrementar su cantidad en la fila existente.`,
          tipo: 'warning',
          mostrarCancelar: false,
          textoConfirmar: 'Entendido'
        })
      }
      return
    }
    detalle.precio_unitario = Number(prod.costo_promedio || prod.precio_compra || prod.costo || prod.precio || 0)
  }
}

const calcularSubtotalFila = (detalle) => {
  const cantidad = Number(detalle.cantidad) || 0
  const precio = Number(detalle.precio_unitario) || 0
  const descuento = Number(detalle.descuento) || 0
  
  const subtotal = (cantidad * precio) - descuento
  return Math.max(0, Math.round(subtotal * 100) / 100)
}

const totales = computed(() => {
  let sumaSubtotal = 0
  detalles.value.forEach(detalle => {
    sumaSubtotal += calcularSubtotalFila(detalle)
  })

  const subtotal = Math.round(sumaSubtotal * 100) / 100
  const impuesto = Math.round((subtotal * 0.16) * 100) / 100
  const total = Math.round((subtotal + impuesto) * 100) / 100

  return {
    subtotal,
    impuesto,
    total
  }
})

const formularioValido = computed(() => {
  const encabezadoOk = Boolean(compra.value.id_proveedor) && Boolean(compra.value.id_almacen) && Boolean(compra.value.fecha_compra)
  const tieneFilas = detalles.value.length > 0
  const filasValidas = detalles.value.every(d => Boolean(d.id_presentacion) && Number(d.cantidad) >= 1 && Number(d.precio_unitario) > 0)

  return encabezadoOk && tieneFilas && filasValidas && !isSaving.value
})

const guardarCompra = async () => {
  if (isSaving.value) return

  if (!compra.value.id_proveedor || !compra.value.id_almacen || !compra.value.fecha_compra) {
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Campos requeridos',
        mensaje: 'Por favor complete todos los datos del proveedor, almacén y fecha de compra.',
        tipo: 'warning',
        mostrarCancelar: false,
        textoConfirmar: 'Entendido'
      })
    }
    return
  }

  if (detalles.value.length === 0) {
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Lista vacía',
        mensaje: 'Debe agregar al menos un producto a la lista de compra.',
        tipo: 'warning',
        mostrarCancelar: false,
        textoConfirmar: 'Entendido'
      })
    }
    return
  }

  const filaInvalida = detalles.value.some(d => !d.id_presentacion || Number(d.cantidad) < 1 || Number(d.precio_unitario) <= 0)
  if (filaInvalida) {
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Artículos Incompletos',
        mensaje: 'Verifique que cada renglón tenga un producto seleccionado, cantidad mínima de 1 y un costo unitario válido.',
        tipo: 'warning',
        mostrarCancelar: false,
        textoConfirmar: 'Revisar'
      })
    }
    return
  }

  const prov = proveedores.value.find(p => Number(p.id_proveedor || p.id) === Number(compra.value.id_proveedor))
  const nombreProv = prov?.razon_social || prov?.nombre_comercial || 'Proveedor seleccionado'
  const totalTxt = formatoMoneda(totales.value.total)

  if (modalConfirm.value) {
    const confirmado = await modalConfirm.value.open({
      titulo: 'Confirmar Orden de Compra',
      mensaje: `¿Deseas emitir la orden ${compra.value.folio} para "${nombreProv}" por un total de $${totalTxt}?\n\nLos productos se proyectarán al almacén asignado.`,
      tipo: 'warning',
      mostrarCancelar: true,
      textoConfirmar: 'Sí, Guardar Orden',
      textoCancelar: 'Cancelar'
    })
    if (!confirmado) return
  }

  isSaving.value = true

  try {
    const payload = {
      id_proveedor: Number(compra.value.id_proveedor),
      id_almacen: Number(compra.value.id_almacen),
      id_usuario: 1,
      fecha_compra: compra.value.fecha_compra,
      fecha_entrega: compra.value.fecha_entrega || null,
      pago_efectivo: 0,
      pago_tarjeta: 0,
      total: totales.value.total,
      detalles: detalles.value.map(d => ({
        id_presentacion: Number(d.id_presentacion),
        cantidad: Number(d.cantidad) || 1,
        precio: Number(d.precio_unitario) || 0,
        descuento: Number(d.descuento) || 0
      }))
    }

    await crearOrdenCompra(payload)
    
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: '¡Orden Registrada!',
        mensaje: `La orden de compra ${compra.value.folio} ha sido creada exitosamente.`,
        tipo: 'success',
        mostrarCancelar: false,
        textoConfirmar: 'Ir a Compras'
      })
    }
    
    router.push('/compras')
  } catch (error) {
    console.error('Error al registrar orden de compra:', error)
    const msg = extraerMensajeError(error, 'Error al guardar la orden de compra.')
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

const volverAlHistorial = async () => {
  const tieneDatos = compra.value.id_proveedor || detalles.value.some(d => d.id_presentacion || Number(d.cantidad) > 1)
  
  if (tieneDatos && modalConfirm.value) {
    const confirmado = await modalConfirm.value.open({
      titulo: '¿Descartar orden de compra?',
      mensaje: 'Existen datos introducidos en la orden. Si sales ahora, se perderán todos los cambios.',
      tipo: 'warning',
      mostrarCancelar: true,
      textoConfirmar: 'Sí, Salir',
      textoCancelar: 'Continuar editando'
    })
    if (!confirmado) return
  }
  router.push('/compras')
}
</script>

<style scoped>
/* Transiciones fluidas en botones de acción */
.btn-outline-success,
.btn-outline-secondary,
.btn-outline-primary,
.btn-outline-danger {
  transition: all 0.15s ease-in-out;
}

/* Respeto estricto del estado inactivo durante isLoading / isSaving */
.btn:disabled,
.form-select:disabled,
.form-control:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

/* Ajuste de encabezado de tarjeta */
.card-header {
  border-radius: 0.375rem 0.375rem 0 0;
}

/* Ocultar flechas de incremento nativas en inputs numéricos de la tabla para limpieza visual */
input[type='number']::-webkit-inner-spin-button,
input[type='number']::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

input[type='number'] {
  appearance: textfield;
  -moz-appearance: textfield;
}

/* Compactar espaciado de celdas en la tabla de desglose */
.table td, 
.table th {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}
</style>