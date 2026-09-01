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
  <section class="container-fluid py-4">
    <PageHeader title="Historial de pagos" subtitle="Nóminas, préstamos y anticipos por empleado." />

    <div class="row g-3 mb-4 align-items-center">
      <div class="col-md-6 col-lg-4">
        <select v-model="empSel" class="form-select" data-testid="hist-emp-select">
          <option value="">Selecciona empleado…</option>
          <option v-for="e in empleados" :key="e.id_empleado" :value="e.id_empleado">{{ e.persona }}</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="consultar">
          <i class="bi bi-search"></i> Consultar
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>

    <div v-else-if="historial">
      <div class="row g-3 mb-4">
        <div class="col-md-4" v-for="(t, k) in historial.resumen" :key="k">
          <div class="card border-0 shadow-sm p-3 bg-light">
            <div class="fs-4 fw-bold text-primary">{{ money(t) }}</div>
            <div class="text-muted small text-capitalize">Total {{ k }}</div>
          </div>
        </div>
      </div>

      <DataTable :headers="headers" :empty="!historial.historial.length" empty-text="Sin movimientos">
        <tr v-for="h in historial.historial" :key="h.id_historial">
          <td class="align-middle text-muted">{{ h.fecha_pago }}</td>
          <td class="align-middle">
            <StatusBadge variant="b-blue">{{ h.tipo_pago }}</StatusBadge>
          </td>
          <td class="align-middle fw-semibold">{{ money(h.monto) }}</td>
          <td class="align-middle">{{ h.metodo_pago }}</td>
          <td class="align-middle">{{ h.referencia }}</td>
        </tr>
      </DataTable>
    </div>

    <div v-else class="text-center py-5 text-muted fst-italic">
      Selecciona un empleado para ver su historial.
    </div>

    <ToastNotification ref="toast" />
  </section>
</template>
