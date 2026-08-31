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
  <section>
    <PageHeader title="Reporte de garantías" subtitle="Productos con mayor índice de fallas, motivos y estados." />

    <div v-if="reporte">
      <div class="stats">
        <div class="stat">
          <i class="bi bi-shield-check"></i>
          <div class="n">{{ reporte.total_garantias }}</div>
          <div class="l">Total garantías</div>
        </div>
        <div class="stat" v-for="(t, k) in reporte.estados" :key="k">
          <div class="n">{{ t }}</div>
          <div class="l">{{ k }}</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="card">
          <div class="card-pad"><h4 style="margin:0 0 4px">Productos más fallados</h4></div>
          <table>
            <thead><tr><th>Producto</th><th>Garantías</th><th>%</th></tr></thead>
            <tbody>
              <tr v-for="p in reporte.productos" :key="p.producto">
                <td>{{ p.producto }}</td>
                <td>{{ p.garantias }}</td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div style="flex:1;height:6px;background:var(--line);border-radius:4px;overflow:hidden">
                      <div :style="{ width: p.porcentaje + '%', height: '100%', background: 'var(--brand)' }"></div>
                    </div>
                    <span class="sub">{{ p.porcentaje }}%</span>
                  </div>
                </td>
              </tr>
              <tr v-if="!reporte.productos.length"><td colspan="3" class="empty">Sin datos</td></tr>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-pad"><h4 style="margin:0 0 4px">Motivos más comunes</h4></div>
          <table>
            <thead><tr><th>Motivo (diagnóstico)</th><th>Cantidad</th></tr></thead>
            <tbody>
              <tr v-for="m in reporte.motivos" :key="m.motivo">
                <td>{{ m.motivo }}</td>
                <td>{{ m.cantidad }}</td>
              </tr>
              <tr v-if="!reporte.motivos.length"><td colspan="2" class="empty">Sin datos</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-else class="empty">Cargando reporte…</div>

    <ToastNotification ref="toast" />
  </section>
</template>
