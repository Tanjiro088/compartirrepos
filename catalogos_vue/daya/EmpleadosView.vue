<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { money } from '../helpers';
import { useGlobalStore } from '../stores/store.js';
import PageHeader from '../components/PageHeader.vue';
import DataTable from '../components/DataTable.vue';
import ModalBase from '../components/ModalBase.vue';
import StatusBadge from '../components/StatusBadge.vue';
import TableToolbar from '../components/TableToolbar.vue';
import ToastNotification from '../components/ToastNotification.vue';

const global = useGlobalStore();
const API_URL = `${global.baseUrl}empleados`;
const toast = ref(null);

const empleados = ref([]);
const cat = reactive({ personas: [], puestos: [], sucursales: [] });
const loading = ref(false);
const modal = ref(false);
const modalTitle = ref('');
const form = reactive({});

const headers = ['# Emp.', 'Nombre', 'Puesto', 'Sucursal', 'Salario mensual', 'Estado', ''];

// Búsqueda y filtro por estado.
const q = ref('');
const estadoFilter = ref('');
const estadosEmpleado = [
  { value: 'activo', label: 'Activo' },
  { value: 'inactivo', label: 'Inactivo' },
];

// Cálculo automático del salario mensual en base al tipo de pago seleccionado y el salario base
const salarioMensualCalculado = computed(() => {
  const base = Number(form.salario_base || 0);
  const tipo = form.tipo_calculo_salario || 'quincenal';

  if (tipo === 'diario') {
    return base * 30; // 30 días al mes
  } else if (tipo === 'semanal') {
    return base * 4;  // 4 semanas al mes
  } else {
    return base * 2;  // Quincenal (2 quincenas al mes)
  }
});

const empleadosFiltrados = computed(() => {
  const term = q.value.trim().toLowerCase();
  return empleados.value.filter((e) => {
    const coincide = !term || [e.persona, e.numero_empleado, e.puesto, e.sucursal]
      .some((v) => String(v || '').toLowerCase().includes(term));
    const estadoOk = !estadoFilter.value || (estadoFilter.value === 'activo' ? !!e.activo : !e.activo);
    return coincide && estadoOk;
  });
});

const loadCat = async () => {
  try {
    const { data } = await axios.get(`${global.baseUrl}catalogos`);
    console.log("Catálogos recibidos:", data);

    if (data) {
      cat.personas = data.personas || [];
      cat.puestos = data.puestos || [];
      cat.sucursales = data.sucursales || [];
    }
  } catch (e) {
    console.error("Error al cargar catálogos:", e);
    toast.value?.apiErr(e);
  }
};

const load = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(API_URL);
    empleados.value = data;
  } catch (e) {
    toast.value?.apiErr(e);
  }
  loading.value = false;
};

const seed = (obj = {}) => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, obj);
};

const nuevo = async () => {
  await loadCat();
  modalTitle.value = 'Nuevo empleado';
  seed({ 
    id_persona: '', 
    id_puesto: '', 
    id_sucursal: '', 
    numero_empleado: '', 
    fecha_contratacion: '', 
    fecha_termino: '',
    salario_base: '', 
    tipo_calculo_salario: 'quincenal', 
    tipo_contrato: 'Tiempo completo', 
    jornada: 'Matutina' 
  });
  modal.value = true;
};

const editar = async (e) => {
  await loadCat();
  modalTitle.value = 'Editar empleado';
  seed({ 
    ...e, 
    salario_base: e.salario_mensual, // Al editar por defecto cargamos el monto total como base asumiendo quincenal
    tipo_calculo_salario: 'quincenal'
  });
  modal.value = true;
};

const guardar = async () => {
  try {
    // Inyectamos el valor calculado del salario mensual antes de enviarlo al backend
    form.salario = salarioMensualCalculado.value;

    if (form.id_empleado) await axios.put(`${API_URL}/${form.id_empleado}`, form);
    else await axios.post(API_URL, form);
    toast.value.notify('Empleado guardado · nómina generada');
    modal.value = false;
    load();
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const eliminar = async (e) => {
  try {
    const { data } = await axios.delete(`${API_URL}/${e.id_empleado}`);
    toast.value.notify(data.message || 'Estado actualizado correctamente');
    load();
  } catch (err) {
    toast.value?.apiErr(err);
  }
};

onMounted(() => {
  loadCat();
  load();
});
</script>

<template>
  <section class="container-fluid py-4">
    <PageHeader title="Empleados" subtitle="Asigna el salario base, calcula el mensual y genera su nómina única.">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nuevo"
          data-testid="add-empleado-btn">
          <i class="bi bi-plus-lg"></i> Nuevo empleado
        </button>
      </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>

    <template v-else>
      <TableToolbar v-model:search="q" v-model:estado="estadoFilter" placeholder="Buscar por nombre, N° o puesto…"
        :estados="estadosEmpleado" id-prefix="empleados" />
      <DataTable :headers="headers" :empty="!empleadosFiltrados.length" empty-text="Sin empleados">
        <tr v-for="e in empleadosFiltrados" :key="e.id_empleado">
          <td class="align-middle fw-semibold">{{ e.numero_empleado }}</td>
          <td class="align-middle">{{ e.persona.nombre }} {{ e.persona.apellido_paterno }} {{ e.persona.apellido_materno }}</td>
          <td class="align-middle">{{ e.puesto.nombre }}</td>
          <td class="align-middle text-muted">{{ e.sucursal || '—' }}</td>
          <td class="align-middle text-end fw-semibold">{{ money(e.salario_mensual) }}</td>
          <td class="align-middle">
            <StatusBadge :variant="e.activo ? 'b-green' : 'b-gray'">{{ e.activo ? 'Activo' : 'Inactivo' }}</StatusBadge>
          </td>
          <td class="align-middle text-end">
            <button class="btn btn-outline-secondary btn-sm me-1" @click="editar(e)">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm" :class="e.activo ? 'btn-outline-danger' : 'btn-outline-success'"
              @click="eliminar(e)" :title="e.activo ? 'Desactivar' : 'Activar'">
              <i :class="e.activo ? 'bi bi-slash-circle' : 'bi bi-check-lg'"></i>
            </button>
          </td>
        </tr>
      </DataTable>
    </template>

    <ModalBase v-if="modal" :title="modalTitle" big @close="modal = false" @save="guardar">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Persona *</label>
          <select class="form-select" v-model="form.id_persona" data-testid="empleado-persona">
            <option value="">—</option>
            <option v-for="p in cat.personas" :key="p.id_persona" :value="p.id_persona">
              {{ [p.nombre, p.apellido_paterno, p.apellido_materno].filter(Boolean).join(' ') }}
            </option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Puesto *</label>
          <select class="form-select" v-model="form.id_puesto" data-testid="empleado-puesto">
            <option value="">—</option>
            <option v-for="p in cat.puestos" :key="p.id_puesto" :value="p.id_puesto">{{ p.nombre }}</option>
          </select>
        </div>

        <!-- TIPO DE CÁLCULO DE SALARIO Y SALARIO BASE -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Tipo de pago / Salario base *</label>
          <div class="input-group">
            <select class="form-select" v-model="form.tipo_calculo_salario" style="max-width: 140px;">
              <option value="quincenal">Quincenal</option>
              <option value="semanal">Semanal</option>
              <option value="diario">Diario</option>
            </select>
            <input type="number" step="0.01" min="0" class="form-control" v-model.number="form.salario_base" placeholder="Ej. 4250.00" />
          </div>
          <div class="form-text">Selecciona la frecuencia e ingresa el monto base.</div>
        </div>

        <!-- SALARIO MENSUAL CALCULADO -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Salario mensual (Calculado)</label>
          <input type="text" class="form-control bg-light fw-bold text-success" :value="money(salarioMensualCalculado)" disabled />
          <div class="form-text">Resultado total calculado para el mes.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Sucursal</label>
          <select class="form-select" v-model="form.id_sucursal">
            <option value="">—</option>
            <option v-for="s in cat.sucursales" :key="s.id_sucursal" :value="s.id_sucursal">{{ s.nombre }}</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold"># Empleado</label>
          <input type="text" class="form-control" v-model="form.numero_empleado" />
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Fecha contratación</label>
          <input type="date" class="form-control" v-model="form.fecha_contratacion" />
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Fecha término contrato</label>
          <input type="date" class="form-control" v-model="form.fecha_termino" />
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Tipo contrato</label>
          <select class="form-select" v-model="form.tipo_contrato">
            <option>Tiempo completo</option>
            <option>Medio tiempo</option>
            <option>Por honorarios</option>
            <option>Temporal</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Jornada</label>
          <select class="form-select" v-model="form.jornada">
            <option>Matutina</option>
            <option>Vespertina</option>
            <option>Nocturna</option>
            <option>Mixta</option>
          </select>
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
