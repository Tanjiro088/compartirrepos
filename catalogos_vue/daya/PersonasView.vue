<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '@/stores/store.js';

import PageHeader from '@/components/PageHeader.vue';
import TableToolbar from '@/components/TableToolbar.vue';
import DataTable from '@/components/DataTable.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import ModalBase from '@/components/ModalBase.vue';
import ToastNotification from '@/components/ToastNotification.vue';

const global = useGlobalStore();
const toast = ref(null);

const personas = ref([]);
const loading = ref(false);
const q = ref('');
const estadoFilter = ref('');
const modal = ref(false);
const editMode = ref(false);
const errors = ref({});

const estadosPersona = [
  { value: 'true', label: 'Activo' },
  { value: 'false', label: 'Inactivo' }
];

const headers = [
  'Documento',
  'Nombre / Razón Social',
  'Tipo',
  'Teléfono',
  'Correo',
  'Estado',
  'Acciones'
];

const form = ref({
  id_persona: null,
  tipo_persona: 'fisica',
  tipo_documento: 'DNI',
  numero_documento: '',
  nombre: '',
  apellido_paterno: '',
  apellido_materno: '',
  razon_social: '',
  nombre_comercial: '',
  calle: '',
  numero_exterior: '',
  numero_interior: '',
  colonia: '',
  codigo_postal: '',
  ciudad: '',
  estado: '',
  pais: 'México',
  telefono: '',
  correo: '',
  fecha_nacimiento: '',
  genero: 'Masculino',
  activo: true
});

const modalTitle = computed(() => editMode.value ? 'Editar Persona' : 'Nueva Persona');

const personasFiltradas = computed(() => {
  return personas.value.filter(p => {
    const textoMatch = 
      (p.nombre?.toLowerCase() || '').includes(q.value.toLowerCase()) ||
      (p.apellido_paterno?.toLowerCase() || '').includes(q.value.toLowerCase()) ||
      (p.razon_social?.toLowerCase() || '').includes(q.value.toLowerCase()) ||
      (p.numero_documento?.toLowerCase() || '').includes(q.value.toLowerCase());
    
    const estadoMatch = estadoFilter.value === '' || String(p.activo) === estadoFilter.value;

    return textoMatch && estadoMatch;
  });
});

const fetchPersonas = async () => {
  loading.value = true;
  try {
    const response = await axios.get(`${global.baseUrl}/personas`);
    
    if (response.data && Array.isArray(response.data.data)) {
      personas.value = response.data.data;
    } else if (Array.isArray(response.data)) {
      personas.value = response.data;
    } else {
      personas.value = [];
    }
  } catch (error) {
    console.error('Error al cargar personas:', error);
    toast.value?.apiErr ? toast.value.apiErr(error) : console.error(error);
    personas.value = [];
  } finally {
    loading.value = false;
  }
};

const nuevo = () => {
  editMode.value = false;
  errors.value = {};
  form.value = {
    id_persona: null,
    tipo_persona: 'fisica',
    tipo_documento: 'DNI',
    numero_documento: '',
    nombre: '',
    apellido_paterno: '',
    apellido_materno: '',
    razon_social: '',
    nombre_comercial: '',
    calle: '',
    numero_exterior: '',
    numero_interior: '',
    colonia: '',
    codigo_postal: '',
    ciudad: '',
    estado: '',
    pais: 'México',
    telefono: '',
    correo: '',
    fecha_nacimiento: '',
    genero: 'Masculino',
    activo: true
  };
  modal.value = true;
};

const editar = (p) => {
  editMode.value = true;
  errors.value = {};
  form.value = { ...p };
  modal.value = true;
};

const guardar = async () => {
  errors.value = {};
  try {
    if (editMode.value) {
      await axios.put(`${global.baseUrl}/personas/${form.value.id_persona}`, form.value);
    } else {
      await axios.post(`${global.baseUrl}/personas`, form.value);
    }
    modal.value = false;
    fetchPersonas();
  } catch (error) {
    console.error("DETALLE DEL ERROR:", error);
    if (error.response && error.response.status === 422) {
      errors.value = error.response.data.errors;
    } else {
      toast.value?.apiErr?.(error);
    }
  }
};

const eliminar = async (p) => {
  const accion = p.activo ? 'desactivar' : 'activar';
  if (confirm(`¿Estás seguro de ${accion} a esta persona?`)) {
    try {
      p.activo = !p.activo;
      await axios.put(`${global.baseUrl}/personas/${p.id_persona}`, p);
      fetchPersonas();
    } catch (error) {
      console.error('Error al cambiar estado:', error);
      p.activo = !p.activo;
    }
  }
};

onMounted(() => {
  fetchPersonas();
});
</script>

<template>
  <section class="container-fluid px-4 py-3">
    <PageHeader title="Personas" subtitle="Catálogo central de personas físicas y morales del sistema.">
      <template #action>
        <button class="btn btn-primary d-flex align-items-center gap-2" @click="nuevo" data-testid="add-persona-btn">
          <i class="bi bi-plus-lg"></i>Nueva persona
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
        placeholder="Buscar por nombre, documento o razón social…"
        :estados="estadosPersona"
        id-prefix="personas"
      />
      <DataTable :headers="headers" :empty="!personasFiltradas.length" empty-text="Sin personas registradas">
        <tr v-for="p in personasFiltradas" :key="p.id_persona">
          <td class="align-middle">{{ p.tipo_documento }}: {{ p.numero_documento }}</td>
          <td class="align-middle">
            <div class="fw-semibold">{{ p.tipo_persona === 'fisica' ? `${p.nombre} ${p.apellido_paterno || ''} ${p.apellido_materno || ''}` : p.razon_social }}</div>
            <div v-if="p.nombre_comercial"><small class="text-muted">{{ p.nombre_comercial }}</small></div>
          </td>
          <td class="align-middle"><span class="badge bg-secondary text-capitalize">{{ p.tipo_persona }}</span></td>
          <td class="align-middle">{{ p.telefono || '—' }}</td>
          <td class="align-middle">{{ p.correo || '—' }}</td>
          <td class="align-middle">
            <StatusBadge :variant="p.activo ? 'b-green' : 'b-gray'">
              {{ p.activo ? 'Activo' : 'Inactivo' }}
            </StatusBadge>
          </td>
          <td class="align-middle text-end">
            <button class="btn btn-outline-secondary btn-sm me-1" @click="editar(p)"><i class="bi bi-pencil"></i></button>
            <button 
              class="btn btn-sm" 
              :class="p.activo ? 'btn-outline-danger' : 'btn-outline-success'" 
              @click="eliminar(p)"
              :title="p.activo ? 'Desactivar' : 'Activar'"
            >
              <i :class="p.activo ? 'bi bi-slash-circle' : 'bi bi-check-lg'"></i>
            </button>
          </td>
        </tr>
      </DataTable>
    </template>

    <ModalBase v-if="modal" :title="modalTitle" big @close="modal = false" @save="guardar">
      <div class="row g-3">
        <!-- Datos Generales -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Tipo de Persona *</label>
          <select class="form-select" v-model="form.tipo_persona" data-testid="persona-tipo">
            <option value="fisica">Física</option>
            <option value="moral">Moral</option>
          </select>
          <span v-if="errors.tipo_persona" class="text-danger small mt-1 d-block">{{ errors.tipo_persona[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Tipo de Documento *</label>
          <select class="form-select" v-model="form.tipo_documento">
            <option value="DNI">DNI</option>
            <option value="RUC">RUC</option>
            <option value="Pasaporte">Pasaporte</option>
            <option value="CE">CE</option>
          </select>
          <span v-if="errors.tipo_documento" class="text-danger small mt-1 d-block">{{ errors.tipo_documento[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Número de Documento *</label>
          <input class="form-control" v-model="form.numero_documento" required />
          <span v-if="errors.numero_documento" class="text-danger small mt-1 d-block">{{ errors.numero_documento[0] }}</span>
        </div>

        <!-- Campos condicionales: Física vs Moral -->
        <template v-if="form.tipo_persona === 'fisica'">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Nombre(s) *</label>
            <input class="form-control" v-model="form.nombre" required />
            <span v-if="errors.nombre" class="text-danger small mt-1 d-block">{{ errors.nombre[0] }}</span>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Apellido Paterno</label>
            <input class="form-control" v-model="form.apellido_paterno" />
            <span v-if="errors.apellido_paterno" class="text-danger small mt-1 d-block">{{ errors.apellido_paterno[0] }}</span>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Apellido Materno</label>
            <input class="form-control" v-model="form.apellido_materno" />
            <span v-if="errors.apellido_materno" class="text-danger small mt-1 d-block">{{ errors.apellido_materno[0] }}</span>
          </div>
        </template>
        <template v-else>
          <div class="col-12">
            <label class="form-label fw-semibold">Razón Social *</label>
            <input class="form-control" v-model="form.razon_social" required />
            <span v-if="errors.razon_social" class="text-danger small mt-1 d-block">{{ errors.razon_social[0] }}</span>
          </div>
        </template>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Nombre Comercial</label>
          <input class="form-control" v-model="form.nombre_comercial" />
          <span v-if="errors.nombre_comercial" class="text-danger small mt-1 d-block">{{ errors.nombre_comercial[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Teléfono</label>
          <input class="form-control" v-model="form.telefono" />
          <span v-if="errors.telefono" class="text-danger small mt-1 d-block">{{ errors.telefono[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Correo Electrónico</label>
          <input type="email" class="form-control" v-model="form.correo" />
          <span v-if="errors.correo" class="text-danger small mt-1 d-block">{{ errors.correo[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Fecha de Nacimiento</label>
          <input type="date" class="form-control" v-model="form.fecha_nacimiento" />
          <span v-if="errors.fecha_nacimiento" class="text-danger small mt-1 d-block">{{ errors.fecha_nacimiento[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Género</label>
          <select class="form-select" v-model="form.genero">
            <option value="Masculino">Masculino</option>
            <option value="Femenino">Femenino</option>
            <option value="Otro">Otro</option>
          </select>
          <span v-if="errors.genero" class="text-danger small mt-1 d-block">{{ errors.genero[0] }}</span>
        </div>

        <!-- Dirección -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Calle</label>
          <input class="form-control" v-model="form.calle" />
          <span v-if="errors.calle" class="text-danger small mt-1 d-block">{{ errors.calle[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Número Exterior</label>
          <input class="form-control" v-model="form.numero_exterior" />
          <span v-if="errors.numero_exterior" class="text-danger small mt-1 d-block">{{ errors.numero_exterior[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Número Interior</label>
          <input class="form-control" v-model="form.numero_interior" />
          <span v-if="errors.numero_interior" class="text-danger small mt-1 d-block">{{ errors.numero_interior[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Colonia</label>
          <input class="form-control" v-model="form.colonia" />
          <span v-if="errors.colonia" class="text-danger small mt-1 d-block">{{ errors.colonia[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Código Postal</label>
          <input class="form-control" v-model="form.codigo_postal" />
          <span v-if="errors.codigo_postal" class="text-danger small mt-1 d-block">{{ errors.codigo_postal[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Ciudad</label>
          <input class="form-control" v-model="form.ciudad" />
          <span v-if="errors.ciudad" class="text-danger small mt-1 d-block">{{ errors.ciudad[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">Estado / Provincia</label>
          <input class="form-control" v-model="form.estado" />
          <span v-if="errors.estado" class="text-danger small mt-1 d-block">{{ errors.estado[0] }}</span>
        </div>

        <div class="col-md-6">
          <label class="form-label fw-semibold">País</label>
          <input class="form-control" v-model="form.pais" />
          <span v-if="errors.pais" class="text-danger small mt-1 d-block">{{ errors.pais[0] }}</span>
        </div>
      </div>
    </ModalBase>

    <ToastNotification ref="toast" />
  </section>
</template>


<style scoped>
.text-danger {
  color: #dc3545;
}
.small {
  font-size: 0.8rem;
  margin-top: 2px;
  display: block;
}
</style>
