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

const puestos = ref([]);
const loading = ref(false);
const modal = ref(false);
const modalTitle = ref('');
const form = reactive({});

// Variables de estado para los filtros (Búsqueda por texto y Estado Activo/Inactivo)
const filtroBusqueda = ref('');
const filtroActivo = ref('');

const headers = ['Nombre', 'Nivel', 'Descripción', 'Salario base', 'Estado', ''];

const load = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(`${global.baseUrl}/puestos`, {
      params: {
        search: filtroBusqueda.value,
        activo: filtroActivo.value
      }
    });
    puestos.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const seed = (obj = {}) => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, obj);
};

const nuevo = () => {
  modalTitle.value = 'Nuevo puesto';
  seed({ nombre: '', descripcion: '', nivel: 'Operativo', salario_base: 0, activo: true });
  modal.value = true;
};

const editar = (p) => {
  modalTitle.value = 'Editar puesto';
  seed({ ...p, activo: !!p.activo });
  modal.value = true;
};

const guardar = async () => {
  try {
    if (form.id_puesto) await axios.put(`${global.baseUrl}/puestos/${form.id_puesto}`, form);
    else await axios.post(`${global.baseUrl}/puestos`, form);
    toast.value.notify('Puesto guardado');
    modal.value = false;
    load();
  } catch (e) {
    toast.value.apiErr(e);
  }
};

const cambiarEstado = async (p) => {
  try {
    const { data } = await axios.delete(`${global.baseUrl}/puestos/${p.id_puesto}`);
    // Actualizamos el estado localmente de inmediato para evitar desincronizaciones
    p.activo = data.activo;
    toast.value.notify(data.message);
    load();
  } catch (err) {
    toast.value?.apiErr(err);
  }
};

onMounted(load);
</script>

<template>
  <section>
    <PageHeader title="Puestos" subtitle="Aquí defines el salario base que heredarán los empleados.">
      <template #action>
        <button class="btn btn-brand" @click="nuevo" data-testid="add-puesto-btn"><i class="bi bi-plus-lg"></i>Nuevo puesto</button>
      </template>
    </PageHeader>

    <!-- Barra de búsqueda y Filtros de estado integrados -->
    <div class="mb-4">
      <div class="row g-3">
        <div style="flex: 2;">
          <input 
            type="text" 
            class="form-control" 
            v-model="filtroBusqueda" 
            @input="load" 
            placeholder="Buscar por nombre o descripción del puesto..."
          >
        </div>
        <div style="flex: 1;">
          <select class="form-select" v-model="filtroActivo" @change="load">
            <option value="">Todos los estados</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="loading" class="spinner"></div>
    <DataTable v-else :headers="headers" :empty="!puestos.length" empty-text="Sin puestos">
      <tr v-for="p in puestos" :key="p.id_puesto">
        <td><strong>{{ p.nombre }}</strong></td>
        <td><StatusBadge variant="b-blue">{{ p.nivel }}</StatusBadge></td>
        <td class="sub">{{ p.descripcion }}</td>
        <td class="money">{{ money(p.salario_base) }}</td>
        <td><StatusBadge :variant="p.activo ? 'b-green' : 'b-gray'">{{ p.activo ? 'Activo' : 'Inactivo' }}</StatusBadge></td>
        <td style="text-align:right">
          <button class="btn btn-ghost btn-sm" @click="editar(p)"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-ghost btn-sm btn-danger" @click="cambiarEstado(p)"><i :class="['bi', p.activo ? 'bi-ban' : 'bi-check-lg']"></i></button>
        </td>
      </tr>
    </DataTable>

    <ModalBase v-if="modal" :title="modalTitle" @close="modal = false" @save="guardar">
      <div class="form-grid">
        <div class="field full"><label>Nombre *</label><input v-model="form.nombre" data-testid="puesto-nombre" /></div>
        <div class="field full"><label>Descripción</label><input v-model="form.descripcion" /></div>
        <div class="field"><label>Nivel</label>
          <select v-model="form.nivel"><option>Ejecutivo</option><option>Gerencial</option><option>Supervisor</option><option>Operativo</option><option>Administrativo</option></select></div>
        <div class="field"><label>Salario base *</label><input type="number" step="0.01" v-model="form.salario_base" data-testid="puesto-salario" /></div>
        <div class="field"><label>Activo</label><select v-model="form.activo"><option :value="true">Sí</option><option :value="false">No</option></select></div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>