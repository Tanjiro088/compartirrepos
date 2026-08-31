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

const prestamos = ref([]);
const empleados = ref([]);
const cat = reactive({ usuarios: [], formas_pago: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const headers = ['Folio', 'Empleado', 'Total', 'Pagado', 'Saldo', 'Estado', ''];

const loadCat = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}/catalogos`);
    Object.assign(cat, data);
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

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
    const { data } = await axios.get(`${global.baseUrl}/prestamos`);
    prestamos.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const nuevo = () => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, { id_empleado: '', id_usuario: cat.usuarios[0]?.id_usuario || '', folio: 'PRES-' + Date.now(), monto_total: 0, tasa_interes: 0, plazo_meses: 6, fecha_inicio: '', fecha_vencimiento: '', motivo: '' });
  modal.value = true;
};

const guardar = async () => {
  try {
    await axios.post(`${global.baseUrl}/prestamos`, form);
    toast.value.notify('Préstamo registrado, cuota aplicada a nómina');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

// Registrar un pago descuenta del saldo pendiente (deuda).
const pagar = async (p) => {
  const m = prompt('Monto del pago (saldo ' + money(p.saldo_pendiente) + ')');
  if (!m) return;
  const idUsuario = cat.usuarios[0]?.id_usuario;
  const idForma = cat.formas_pago[0]?.id_forma_pago;
  try {
    await axios.post(`${global.baseUrl}/prestamos/${p.id_prestamo}/pagar`, { monto: parseFloat(m), id_forma_pago: idForma, id_usuario: idUsuario, referencia: 'ABONO' });
    toast.value.notify('Pago registrado, deuda descontada');
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

onMounted(() => {
  loadCat();
  loadEmpleados();
  load();
});
</script>

<template>
  <section>
    <PageHeader title="Préstamos" subtitle="Al registrar un pago se descuenta de la deuda (saldo pendiente).">
      <template #action>
        <button class="btn btn-brand" @click="nuevo" data-testid="add-prestamo-btn"><i class="bi bi-plus-lg"></i>Registrar préstamo</button>
      </template>
    </PageHeader>

    <div v-if="loading" class="spinner"></div>
    <DataTable v-else :headers="headers" :empty="!prestamos.length" empty-text="Sin préstamos">
      <tr v-for="p in prestamos" :key="p.id_prestamo">
        <td>{{ p.folio }}</td>
        <td>{{ p.empleado }}</td>
        <td class="money">{{ money(p.monto_total) }}</td>
        <td class="money">{{ money(p.monto_pagado) }}</td>
        <td class="money">{{ money(p.saldo_pendiente) }}</td>
        <td><StatusBadge :estado="p.estado" /></td>
        <td style="text-align:right">
          <button class="btn btn-sm btn-brand" :disabled="p.estado === 'pagado'" @click="pagar(p)"><i class="bi bi-cash-coin"></i> Registrar pago</button>
        </td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Registrar préstamo" big @close="modal = false" @save="guardar">
      <div class="form-grid">
        <div class="field"><label>Empleado *</label>
          <select v-model="form.id_empleado" data-testid="prestamo-empleado"><option value="">—</option>
            <option v-for="e in empleados" :key="e.id_empleado" :value="e.id_empleado">{{ e.persona }}</option></select></div>
        <div class="field"><label>Folio *</label><input v-model="form.folio" /></div>
        <div class="field"><label>Monto total *</label><input type="number" step="0.01" v-model="form.monto_total" /></div>
        <div class="field"><label>Tasa interés (%)</label><input type="number" step="0.01" v-model="form.tasa_interes" /></div>
        <div class="field"><label>Plazo (meses) *</label><input type="number" min="1" v-model="form.plazo_meses" /></div>
        <div class="field"><label>Fecha inicio *</label><input type="date" v-model="form.fecha_inicio" /></div>
        <div class="field"><label>Fecha vencimiento *</label><input type="date" v-model="form.fecha_vencimiento" /></div>
        <div class="field full"><label>Motivo</label><input v-model="form.motivo" /></div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
