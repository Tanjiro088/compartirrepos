<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Centralizado para Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-header bg-transparent text-primary border-bottom border-primary text-center py-3">
                <h3 class="mb-0 fw-bold">Recepción de Mercancía e Ingreso a Stock</h3>
            </div>
            
            <!-- SPINNER DE CARGA INICIAL -->
            <div v-if="isLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando orden...</span>
                </div>
                <p class="mt-2 text-muted mb-0">Consultando orden de compra y detalle de artículos...</p>
            </div>

            <div v-else class="card-body p-4">
                <!-- Información del Maestro (Orden de Compra) -->
                <div class="alert alert-secondary mb-4 shadow-sm border-0">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <span class="text-muted small d-block">Folio de Orden:</span>
                            <strong class="font-monospace text-primary">{{ orden.folio }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small d-block">Proveedor:</span>
                            <strong class="text-dark">{{ orden.proveedor }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small d-block">Almacén Destino:</span>
                            <strong class="text-dark">{{ orden.almacen }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small d-block">Fecha Esperada:</span>
                            <strong class="font-monospace text-dark">{{ orden.fecha_entrega || 'No especificada' }}</strong>
                        </div>
                    </div>
                </div>

                <h5 class="text-dark mb-3 border-bottom pb-2 fw-bold">Inspección Física de Productos</h5>

                <!-- Tabla de Recepción -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th width="32%">Producto / Presentación</th>
                                <th width="12%">Cantidad Pedida</th>
                                <th width="16%">Cantidad Recibida *</th>
                                <th width="28%">Observaciones (Faltantes / Daños)</th>
                                <th width="12%">Estado de Fila</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in detallesCompra" :key="index" :class="{'table-warning': Number(item.cantidad_recibida) < Number(item.cantidad_pedida)}">
                                <td class="text-start fw-semibold text-dark">{{ item.producto }}</td>
                                <td class="fw-bold fs-6 font-monospace">{{ item.cantidad_pedida }}</td>
                                <td>
                                    <input 
                                        type="number" 
                                        class="form-control form-control-sm text-center fw-bold text-primary font-monospace" 
                                        v-model.number="item.cantidad_recibida" 
                                        min="0" 
                                        :max="item.cantidad_pedida"
                                        step="1"
                                        :disabled="isSaving"
                                    >
                                </td>
                                <td>
                                    <input 
                                        type="text" 
                                        class="form-control form-control-sm" 
                                        v-model.trim="item.observacion" 
                                        :placeholder="Number(item.cantidad_recibida) < Number(item.cantidad_pedida) ? 'Especifique el motivo del faltante...' : 'Todo en orden'"
                                        :disabled="isSaving"
                                    >
                                </td>
                                <td>
                                    <span class="badge" :class="Number(item.cantidad_recibida) === Number(item.cantidad_pedida) ? 'bg-success' : 'bg-warning text-dark'">
                                        {{ Number(item.cantidad_recibida) === Number(item.cantidad_pedida) ? 'Completo' : 'Incompleto' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="detallesCompra.length === 0">
                                <td colspan="5" class="text-muted py-4 fst-italic">
                                    No hay artículos registrados en esta orden de compra.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Resumen y Guardado -->
                <div class="row align-items-center bg-light p-3 rounded border shadow-sm">
                    <div class="col-md-7 mb-3 mb-md-0">
                        <label class="form-label fw-bold small text-secondary">Observaciones Generales de la Recepción</label>
                        <textarea 
                            class="form-control" 
                            v-model.trim="observacionesGenerales" 
                            rows="2" 
                            placeholder="Ej. El camión llegó con retraso, cajas mojadas en tarima central..."
                            :disabled="isSaving"
                        ></textarea>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <div class="mb-3">
                            <span class="text-muted small me-2">Estado resultante de la orden:</span>
                            <span class="badge fs-6 text-uppercase px-3 py-2 font-monospace" :class="estadoResultante === 'recibido' ? 'bg-success' : 'bg-warning text-dark'">
                                {{ estadoResultante }}
                            </span>
                        </div>
                        <button 
                            type="button"
                            class="btn btn-success btn-lg fw-bold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm text-white w-100 w-md-auto" 
                            @click="confirmarRecepcion"
                            :disabled="isSaving || detallesCompra.length === 0"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <i v-else class="bi bi-box-arrow-in-down"></i>
                            <span>{{ isSaving ? 'Registrando Ingreso...' : '📥 Ingresar al Inventario' }}</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <ToastNotification ref="toast" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import ConfirmModal from '@/components/ConfirmModal.vue'
import ToastNotification from '@/components/ToastNotification.vue'
import { obtenerOrdenCompraPorId, registrarRecepcionCompra } from '@/services/comprasService'

const router = useRouter()
const route = useRoute()
const modalConfirm = ref(null)
const toast = ref(null)

const isLoading = ref(false)
const isSaving = ref(false)

// Estructura limpia del encabezado de la orden de compra
const orden = ref({
    id_compra: null,
    folio: '',
    proveedor: '',
    almacen: '',
    fecha_entrega: ''
})

// Detalles de la orden de compra
const detallesCompra = ref([])
const observacionesGenerales = ref('')

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error al procesar la recepción de mercancía.') => {
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

// Carga de datos reales desde el servidor
const cargarOrdenCompra = async () => {
    const idCompra = route.params.id || 1
    isLoading.value = true
    try {
        const data = await obtenerOrdenCompraPorId(idCompra)
        orden.value = {
            id_compra: data.id_compra || data.id || idCompra,
            folio: data.folio || 'OC-GENERAL',
            proveedor: data.proveedor?.razon_social || data.proveedor?.nombre_comercial || data.proveedor || 'Proveedor',
            almacen: data.almacen?.nombre || data.almacen || 'Almacén General',
            fecha_entrega: data.fecha_entrega || ''
        }

        const items = data.detalles || data.items || []
        detallesCompra.value = items.map(d => ({
            id_presentacion: Number(d.id_presentacion || d.id),
            producto: d.producto || d.nombre || `Artículo #${d.id_presentacion || d.id}`,
            cantidad_pedida: Number(d.cantidad_pedida || d.cantidad || 0),
            cantidad_recibida: Number(d.cantidad_recibida ?? d.cantidad_pedida ?? d.cantidad ?? 0),
            observacion: String(d.observacion || '').trim()
        }))
    } catch (error) {
        console.error('Error al cargar orden de compra:', error)
        const msg = extraerMensajeError(error, 'No fue posible consultar los detalles de la orden en el servidor.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error de Carga',
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
    await cargarOrdenCompra()
})

/**
 * Propiedad Computada: estadoResultante
 * Propósito: Determina si la compra pasa a estado "recibido" (completo) o "parcial" 
 * verificando si algún producto tiene una cantidad recibida menor a la pedida.
 */
const estadoResultante = computed(() => {
    if (detallesCompra.value.length === 0) return 'recibido'
    const hayFaltantes = detallesCompra.value.some(item => Number(item.cantidad_recibida) < Number(item.cantidad_pedida))
    return hayFaltantes ? 'parcial' : 'recibido'
})

// Validación previa de cantidades
const validarRecepcion = () => {
    if (detallesCompra.value.length === 0) return false
    return detallesCompra.value.every(d => {
        const rec = Number(d.cantidad_recibida)
        return !isNaN(rec) && rec >= 0
    })
}

/**
 * Función: confirmarRecepcion
 * Propósito: Envía el parte de recepción al backend con bloqueo de concurrencia y confirmación modal.
 */
const confirmarRecepcion = async () => {
    if (isSaving.value) return

    if (!validarRecepcion()) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Cantidades Inválidas',
                mensaje: 'Por favor verifique que todas las cantidades recibidas sean numéricas y mayores o iguales a cero.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Revisar'
            })
        }
        return
    }

    const estadoTxt = estadoResultante.value.toUpperCase()

    if (modalConfirm.value) {
        const aceptado = await modalConfirm.value.open({
            titulo: 'Confirmar Ingreso de Mercancía',
            mensaje: `¿Estás seguro de registrar la recepción de esta orden?\n\n• Estado resultante: ${estadoTxt}\n• Almacén destino: ${orden.value.almacen}`,
            tipo: 'warning',
            mostrarCancelar: true,
            textoConfirmar: 'Sí, Ingresar a Stock',
            textoCancelar: 'Cancelar'
        })

        if (!aceptado) return
    }

    isSaving.value = true

    const payload = {
        id_compra: Number(orden.value.id_compra),
        estado_nuevo: estadoResultante.value,
        observaciones: String(observacionesGenerales.value || '').trim(),
        detalles_recibidos: detallesCompra.value.map(d => ({
            id_presentacion: Number(d.id_presentacion),
            cantidad_recibida: Number(d.cantidad_recibida) || 0,
            observacion: String(d.observacion || '').trim()
        }))
    }

    try {
        await registrarRecepcionCompra(payload)

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: '¡Recepción Completada!',
                mensaje: 'Recepción registrada correctamente. Las existencias del inventario han sido actualizadas.',
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Ir al Listado'
            })
        }

        router.push('/compras')
    } catch (error) {
        console.error("Error al registrar recepción de compra:", error)
        const msg = extraerMensajeError(error, 'No se pudo asentar la recepción de mercancía en el servidor.')
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
.btn-outline-info { 
    border-color: var(--bs-info, #0dcaf0); 
    color: var(--bs-info, #0dcaf0); 
    background: transparent; 
}

.btn-outline-info:hover { 
    background: var(--bs-info, #0dcaf0); 
    color: var(--bs-white, #ffffff); 
}

.btn { 
    transition: all 0.2s ease-in-out; 
}
</style>