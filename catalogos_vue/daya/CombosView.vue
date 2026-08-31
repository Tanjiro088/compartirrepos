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
const toast = ref(null);

const combos = ref([]);
const cat = reactive({ presentaciones: [] });
const loading = ref(false);
const modal = ref(false);
const form = reactive({});
const comboProductos = ref([]);

const headers = ['Nombre', 'Precio normal', 'Precio combo', 'Ahorro', 'Estado', ''];

// El precio normal se calcula sumando precio × cantidad de cada producto.
const comboNormal = computed(() =>
  comboProductos.value.reduce((s, it) => {
    const p = cat.presentaciones.find((x) => x.id_presentacion == it.id_presentacion);
    return s + Number(p?.precio || 0) * Number(it.cantidad || 0);
  }, 0)
);

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
    const { data } = await axios.get(`${global.baseUrl}/combos`);
    combos.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const nuevo = () => {
  comboProductos.value = [{ id_presentacion: '', cantidad: 1 }];
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, { nombre: '', descripcion: '', precio_combo: 0 });
  modal.value = true;
};

const addItem = () => comboProductos.value.push({ id_presentacion: '', cantidad: 1 });
const rmItem = (i) => comboProductos.value.splice(i, 1);

const guardar = async () => {
  try {
    await axios.post(`${global.baseUrl}/combos`, { ...form, productos: comboProductos.value });
    toast.value.notify('Combo creado');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const eliminar = async (c) => {
  if (!confirm('¿Eliminar combo?')) return;
  try {
    await axios.delete(`${global.baseUrl}/combos/${c.id_combo}`);
    toast.value.notify('Combo eliminado');
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
    <PageHeader title="Combos" subtitle="El ahorro se calcula: precio normal − precio combo.">
      <template #action>
        <button class="btn btn-brand" @click="nuevo" data-testid="add-combo-btn"><i class="bi bi-plus-lg"></i>Nuevo combo</button>
      </template>
    </PageHeader>

    <div v-if="loading" class="spinner"></div>
    <DataTable v-else :headers="headers" :empty="!combos.length" empty-text="Sin combos">
      <tr v-for="c in combos" :key="c.id_combo">
        <td><strong>{{ c.nombre }}</strong><div class="sub">{{ c.descripcion }}</div></td>
        <td class="money">{{ money(c.precio_normal) }}</td>
        <td class="money">{{ money(c.precio_combo) }}</td>
        <td class="money" style="color:var(--brand)">{{ money(c.ahorro) }}</td>
        <td><StatusBadge :variant="c.activo ? 'b-green' : 'b-gray'">{{ c.activo ? 'Activo' : 'Inactivo' }}</StatusBadge></td>
        <td style="text-align:right">
          <button class="btn btn-ghost btn-sm btn-danger" @click="eliminar(c)"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Nuevo combo" big @close="modal = false" @save="guardar">
      <div class="form-grid">
        <div class="field full"><label>Nombre *</label><input v-model="form.nombre" /></div>
        <div class="field full"><label>Descripción</label><input v-model="form.descripcion" /></div>
      </div>
      <label style="margin-top:14px;display:block">Productos del combo</label>
      <div v-for="(it, i) in comboProductos" :key="i" style="display:flex;gap:8px;margin-top:8px">
        <select v-model="it.id_presentacion" style="flex:2"><option value="">Producto…</option>
          <option v-for="p in cat.presentaciones" :key="p.id_presentacion" :value="p.id_presentacion">{{ p.nombre }} ({{ money(p.precio) }})</option></select>
        <input type="number" step="0.001" v-model="it.cantidad" style="flex:1" placeholder="Cant." />
        <button class="btn btn-ghost btn-danger" @click="rmItem(i)"><i class="bi bi-x-lg"></i></button>
      </div>
      <button class="btn btn-sm" style="margin-top:10px" @click="addItem"><i class="bi bi-plus"></i> Agregar producto</button>
      <div class="form-grid" style="margin-top:14px">
        <div class="field"><label>Precio normal (auto)</label><input :value="money(comboNormal)" disabled /></div>
        <div class="field"><label>Precio combo *</label><input type="number" step="0.01" v-model="form.precio_combo" /></div>
        <div class="field full"><span class="hint">Ahorro estimado: <strong>{{ money(comboNormal - (form.precio_combo || 0)) }}</strong></span></div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
