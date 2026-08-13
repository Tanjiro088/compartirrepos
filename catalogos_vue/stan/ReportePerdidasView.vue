<template>
  <div class="container mt-4 mb-5">
    <div class="card shadow-sm border-light">
      <div class="card-body p-4">
        <h3 class="text-center mb-4 text-dark fw-bold">Reporte de Pérdidas por Mermas</h3>

        <div class="row mb-4">
          <div class="col-md-3">
            <label class="form-label fw-bold">Fecha Desde</label>
            <input type="date" class="form-control" v-model="fechaDesde">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-bold">Fecha Hasta</label>
            <input type="date" class="form-control" v-model="fechaHasta">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-outline-warning w-100" @click="cargarReporte" :disabled="cargando">
              <span v-if="cargando" class="spinner-border spinner-border-sm me-2"></span>
              Generar Reporte
            </button>
          </div>
        </div>

        <div v-if="reporte" class="mt-4">
          <div class="row mb-4">
            <div class="col-md-4">
              <div class="card border-warning bg-light">
                <div class="card-body text-center">
                  <h6 class="text-muted">Total de Pérdidas</h6>
                  <h3 class="text-danger fw-bold">$ {{$formatCurrency( reporte.total_perdidas) }}</h3>
                  <small class="text-muted">{{ reporte.periodo }}</small>
                </div>
              </div>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-md-6">
              <h5 class="fw-bold mb-3">Top 5 Productos con Mayor Pérdida</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Producto</th>
                      <th>Cantidad</th>
                      <th>Pérdida</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(p, i) in reporte.top_5_productos" :key="i">
                      <td class="fw-bold">{{ i + 1 }}</td>
                      <td>{{ p.producto }}</td>
                      <td>{{ p.cantidad_total }}</td>
                      <td class="fw-bold text-danger">$ {{$formatCurrency( p.perdida) }}</td>
                    </tr>
                    <tr v-if="reporte.top_5_productos.length === 0">
                      <td colspan="4" class="text-muted py-3">No hay pérdidas registradas en este período.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="col-md-6">
              <h5 class="fw-bold mb-3">Pérdidas por Tipo de Merma</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th>Tipo</th>
                      <th>Monto</th>
                      <th>%</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="t in reporte.por_tipo" :key="t.tipo">
                      <td class="fw-bold">{{ t.tipo }}</td>
                      <td class="text-danger">$ {{$formatCurrency( t.monto) }}</td>
                      <td>
                        <div class="progress" style="height: 20px">
                          <div class="progress-bar bg-warning text-dark fw-bold" :style="{ width: t.porcentaje + '%' }">
                            {{ t.porcentaje }}%
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="reporte.por_tipo.length === 0">
                      <td colspan="3" class="text-muted py-3">Sin datos.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="row mt-4">
            <div class="col-12">
              <h5 class="fw-bold mb-3">Tendencia Mensual (Últimos 12 meses)</h5>
              <div class="table-responsive">
                <table class="table table-hover align-middle text-center">
                  <thead class="table-light">
                    <tr>
                      <th>Mes</th>
                      <th>Monto Perdido</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="t in reporte.tendencia_mensual" :key="t.mes">
                      <td class="fw-bold">{{ t.mes }}</td>
                      <td class="text-danger fw-bold">$ {{$formatCurrency( t.monto) }}</td>
                    </tr>
                    <tr v-if="reporte.tendencia_mensual.length === 0">
                      <td colspan="2" class="text-muted py-3">Sin datos de tendencia.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div v-if="!reporte && !cargando" class="text-center text-muted mt-5 py-5">
          <p class="fs-5">Seleccione un rango de fechas y presione "Generar Reporte".</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { obtenerReportePerdidas } from '@/services/mermasService.js'
import { useToast } from '@/composables/useToast.js'

const { addToast } = useToast()

const fechaDesde = ref(new Date().toISOString().slice(0, 7) + '-01')
const fechaHasta = ref(new Date().toISOString().slice(0, 10))
const cargando = ref(false)
const reporte = ref(null)

const cargarReporte = async () => {
  cargando.value = true
  reporte.value = null
  try {
    const data = await obtenerReportePerdidas({
      fecha_desde: fechaDesde.value,
      fecha_hasta: fechaHasta.value
    })
    reporte.value = data
  } catch {
    addToast('Error al cargar el reporte de pérdidas.', 'danger')
  } finally {
    cargando.value = false
  }
}
</script>
