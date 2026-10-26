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
const API_URL = `${global.baseUrl}anticipos`;
const toast = ref(null);

const anticipos = ref([]);
const empleados = ref([]);
const cat = reactive({ usuarios: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const headers = ['Folio', 'Empleado', 'Monto', 'Pagos', 'Por pago', 'Saldo', 'Estado'];

// Propiedad computada para filtrar únicamente empleados activos
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
    anticipos.value = data;
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
    folio: 'ANT-' + Date.now(), 
    fecha_solicitud: fechaHoy,
    fecha_autorizacion: fechaHoy,
    monto: 0, 
    motivo: '', 
    numero_pagos: 1 
  });
  modal.value = true;
};

const guardar = async () => {
  try {
    await axios.post(API_URL, form);
    toast.value.notify('Anticipo registrado y programado para descuento en nómina');
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
    <PageHeader title="Anticipos" subtitle="Al registrar se descuenta automáticamente de las nóminas según los pagos pactados.">
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
      </tr>
    </DataTable>

    <!-- Modal Registrar Anticipo -->
    <ModalBase v-if="modal" title="Registrar anticipo" @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Empleado *</label>
          <select class="form-select" v-model="form.id_empleado" data-testid="anticipo-empleado">
            <option value="">—</option>
            <!-- Usamos la lista filtrada de empleados activos -->
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
          <label class="form-label fw-semibold">Fecha solicitud *</label>
          <input type="date" class="form-control" v-model="form.fecha_solicitud" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Fecha autorización *</label>
          <input type="date" class="form-control" v-model="form.fecha_autorizacion" />
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
