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

const promociones = ref([]);
const cat = reactive({ tipos_promocion: [], presentaciones: [], sucursales: [] });
const loading = ref(false);
const modal = ref(false);
const modalTitle = ref('');
const form = reactive({});

// Selecciones múltiples de la promoción.
const promoDias = ref([]);
const promoPres = ref([]);
const promoSuc = ref([]);
const diasSemana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

const headers = ['Nombre', 'Vigencia', 'Prioridad', 'Acumulable', 'Estado', ''];

const toggleArr = (arr, val) => {
  const i = arr.value.indexOf(val);
  i >= 0 ? arr.value.splice(i, 1) : arr.value.push(val);
};

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
    const { data } = await axios.get(`${global.baseUrl}/promociones`);
    promociones.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const seed = (obj = {}) => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, obj);
};

const nueva = () => {
  promoDias.value = [];
  promoPres.value = [];
  promoSuc.value = [];
  modalTitle.value = 'Nueva promoción';
  seed({ nombre: '', descripcion: '', fecha_inicio: '', fecha_fin: '', prioridad: 1, acumulable: false, activa: true, id_tipo_promocion: '', porcentaje_descuento: 0, importe_descuento: 0, precio_especial: 0, cantidad_minima: 0, importe_minimo: 0 });
  modal.value = true;
};

const editar = async (p) => {
  try {
    const { data: d } = await axios.get(`${global.baseUrl}/promociones/${p.id_promocion}`);
    const regla = (d.reglas && d.reglas[0]) || {};
    promoDias.value = d.dias ? Object.keys(d.dias).filter((k) => diasSemana.includes(k) && d.dias[k]) : [];
    promoPres.value = (d.presentaciones || []).map(Number);
    promoSuc.value = (d.sucursales || []).map(Number);
    modalTitle.value = 'Editar promoción';
    seed({
      id_promocion: d.id_promocion, nombre: d.nombre, descripcion: d.descripcion,
      fecha_inicio: (d.fecha_inicio || '').replace(' ', 'T').substring(0, 16),
      fecha_fin: (d.fecha_fin || '').replace(' ', 'T').substring(0, 16),
      prioridad: d.prioridad, acumulable: !!d.acumulable, activa: !!d.activa,
      id_tipo_promocion: regla.id_tipo_promocion || '', porcentaje_descuento: regla.porcentaje_descuento || 0,
      importe_descuento: regla.importe_descuento || 0, precio_especial: regla.precio_especial || 0,
      cantidad_minima: regla.cantidad_minima || 0, importe_minimo: regla.importe_minimo || 0,
    });
    modal.value = true;
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const guardar = async () => {
  try {
    const payload = { ...form, dias: promoDias.value, presentaciones: promoPres.value, sucursales: promoSuc.value };
    if (form.id_promocion) await axios.put(`${global.baseUrl}/promociones/${form.id_promocion}`, payload);
    else await axios.post(`${global.baseUrl}/promociones`, payload);
    toast.value.notify('Promoción guardada');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const eliminar = async (p) => {
  if (!confirm('¿Eliminar promoción?')) return;
  try {
    await axios.delete(`${global.baseUrl}/promociones/${p.id_promocion}`);
    toast.value.notify('Promoción eliminada');
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
    <PageHeader title="Promociones" subtitle="Descuentos, 2x1, volumen, precio especial · por productos, sucursales y días.">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nueva" data-testid="add-promo-btn">
          <i class="bi bi-plus-lg"></i> Nueva promoción
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>
    
    <DataTable v-else :headers="headers" :empty="!promociones.length" empty-text="Sin promociones">
      <tr v-for="p in promociones" :key="p.id_promocion">
        <td class="align-middle">
          <div class="fw-semibold">{{ p.nombre }}</div>
          <div class="text-muted small">{{ p.descripcion }}</div>
        </td>
        <td class="align-middle text-muted small">
          {{ (p.fecha_inicio || '').substring(0, 10) }} → {{ (p.fecha_fin || '').substring(0, 10) }}
        </td>
        <td class="align-middle">{{ p.prioridad }}</td>
        <td class="align-middle">{{ p.acumulable ? 'Sí' : 'No' }}</td>
        <td class="align-middle">
          <StatusBadge :variant="p.activa ? 'b-green' : 'b-gray'">{{ p.activa ? 'Activa' : 'Inactiva' }}</StatusBadge>
        </td>
        <td class="align-middle text-end">
          <button class="btn btn-outline-secondary btn-sm me-1" @click="editar(p)" data-testid="edit-promo-btn">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-outline-danger btn-sm" @click="eliminar(p)">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" :title="modalTitle" big @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre *</label>
          <input type="text" class="form-control" v-model="form.nombre" />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Descripción</label>
          <input type="text" class="form-control" v-model="form.descripcion" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Inicio *</label>
          <input type="datetime-local" class="form-control" v-model="form.fecha_inicio" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Fin *</label>
          <input type="datetime-local" class="form-control" v-model="form.fecha_fin" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Tipo *</label>
          <select class="form-select" v-model="form.id_tipo_promocion">
            <option value="">—</option>
            <option v-for="t in cat.tipos_promocion" :key="t.id_tipo_promocion" :value="t.id_tipo_promocion">{{ t.nombre }}</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Prioridad</label>
          <input type="number" class="form-control" v-model="form.prioridad" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">% Descuento</label>
          <input type="number" step="0.01" class="form-control" v-model="form.porcentaje_descuento" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Importe descuento</label>
          <input type="number" step="0.01" class="form-control" v-model="form.importe_descuento" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Precio especial</label>
          <input type="number" step="0.01" class="form-control" v-model="form.precio_especial" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Cant. mínima</label>
          <input type="number" step="0.01" class="form-control" v-model="form.cantidad_minima" />
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Acumulable</label>
          <select class="form-select" v-model="form.acumulable">
            <option :value="false">No</option>
            <option :value="true">Sí</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Activa</label>
          <select class="form-select" v-model="form.activa">
            <option :value="true">Sí</option>
            <option :value="false">No</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold d-block">Días de aplicación</label>
          <div class="d-flex flex-wrap gap-2">
            <span v-for="d in diasSemana" :key="d" class="badge cursor-pointer px-3 py-2" :class="promoDias.includes(d) ? 'bg-primary' : 'bg-light text-dark border'" @click="toggleArr(promoDias, d)">
              {{ d }}
            </span>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold d-block">Productos incluidos</label>
          <div class="d-flex flex-wrap gap-2">
            <span v-for="p in cat.presentaciones" :key="p.id_presentacion" class="badge cursor-pointer px-3 py-2" :class="promoPres.includes(p.id_presentacion) ? 'bg-primary' : 'bg-light text-dark border'" @click="toggleArr(promoPres, p.id_presentacion)">
              {{ p.nombre }}
            </span>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold d-block">Sucursales</label>
          <div class="d-flex flex-wrap gap-2">
            <span v-for="s in cat.sucursales" :key="s.id_sucursal" class="badge cursor-pointer px-3 py-2" :class="promoSuc.includes(s.id_sucursal) ? 'bg-primary' : 'bg-light text-dark border'" @click="toggleArr(promoSuc, s.id_sucursal)">
              {{ s.nombre }}
            </span>
          </div>
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
