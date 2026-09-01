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

const anticipos = ref([]);
const empleados = ref([]);
const cat = reactive({ usuarios: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const headers = ['Folio', 'Empleado', 'Monto', 'Pagos', 'Por pago', 'Saldo', 'Estado', ''];

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
    const { data } = await axios.get(`${global.baseUrl}/anticipos`);
    anticipos.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

// Al registrar, el anticipo se descuenta por pago en la nómina del empleado.
const nuevo = () => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, { id_empleado: '', id_usuario: cat.usuarios[0]?.id_usuario || '', folio: 'ANT-' + Date.now(), monto: 0, motivo: '', numero_pagos: 1 });
  modal.value = true;
};

const guardar = async () => {
  try {
    await axios.post(`${global.baseUrl}/anticipos`, form);
    toast.value.notify('Anticipo registrado y aplicado a nómina');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const abonar = async (a) => {
  const m = prompt('Monto a abonar (deuda ' + money(a.saldo_pendiente) + ')');
  if (!m) return;
  try {
    await axios.post(`${global.baseUrl}/anticipos/${a.id_anticipo}/abonar`, { monto: parseFloat(m) });
    toast.value.notify('Abono aplicado, deuda descontada');
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
  <section class="container-fluid py-4">
    <PageHeader title="Anticipos" subtitle="Al registrar se descuenta por pago en la nómina del empleado.">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nuevo" data-testid="add-anticipo-btn">
          <i class="bi bi-plus-lg"></i> Registrar anticipo
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>

    <DataTable v-else :headers="headers" :empty="!anticipos.length" empty-text="Sin anticipos">
      <tr v-for="a in anticipos" :key="a.id_anticipo">
        <td class="align-middle">{{ a.folio }}</td>
        <td class="align-middle">{{ a.empleado }}</td>
        <td class="align-middle text-end fw-semibold">{{ money(a.monto) }}</td>
        <td class="align-middle">{{ a.numero_pagos }}</td>
        <td class="align-middle text-end fw-semibold">{{ money(a.monto_por_pago) }}</td>
        <td class="align-middle text-end fw-semibold text-danger">{{ money(a.saldo_pendiente) }}</td>
        <td class="align-middle"><StatusBadge :estado="a.estado" /></td>
        <td class="align-middle text-end">
          <button class="btn btn-outline-primary btn-sm" @click="abonar(a)">
            <i class="bi bi-cash"></i> Abonar
          </button>
        </td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Registrar anticipo" @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Empleado *</label>
          <select class="form-select" v-model="form.id_empleado" data-testid="anticipo-empleado">
            <option value="">—</option>
            <option v-for="e in empleados" :key="e.id_empleado" :value="e.id_empleado">{{ e.persona }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Folio *</label>
          <input type="text" class="form-control" v-model="form.folio" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Monto *</label>
          <input type="number" step="0.01" class="form-control" v-model="form.monto" data-testid="anticipo-monto" />
          <div class="form-text">Máx. 50% del salario mensual.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">N° de pagos *</label>
          <input type="number" min="1" class="form-control" v-model="form.numero_pagos" />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Motivo</label>
          <input type="text" class="form-control" v-model="form.motivo" />
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
