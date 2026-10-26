<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { money } from '../helpers';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import ModalBase from '../components/ModalBase.vue';
import StatusBadge from '../components/StatusBadge.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const API_URL = `${global.baseUrl}prestamos`;
const toast = ref(null);

const prestamos = ref([]);
const empleados = ref([]);
const cat = reactive({ usuarios: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const headers = ['Folio', 'Empleado', 'Total', 'Pagado', 'Saldo', 'Estado'];

// Propiedad computada para filtrar únicamente empleados activos en el select
const empleadosActivos = computed(() => {
  return empleados.value.filter(e => e.activo);
});

const loadCat = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}catalogos`);
    Object.assign(cat, data);
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const loadEmpleados = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}empleados`);
    empleados.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(API_URL);
    prestamos.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const nuevo = () => {
  Object.keys(form).forEach((k) => delete form[k]);
  const fechaHoy = new Date().toISOString().split('T')[0];
  Object.assign(form, { 
    id_empleado: '', 
    id_usuario: cat.usuarios[0]?.id_usuario || '', 
    folio: 'PRES-' + Date.now(), 
    monto_total: 0, 
    tasa_interes: 0, 
    plazo_meses: 6, 
    fecha_inicio: fechaHoy, 
    fecha_vencimiento: '', 
    motivo: '' 
  });
  modal.value = true;
};

const guardar = async () => {
  try {
    await axios.post(API_URL, form);
    toast.value.notify('Préstamo registrado y cuota aplicada a nómina');
    modal.value = false;
    load();
  } catch (e) {
    toast.value?.apiErr(e);
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
    <PageHeader title="Préstamos" subtitle="Al registrar un préstamo se descuenta automáticamente de las nóminas según el plazo pactado.">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nuevo" data-testid="add-prestamo-btn">
          <i class="bi bi-plus-lg"></i> Registrar préstamo
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>
    
    <DataTable v-else :headers="headers" :empty="!prestamos.length" empty-text="Sin préstamos">
      <tr v-for="p in prestamos" :key="p.id_prestamo">
        <td class="align-middle fw-semibold">{{ p.folio }}</td>
        <td class="align-middle">{{ p.empleado }}</td>
        <td class="align-middle">{{ money(p.monto_total) }}</td>
        <td class="align-middle text-muted">{{ money(p.monto_pagado) }}</td>
        <td class="align-middle fw-bold text-danger">{{ money(p.saldo_pendiente) }}</td>
        <td class="align-middle"><StatusBadge :estado="p.estado" /></td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Registrar préstamo" big @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Empleado *</label>
          <select class="form-select" v-model="form.id_empleado" data-testid="prestamo-empleado">
            <option value="">—</option>
            <option v-for="e in empleadosActivos" :key="e.id_empleado" :value="e.id_empleado">
              {{ e.persona.nombre }} {{ e.persona.apellido_paterno }} {{ e.persona.apellido_materno }}
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Folio *</label>
          <input type="text" class="form-control" v-model="form.folio" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Monto total *</label>
          <input type="number" step="0.01" class="form-control" v-model="form.monto_total" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Tasa interés (%)</label>
          <input type="number" step="0.01" class="form-control" v-model="form.tasa_interes" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Plazo (meses) *</label>
          <input type="number" min="1" class="form-control" v-model="form.plazo_meses" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Fecha inicio *</label>
          <input type="date" class="form-control" v-model="form.fecha_inicio" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Fecha vencimiento *</label>
          <input type="date" class="form-control" v-model="form.fecha_vencimiento" />
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
