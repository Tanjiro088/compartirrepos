<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import ModalBase from '../components/ModalBase.vue';
import StatusBadge from '../components/StatusBadge.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const series = ref([]);
const cat = reactive({ presentaciones: [], almacenes: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const serieQuery = ref('');
const serieConsulta = ref(null);

const headers = ['Serie', 'Producto', 'Almacén', 'Estado', 'Ingreso'];

const loadCat = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}/catalogos`);
    Object.assign(cat, data);
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(`${global.baseUrl}/series`);
    series.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const nueva = () => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, { id_presentacion: '', id_almacen: '', numero_serie: '', fecha_ingreso: '' });
  modal.value = true;
};

const guardar = async () => {
  try {
    await axios.post(`${global.baseUrl}/series`, form);
    toast.value.notify('Serie registrada');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const consultar = async () => {
  if (!serieQuery.value) return;
  try {
    const { data } = await axios.get(`${global.baseUrl}/series/consultar/${encodeURIComponent(serieQuery.value)}`);
    serieConsulta.value = data;
  } catch (e) {
    serieConsulta.value = null;
    toast.value.apiErr(e);
  }
};

onMounted(() => {
  loadCat();
  load();
});
</script>

<template>
  <section class="container-fluid py-4">
    <PageHeader title="Series de producto" subtitle="Trazabilidad por número de serie (no se duplica).">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nueva" data-testid="add-serie-btn">
          <i class="bi bi-plus-lg"></i> Registrar serie
        </button>
      </template>
    </PageHeader>

    <div class="row g-3 mb-4">
      <div class="col-md-6 col-lg-4">
        <input v-model="serieQuery" class="form-control" placeholder="Buscar por número de serie…" data-testid="serie-search" />
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-primary d-inline-flex align-items-center gap-2" @click="consultar">
          <i class="bi bi-search"></i> Consultar
        </button>
      </div>
    </div>

    <div v-if="serieConsulta" class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h4 class="h5 fw-bold mb-0">{{ serieConsulta.numero_serie }} · {{ serieConsulta.producto }}</h4>
          <StatusBadge :estado="serieConsulta.estado" />
        </div>
        <div v-if="serieConsulta.garantias?.length" class="mt-3">
          <div class="text-muted small mb-2 fw-semibold">Garantías asociadas:</div>
          <div v-for="ga in serieConsulta.garantias" :key="ga.folio" class="text-muted small mb-1 d-flex align-items-center gap-2">
            <span>• {{ ga.folio }} — {{ ga.diagnostico }}</span>
            <StatusBadge :estado="ga.estado" />
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>
    
    <DataTable v-else :headers="headers" :empty="!series.length" empty-text="Sin series">
      <tr v-for="s in series" :key="s.id_serie">
        <td class="align-middle fw-semibold">{{ s.numero_serie }}</td>
        <td class="align-middle">{{ s.producto }}</td>
        <td class="align-middle">{{ s.almacen }}</td>
        <td class="align-middle"><StatusBadge :estado="s.estado" /></td>
        <td class="align-middle text-muted small">{{ s.fecha_ingreso }}</td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Registrar número de serie" @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Producto *</label>
          <select class="form-select" v-model="form.id_presentacion">
            <option value="">—</option>
            <option v-for="p in cat.presentaciones" :key="p.id_presentacion" :value="p.id_presentacion">{{ p.nombre }}</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Almacén *</label>
          <select class="form-select" v-model="form.id_almacen">
            <option value="">—</option>
            <option v-for="a in cat.almacenes" :key="a.id_almacen" :value="a.id_almacen">{{ a.nombre }}</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Número de serie *</label>
          <input type="text" class="form-control" v-model="form.numero_serie" />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Fecha ingreso</label>
          <input type="date" class="form-control" v-model="form.fecha_ingreso" />
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
