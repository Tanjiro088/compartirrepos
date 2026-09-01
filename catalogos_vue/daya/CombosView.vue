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
  <section class="container-fluid py-4">
    <PageHeader title="Combos" subtitle="El ahorro se calcula: precio normal − precio combo.">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nuevo" data-testid="add-combo-btn">
          <i class="bi bi-plus-lg"></i> Nuevo combo
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>

    <DataTable v-else :headers="headers" :empty="!combos.length" empty-text="Sin combos">
      <tr v-for="c in combos" :key="c.id_combo">
        <td class="align-middle">
          <strong class="d-block">{{ c.nombre }}</strong>
          <small class="text-muted">{{ c.descripcion }}</small>
        </td>
        <td class="align-middle text-end">{{ money(c.precio_normal) }}</td>
        <td class="align-middle text-end">{{ money(c.precio_combo) }}</td>
        <td class="align-middle text-end fw-semibold text-success">{{ money(c.ahorro) }}</td>
        <td class="align-middle">
          <StatusBadge :variant="c.activo ? 'b-green' : 'b-gray'">{{ c.activo ? 'Activo' : 'Inactivo' }}</StatusBadge>
        </td>
        <td class="align-middle text-end">
          <button class="btn btn-outline-danger btn-sm" @click="eliminar(c)">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" title="Nuevo combo" big @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre *</label>
          <input type="text" class="form-control" v-model="form.nombre" />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Descripción</label>
          <input type="text" class="form-control" v-model="form.descripcion" />
        </div>
      </div>

      <label class="form-label fw-semibold mt-4 d-block">Productos del combo</label>
      <div v-for="(it, i) in comboProductos" :key="i" class="row g-2 align-items-center mb-2">
        <div class="col-8">
          <select class="form-select" v-model="it.id_presentacion">
            <option value="">Producto…</option>
            <option v-for="p in cat.presentaciones" :key="p.id_presentacion" :value="p.id_presentacion">
              {{ p.nombre }} ({{ money(p.precio) }})
            </option>
          </select>
        </div>
        <div class="col-3">
          <input type="number" step="0.001" class="form-control" v-model="it.cantidad" placeholder="Cant." />
        </div>
        <div class="col-1 text-end">
          <button class="btn btn-outline-danger btn-sm w-100" @click="rmItem(i)">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>

      <button class="btn btn-outline-secondary btn-sm mt-2 d-inline-flex align-items-center gap-1" @click="addItem">
        <i class="bi bi-plus"></i> Agregar producto
      </button>

      <div class="row g-3 mt-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Precio normal (auto)</label>
          <input type="text" class="form-control" :value="money(comboNormal)" disabled />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Precio combo *</label>
          <input type="number" step="0.01" class="form-control" v-model="form.precio_combo" />
        </div>
        <div class="col-12">
          <div class="form-text">
            Ahorro estimado: <strong class="text-success">{{ money(comboNormal - (form.precio_combo || 0)) }}</strong>
          </div>
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
