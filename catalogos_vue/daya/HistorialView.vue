<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { money } from '../helpers';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import StatusBadge from '../components/StatusBadge.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const empleados = ref([]);
const empSel = ref('');
const historial = ref(null);
const loading = ref(false);

const headers = ['Fecha', 'Tipo', 'Monto', 'Método', 'Referencia'];

const loadEmpleados = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}/empleados`);
    empleados.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const consultar = async () => {
  if (!empSel.value) return;
  loading.value = true;
  try {
    const { data } = await axios.get(`${global.baseUrl}/empleados/${empSel.value}/historial`);
    historial.value = data;
  } catch (e) {
    toast.value.apiErr(e);
  }
  loading.value = false;
};

onMounted(loadEmpleados);
</script>

<template>
  <section>
    <PageHeader title="Historial de pagos" subtitle="Nóminas, préstamos y anticipos por empleado." />

    <div class="toolbar">
      <select v-model="empSel" class="search" data-testid="hist-emp-select">
        <option value="">Selecciona empleado…</option>
        <option v-for="e in empleados" :key="e.id_empleado" :value="e.id_empleado">{{ e.persona }}</option>
      </select>
      <button class="btn btn-brand" @click="consultar"><i class="bi bi-search"></i> Consultar</button>
    </div>

    <div v-if="loading" class="spinner"></div>

    <div v-else-if="historial">
      <div class="stats">
        <div class="stat" v-for="(t, k) in historial.resumen" :key="k">
          <div class="n money">{{ money(t) }}</div>
          <div class="l">Total {{ k }}</div>
        </div>
      </div>
      <DataTable :headers="headers" :empty="!historial.historial.length" empty-text="Sin movimientos">
        <tr v-for="h in historial.historial" :key="h.id_historial">
          <td>{{ h.fecha_pago }}</td>
          <td><StatusBadge variant="b-blue">{{ h.tipo_pago }}</StatusBadge></td>
          <td class="money">{{ money(h.monto) }}</td>
          <td>{{ h.metodo_pago }}</td>
          <td>{{ h.referencia }}</td>
        </tr>
      </DataTable>
    </div>

    <div v-else class="empty">Selecciona un empleado para ver su historial.</div>

    <ToastNotification ref="toast" />
  </section>
</template>
