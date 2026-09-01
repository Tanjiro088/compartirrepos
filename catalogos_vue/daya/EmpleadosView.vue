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

// El salario NO se captura: se hereda del salario base del puesto (regla de negocio).
const salarioPreview = computed(() => {
  const p = cat.puestos.find((x) => x.id_puesto == form.id_puesto);
  return Number(p?.salario_base || 0);
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
    const { data } = await axios.get(`${global.baseUrl}/catalogos`);
    console.log("Catálogos recibidos:", data); // <-- Revisa esto en la consola del navegador (F12)
    
    // Validamos que data tenga las propiedades antes de asignarlas
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
    const { data } = await axios.get(`${global.baseUrl}/empleados`);
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
  await loadCat(); // <-- Recarga los catálogos frescos antes de abrir
  modalTitle.value = 'Nuevo empleado';
  seed({ id_persona: '', id_puesto: '', id_sucursal: '', numero_empleado: '', fecha_contratacion: '', tipo_contrato: 'Tiempo completo', jornada: 'Matutina', banco: '', numero_cuenta: '', clabe: '' });
  modal.value = true;
};

const editar = async (e) => {
  await loadCat(); // <-- Recarga los catálogos frescos antes de abrir
  modalTitle.value = 'Editar empleado';
  seed({ ...e });
  modal.value = true;
};

const guardar = async () => {
  try {
    if (form.id_empleado) await axios.put(`${global.baseUrl}/empleados/${form.id_empleado}`, form);
    else await axios.post(`${global.baseUrl}/empleados`, form);
    toast.value.notify('Empleado guardado · nómina generada');
    modal.value = false;
    load();
  } catch (e) {
    toast.value?.apiErr(e);
  }
};

const eliminar = async (e) => {
  const accion = e.activo ? 'desactivar' : 'activar';
  // if (!confirm(`¿Estás seguro de ${accion} este empleado?`)) return;
  try {
    const { data } = await axios.delete(`${global.baseUrl}/empleados/${e.id_empleado}`);
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
    <PageHeader title="Empleados" subtitle="Al alta se hereda el salario del puesto y se genera su nómina única.">
      <template #action>
        <button class="btn btn-primary d-inline-flex align-items-center gap-2" @click="nuevo" data-testid="add-empleado-btn">
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
      <TableToolbar
        v-model:search="q"
        v-model:estado="estadoFilter"
        placeholder="Buscar por nombre, N° o puesto…"
        :estados="estadosEmpleado"
        id-prefix="empleados"
      />
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
            <button 
              class="btn btn-sm" 
              :class="e.activo ? 'btn-outline-danger' : 'btn-outline-success'" 
              @click="eliminar(e)"
              :title="e.activo ? 'Desactivar' : 'Activar'"
            >
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
              {{ p.nombre + ' ' + p.apellido_paterno + ' ' + p.apellido_materno }}
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

        <div class="col-12">
          <label class="form-label fw-semibold">Salario mensual (heredado del puesto)</label>
          <input type="text" class="form-control" :value="money(salarioPreview)" disabled />
          <div class="form-text">No se captura: se toma del salario base del puesto.</div>
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

        <div class="col-md-6">
          <label class="form-label fw-semibold">Banco</label>
          <input type="text" class="form-control" v-model="form.banco" />
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">N° cuenta <small class="text-muted fw-normal">(Máx. 20 dígitos)</small></label>
          <input 
            type="text"
            class="form-control"
            v-model="form.numero_cuenta" 
            maxlength="20" 
            @input="form.numero_cuenta = form.numero_cuenta.replace(/\D/g, '')" 
            placeholder="Ej. 0123456789" 
          />
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">CLABE <small class="text-muted fw-normal">(Exactamente 18 dígitos)</small></label>
          <input 
            type="text"
            class="form-control"
            v-model="form.clabe" 
            maxlength="18" 
            @input="form.clabe = form.clabe.replace(/\D/g, '')" 
            placeholder="18 dígitos interbancarios" 
          />
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>
