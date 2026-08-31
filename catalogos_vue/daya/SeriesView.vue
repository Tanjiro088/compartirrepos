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
  <section>
    <PageHeader title="Series de producto" subtitle="Trazabilidad por número de serie (no se duplica).">
      <template #action>
        <button class="btn btn-brand" @click="nueva" data-testid="add-serie-btn"><i class="bi bi-plus-lg"></i>Registrar serie</button>
      </template>
    </PageHeader>

    <div class="toolbar">
      <input v-model="serieQuery" class="search" placeholder="Buscar por número de serie…" data-testid="serie-search" />
      <button class="btn" @click="consultar"><i class="bi bi-search"></i> Consultar</button>
    </div>

    <div v-if="serieConsulta" class="card card-pad" style="margin-bottom:16px">
      <h4 style="margin:0 0 6px">{{ serieConsulta.numero_serie }} · {{ serieConsulta.producto }}</h4>
      <StatusBadge :estado="serieConsulta.estado" />
      <div v-if="serieConsulta.garantias?.length" style="margin-top:12px">
        <div class="sub" style="margin-bottom:6px">Garantías asociadas:</div>
        <div v-for="ga in serieConsulta.garantias" :key="ga.folio" class="sub">
          • {{ ga.folio }} — {{ ga.diagnostico }} <StatusBadge :estado="ga.estado" />
        </div>
      </div>
    </div>

    <div v-if="loading" class="spinner"></div>
    <DataTable v-else :headers="headers" :empty="!series.length" empty-text="Sin series">
      <tr v-for="s in series" :key="s.id_serie">
        <td><strong>{{ s.numero_serie }}</strong></td>
        <td>{{ s.producto }}</td>
        <td>{{ s.almacen }}</td>
        <td><StatusBadge :estado="s.estado" /></td>
        <td class="sub">{{ s.fecha_ingreso }}</td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Registrar número de serie" @close="modal = false" @save="guardar">
      <div class="form-grid">
        <div class="field"><label>Producto *</label>
          <select v-model="form.id_presentacion"><option value="">—</option>
            <option v-for="p in cat.presentaciones" :key="p.id_presentacion" :value="p.id_presentacion">{{ p.nombre }}</option></select></div>
        <div class="field"><label>Almacén *</label>
          <select v-model="form.id_almacen"><option value="">—</option>
            <option v-for="a in cat.almacenes" :key="a.id_almacen" :value="a.id_almacen">{{ a.nombre }}</option></select></div>
        <div class="field"><label>Número de serie *</label><input v-model="form.numero_serie" /></div>
        <div class="field"><label>Fecha ingreso</label><input type="date" v-model="form.fecha_ingreso" /></div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
