<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const reporte = ref(null);

const load = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}/garantias/reporte`);
    reporte.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

onMounted(load);
</script>

<template>
  <section class="container-fluid py-4">
    <PageHeader title="Reporte de garantías" subtitle="Productos con mayor índice de fallas, motivos y estados." />

    <div v-if="reporte">
      <div class="row g-3 mb-4">
        <div class="col-md-4 col-lg-3">
          <div class="card border-0 shadow-sm p-3 bg-light h-100">
            <div class="d-flex align-items-center gap-2 mb-2 text-primary">
              <i class="bi bi-shield-check fs-4"></i>
              <span class="text-muted small fw-semibold">Total garantías</span>
            </div>
            <div class="fs-3 fw-bold">{{ reporte.total_garantias }}</div>
          </div>
        </div>
        <div class="col-md-4 col-lg-3" v-for="(t, k) in reporte.estados" :key="k">
          <div class="card border-0 shadow-sm p-3 bg-light h-100">
            <div class="text-muted small fw-semibold text-capitalize mb-2">{{ k }}</div>
            <div class="fs-3 fw-bold text-primary">{{ t }}</div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-6">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h4 class="h5 fw-bold mb-3">Productos más fallados</h4>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Producto</th>
                      <th>Garantías</th>
                      <th style="width: 40%;">%</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="p in reporte.productos" :key="p.producto">
                      <td class="fw-semibold">{{ p.producto }}</td>
                      <td>{{ p.garantias }}</td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <div class="progress flex-grow-1" style="height: 6px;">
                            <div class="progress-bar bg-primary" :style="{ width: p.porcentaje + '%' }" role="progressbar"></div>
                          </div>
                          <span class="text-muted small">{{ p.porcentaje }}%</span>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="!reporte.productos.length">
                      <td colspan="3" class="text-center py-4 text-muted fst-italic">Sin datos</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="card border-0 shadow-sm">
            <div class="card-body">
              <h4 class="h5 fw-bold mb-3">Motivos más comunes</h4>
              <div class="table-responsive">
                <table class="table align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Motivo (diagnóstico)</th>
                      <th>Cantidad</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="m in reporte.motivos" :key="m.motivo">
                      <td class="fw-semibold">{{ m.motivo }}</td>
                      <td>{{ m.cantidad }}</td>
                    </tr>
                    <tr v-if="!reporte.motivos.length">
                      <td colspan="2" class="text-center py-4 text-muted fst-italic">Sin datos</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-5 text-muted fst-italic">
      Cargando reporte…
    </div>

    <ToastNotification ref="toast" />
  </section>
</template>
