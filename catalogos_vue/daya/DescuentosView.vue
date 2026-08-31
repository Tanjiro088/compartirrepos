<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { money } from '../helpers';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import ModalBase from '../components/ModalBase.vue';
import StatusBadge from '../components/StatusBadge.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const descuentos = ref([]);
const empleados = ref([]);
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const headers = ['Empleado', 'Concepto', 'Tipo', 'Valor', 'Frecuencia', 'Estado'];

const loadEmpleados = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}/empleados`);
    empleados.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(`${global.baseUrl}/descuentos-automaticos`);
    descuentos.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const nuevo = () => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, { id_empleado: '', concepto: '', tipo: 'monto_fijo', valor: 0, frecuencia: 'mensual', fecha_inicio: '', fecha_fin: '' });
  modal.value = true;
};

const guardar = async () => {
  try {
    await axios.post(`${global.baseUrl}/descuentos-automaticos`, form);
    toast.value.notify('Descuento configurado y aplicado');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

onMounted(() => {
  loadEmpleados();
  load();
});
</script>

<template>
  <section>
    <PageHeader title="Descuentos automáticos" subtitle="Recurrentes por porcentaje o monto fijo, aplicados a la nómina.">
      <template #action>
        <button class="btn btn-brand" @click="nuevo" data-testid="add-descuento-btn"><i class="bi bi-plus-lg"></i>Nuevo descuento</button>
      </template>
    </PageHeader>

    <div v-if="loading" class="spinner"></div>
    <DataTable v-else :headers="headers" :empty="!descuentos.length" empty-text="Sin descuentos">
      <tr v-for="d in descuentos" :key="d.id_descuento_automatico">
        <td>{{ d.empleado }}</td>
        <td>{{ d.concepto }}</td>
        <td><StatusBadge variant="b-blue">{{ d.tipo }}</StatusBadge></td>
        <td>{{ d.tipo === 'porcentaje' ? d.valor + '%' : money(d.valor) }}</td>
        <td>{{ d.frecuencia }}</td>
        <td><StatusBadge :variant="d.activo ? 'b-green' : 'b-gray'">{{ d.activo ? 'Activo' : 'Inactivo' }}</StatusBadge></td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Nuevo descuento automático" @close="modal = false" @save="guardar">
      <div class="form-grid">
        <div class="field"><label>Empleado *</label>
          <select v-model="form.id_empleado">
            <option value="">—</option>
            <option v-for="e in empleados" :key="e.id_empleado" :value="e.id_empleado">
              {{ e.persona?.nombre ?? e.nombre }} {{ e.persona?.apellido_paterno ?? e.apellido_paterno ?? '' }} {{ e.persona?.apellido_materno ?? e.apellido_materno ?? '' }}
            </option>
          </select>
        </div>
        <div class="field"><label>Concepto *</label><input v-model="form.concepto" /></div>
        <div class="field"><label>Tipo</label>
          <select v-model="form.tipo"><option value="monto_fijo">Monto fijo</option><option value="porcentaje">Porcentaje</option></select></div>
        <div class="field"><label>Valor *</label><input type="number" step="0.01" v-model="form.valor" /></div>
        <div class="field"><label>Frecuencia</label>
          <select v-model="form.frecuencia"><option>mensual</option><option>quincenal</option><option>unico</option></select></div>
        <div class="field"><label>Fecha inicio *</label><input type="date" v-model="form.fecha_inicio" /></div>
        <div class="field"><label>Fecha fin</label><input type="date" v-model="form.fecha_fin" /></div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>