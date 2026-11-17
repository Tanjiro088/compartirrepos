<script setup>
import { ref, onMounted } from 'vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import ToastNotification from '@/components/ToastNotification.vue'
import { obtenerReportePerdidas } from '@/services/mermasService.js'

const modalConfirm = ref(null)
const toast = ref(null)

const fechaDesde = ref(new Date().toISOString().slice(0, 7) + '-01')
const fechaHasta = ref(new Date().toISOString().slice(0, 10))
const cargando = ref(false)
const reporte = ref(null)

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Ocurrió un error al cargar el reporte financiero de pérdidas.') => {
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

const formatMoneda = (valor) => {
  const num = Number(valor || 0)
  return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const cargarReporte = async () => {
  if (cargando.value) return

  if (fechaDesde.value && fechaHasta.value && new Date(fechaHasta.value) < new Date(fechaDesde.value)) {
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Rango de Fechas Inválido',
        mensaje: 'La fecha "Hasta" no puede ser anterior a la fecha "Desde".',
        tipo: 'warning',
        mostrarCancelar: false,
        textoConfirmar: 'Revisar'
      })
    }
    return
  }

  cargando.value = true
  try {
    const data = await obtenerReportePerdidas({
      fecha_desde: fechaDesde.value,
      fecha_hasta: fechaHasta.value
    })
    reporte.value = data?.data || data || null
    toast.value?.notify?.('Reporte generado correctamente')
  } catch (err) {
    console.error("Error al cargar reporte de pérdidas:", err)
    reporte.value = null
    toast.value?.apiErr?.(err)
    const msg = extraerMensajeError(err, 'Ocurrió un error al cargar el reporte financiero de pérdidas desde el servidor.')
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Error de Consulta',
        mensaje: msg,
        tipo: 'danger',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
  } finally {
    cargando.value = false
  }
}

onMounted(() => {
  cargarReporte()
})
</script>

<template>
  <div class="container mt-4 mb-5 animate-fade-in">
    <!-- Modal Personalizado para Evitar Salida de Pantalla Completa -->
    <ConfirmModal ref="modalConfirm" />

    <div class="card shadow-sm border-light">
      <div class="card-body p-4">
        <h3 class="text-center mb-4 text-dark fw-bold">Reporte Financiero de Pérdidas por Mermas</h3>

        <div class="row mb-4 g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-bold small text-secondary">Fecha Desde *</label>
            <input type="date" class="form-control font-monospace" v-model="fechaDesde" :disabled="cargando">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold small text-secondary">Fecha Hasta *</label>
            <input type="date" class="form-control font-monospace" v-model="fechaHasta" :disabled="cargando">
          </div>
          <div class="col-md-3">
            <button 
              type="button" 
              class="btn btn-warning w-100 fw-bold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm text-dark" 
              @click="cargarReporte" 
              :disabled="cargando"
            >
              <span v-if="cargando" class="spinner-border spinner-border-sm" role="status"></span>
              <i v-else class="bi bi-search"></i>
              <span>{{ cargando ? 'Generando...' : 'Generar Reporte' }}</span>
            </button>
          </div>
        </div>

        <div v-if="reporte" class="mt-4">
          <div class="row mb-4">
            <div class="col-md-4">
              <div class="card border-warning bg-light shadow-sm">
                <div class="card-body text-center">
                  <h6 class="text-muted small fw-semibold">Total de Pérdidas Monetarias</h6>
                  <h3 class="text-danger fw-bold font-monospace mt-2 mb-1">$ {{ formatMoneda(reporte?.total_perdidas) }}</h3>
                  <small class="text-muted font-monospace">{{ reporte?.periodo || `${fechaDesde} al ${fechaHasta}` }}</small>
                </div>
              </div>
            </div>
          </div>

          <div class="row mt-4 g-4">
            <div class="col-md-6">
              <h5 class="fw-bold mb-3 text-dark">Top 5 Productos con Mayor Pérdida</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th width="10%">#</th>
                      <th width="45%">Producto</th>
                      <th width="20%">Cantidad</th>
                      <th width="25%">Pérdida</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(p, i) in (reporte?.top_5_productos || [])" :key="i">
                      <td class="fw-bold font-monospace text-secondary">{{ i + 1 }}</td>
                      <td class="text-start fw-semibold text-dark">{{ p?.producto || 'Sin nombre' }}</td>
                      <td class="font-monospace">{{ p?.cantidad_total || 0 }}</td>
                      <td class="fw-bold text-danger font-monospace">$ {{ formatMoneda(p?.perdida) }}</td>
                    </tr>
                    <tr v-if="!reporte?.top_5_productos || reporte?.top_5_productos.length === 0">
                      <td colspan="4" class="text-muted py-4 fst-italic">No hay pérdidas registradas en este período.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="col-md-6">
              <h5 class="fw-bold mb-3 text-dark">Pérdidas por Tipo de Merma</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th width="35%">Tipo</th>
                      <th width="30%">Monto</th>
                      <th width="35%">Proporción (%)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="t in (reporte?.por_tipo || [])" :key="t?.tipo">
                      <td class="fw-bold text-dark text-capitalize">{{ t?.tipo || 'General' }}</td>
                      <td class="text-danger font-monospace fw-semibold">$ {{ formatMoneda(t?.monto) }}</td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="progress flex-grow-1 bg-light border" style="height: 18px">
                            <div 
                              class="progress-bar bg-warning text-dark fw-bold font-monospace small" 
                              :style="{ width: Math.min(Number(t?.porcentaje || 0), 100) + '%' }"
                              role="progressbar"
                            >
                              {{ t?.porcentaje || 0 }}%
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="!reporte?.por_tipo || reporte?.por_tipo.length === 0">
                      <td colspan="3" class="text-muted py-4 fst-italic">Sin datos por tipo de merma.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-12">
              <h5 class="fw-bold mb-3 text-dark">Tendencia Mensual (Últimos 12 meses)</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th width="50%">Mes</th>
                      <th width="50%">Monto Perdido</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="t in (reporte?.tendencia_mensual || [])" :key="t?.mes">
                      <td class="fw-bold font-monospace text-dark">{{ t?.mes }}</td>
                      <td class="text-danger fw-bold font-monospace">$ {{ formatMoneda(t?.monto) }}</td>
                    </tr>
                    <tr v-if="!reporte?.tendencia_mensual || reporte?.tendencia_mensual.length === 0">
                      <td colspan="2" class="text-muted py-4 fst-italic">Sin datos de tendencia mensual.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!reporte && !cargando" class="text-center text-muted mt-5 py-5 card border-0 bg-light">
          <i class="bi bi-bar-chart-line fs-1 text-secondary mb-2"></i>
          <p class="fs-5 mb-0">Seleccione un rango de fechas y presione "Generar Reporte" para consultar las estadísticas.</p>
        </div>
      </div>
    </div>

    <ToastNotification ref="toast" />
  </div>
</template>