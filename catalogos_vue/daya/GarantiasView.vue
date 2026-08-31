<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import ModalBase from '../components/ModalBase.vue';
import StatusBadge from '../components/StatusBadge.vue';
import TableToolbar from '../components/TableToolbar.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const garantias = ref([]);
const cat = reactive({ ventas: [], clientes: [], usuarios: [], presentaciones: [], almacenes: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});

const headers = ['Folio', 'Cliente', 'Tipo', 'Vence', 'Estado', ''];

// Búsqueda y filtro por estado.
const q = ref('');
const estadoFilter = ref('');
const estadosGarantia = [
  { value: 'activa', label: 'Activa' },
  { value: 'en_proceso', label: 'En proceso' },
  { value: 'reparada', label: 'Reparada' },
  { value: 'reemplazada', label: 'Reemplazada' },
  { value: 'vencida', label: 'Vencida' },
  { value: 'cancelada', label: 'Cancelada' },
];
const garantiasFiltradas = computed(() => {
  const term = q.value.trim().toLowerCase();
  return garantias.value.filter((g) => {
    const coincide = !term || [g.folio, g.cliente, g.tipo_garantia]
      .some((v) => String(v || '').toLowerCase().includes(term));
    const estadoOk = !estadoFilter.value || g.estado === estadoFilter.value;
    return coincide && estadoOk;
  });
});

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
    const { data } = await axios.get(`${global.baseUrl}/garantias`);
    garantias.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const nueva = () => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, { id_venta: '', id_cliente: '', id_usuario: cat.usuarios[0]?.id_usuario || '', folio: 'GAR-' + Date.now(), fecha_garantia: '', tipo_garantia: 'tienda', dias_garantia: 90, observaciones: '', d_presentacion: '', d_serie: '', d_diagnostico: '', d_solucion: '' });
  modal.value = true;
};

const guardar = async () => {
  const payload = { ...form, detalles: [{ id_presentacion: form.d_presentacion, id_serie: form.d_serie || null, diagnostico: form.d_diagnostico, solucion: form.d_solucion }] };
  try {
    await axios.post(`${global.baseUrl}/garantias`, payload);
    toast.value.notify('Garantía registrada');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const reposicion = async (g) => {
  if (g.estado === 'reemplazada') {
    toast.value.notify('Ya reemplazada', true);
    return;
  }
  const ns = prompt('Nuevo número de serie para reposición');
  if (!ns) return;
  const pres = cat.presentaciones[0]?.id_presentacion;
  const alm = cat.almacenes[0]?.id_almacen;
  const us = cat.usuarios[0]?.id_usuario;
  try {
    await axios.post(`${global.baseUrl}/garantias/${g.id_garantia}/reposicion`, { id_usuario: us, nuevo_serie: ns, id_presentacion: pres, id_almacen: alm, observaciones: 'Reposición desde UI' });
    toast.value.notify('Reposición registrada');
    load();
  } catch (e) {
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
    <PageHeader title="Garantías" subtitle="Registro, seguimiento y reposición de productos.">
      <template #action>
        <button class="btn btn-brand" @click="nueva" data-testid="add-garantia-btn"><i class="bi bi-plus-lg"></i>Registrar garantía</button>
      </template>
    </PageHeader>

    <div v-if="loading" class="spinner"></div>
    <template v-else>
      <TableToolbar
        v-model:search="q"
        v-model:estado="estadoFilter"
        placeholder="Buscar por folio, cliente o tipo…"
        :estados="estadosGarantia"
        id-prefix="garantias"
      />
      <DataTable :headers="headers" :empty="!garantiasFiltradas.length" empty-text="Sin garantías">
        <tr v-for="g in garantiasFiltradas" :key="g.id_garantia">
          <td>{{ g.folio }}</td>
          <td>{{ g.cliente }}</td>
          <td><StatusBadge variant="b-blue">{{ g.tipo_garantia }}</StatusBadge></td>
          <td class="sub">{{ g.fecha_vencimiento }}</td>
          <td><StatusBadge :estado="g.estado" /></td>
          <td style="text-align:right">
            <button class="btn btn-ghost btn-sm" @click="reposicion(g)"><i class="bi bi-arrow-repeat"></i> Reposición</button>
          </td>
        </tr>
      </DataTable>
    </template>

    <ModalBase v-if="modal" title="Registrar garantía" big @close="modal = false" @save="guardar">
      <div class="form-grid">
        <div class="field"><label>Venta *</label>
          <select v-model="form.id_venta"><option value="">—</option>
            <option v-for="v in cat.ventas" :key="v.id_venta" :value="v.id_venta">Venta #{{ v.id_venta }}</option></select></div>
        <div class="field"><label>Cliente *</label>
          <select v-model="form.id_cliente"><option value="">—</option>
            <option v-for="c in cat.clientes" :key="c.id_cliente" :value="c.id_cliente">{{ c.nombre }}</option></select></div>
        <div class="field"><label>Folio *</label><input v-model="form.folio" /></div>
        <div class="field"><label>Fecha garantía *</label><input type="date" v-model="form.fecha_garantia" /></div>
        <div class="field"><label>Tipo</label>
          <select v-model="form.tipo_garantia"><option>fabricante</option><option>tienda</option><option>extendida</option></select></div>
        <div class="field"><label>Días garantía</label><input type="number" v-model="form.dias_garantia" /></div>
        <div class="field"><label>Producto *</label>
          <select v-model="form.d_presentacion"><option value="">—</option>
            <option v-for="p in cat.presentaciones" :key="p.id_presentacion" :value="p.id_presentacion">{{ p.nombre }}</option></select></div>
        <div class="field"><label>Serie (opcional)</label><input type="number" v-model="form.d_serie" placeholder="id_serie" /></div>
        <div class="field full"><label>Diagnóstico</label><input v-model="form.d_diagnostico" /></div>
        <div class="field full"><label>Solución</label><input v-model="form.d_solucion" /></div>
        <div class="field full"><label>Observaciones</label><textarea v-model="form.observaciones" rows="2"></textarea></div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
