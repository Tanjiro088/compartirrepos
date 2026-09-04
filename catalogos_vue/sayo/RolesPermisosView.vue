<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '../stores/store.js';

// Estado global
const store = useGlobalStore();
const url = store.baseUrl;

// Estados del componente
const roles = ref([]);
const mostrarFormulario = ref(false);

const modulosSistema = [
  'Organización', 'Recursos Humanos', 'Ventas y Caja', 
  'Seguridad', 'Clasificaciones', 'Productos'
];
const acciones = ['leer', 'crear', 'actualizar', 'eliminar'];

// Matriz reactiva para controlar los switches del formulario
const matrizPermisos = ref({});

// Objeto para el formulario (datos básicos del rol)
const form = ref({
  id_rol: null,
  nombre: '',
  descripcion: ''
});

// Inicializa la matriz de permisos todo en 'false'
const inicializarMatriz = () => {
  const matriz = {};
  modulosSistema.forEach(modulo => {
    matriz[modulo] = { leer: false, crear: false, actualizar: false, eliminar: false };
  });
  matrizPermisos.value = matriz;
};

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerRoles = async () => {
  try {
    const respuesta = await axios.get(url + 'roles');
    roles.value = respuesta.data;
  } catch (error) {
    console.error('Error al obtener roles:', error);
  }
};

const guardarRol = async () => {
  try {
    // Transformamos la matriz visual en el Array que espera Laravel
    const permisosAPI = [];
    for (const [modulo, operaciones] of Object.entries(matrizPermisos.value)) {
      for (const [accion, activo] of Object.entries(operaciones)) {
        if (activo) {
          permisosAPI.push({ modulo, accion });
        }
      }
    }

    const payload = {
      nombre: form.value.nombre,
      descripcion: form.value.descripcion,
      permisos: permisosAPI
    };

    if (form.value.id_rol) {
      await axios.put(`${url}roles/${form.value.id_rol}`, payload);
    } else {
      await axios.post(`${url}roles`, payload);
    }
    
    await obtenerRoles();
    cerrarFormulario();
  } catch (error) {
    console.error('Error al guardar:', error);
    alert('Ocurrió un error al guardar el rol y sus permisos.');
  }
};

const eliminarRol = async (id) => {
  if (confirm('⚠️ ¿Estás seguro de eliminar este rol? Se perderán sus configuraciones de acceso.')) {
    try {
      await axios.delete(`${url}roles/${id}`);
      await obtenerRoles();
    } catch (error) {
      // Atrapamos la validación (Código 400) de usuarios asignados
      if(error.response && error.response.status === 400){
        alert(error.response.data.error);
      } else {
        alert('Hubo un problema al intentar eliminar el rol.');
      }
    }
  }
};

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirNuevo = () => {
  form.value = { id_rol: null, nombre: '', descripcion: '' };
  inicializarMatriz();
  mostrarFormulario.value = true;
};

const abrirEditar = async (rol) => {
  form.value = { ...rol };
  inicializarMatriz(); // Reseteamos la UI

  try {
    // Solicitamos al backend los permisos específicos de este rol
    const respuesta = await axios.get(`${url}roles/${rol.id_rol}`);
    const permisosAsignados = respuesta.data.permisos || [];

    // Encendemos los switches correspondientes
    permisosAsignados.forEach(p => {
      if (matrizPermisos.value[p.modulo] && matrizPermisos.value[p.modulo][p.accion] !== undefined) {
        matrizPermisos.value[p.modulo][p.accion] = true;
      }
    });

    mostrarFormulario.value = true;
  } catch (error) {
    console.error('Error al cargar permisos del rol:', error);
  }
};

const cerrarFormulario = () => {
  mostrarFormulario.value = false;
};

// Cargar datos al montar la vista
onMounted(() => {
  obtenerRoles();
});
</script>

<template>
  <div class="container mt-5 mb-5">
    <h2 class="text-center mb-4 text-secondary">Roles y Permisos</h2>

    <!-- VISTA DE TABLA -->
    <div v-if="!mostrarFormulario" class="card shadow-sm p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Roles del Sistema</h4>
        <button class="btn btn-primary" @click="abrirNuevo">
          <i class="bi bi-shield-plus"></i> Nuevo Rol
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Nombre del Rol</th>
              <th>Descripción</th>
              <th class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rol in roles" :key="rol.id_rol">
              <td><span class="text-muted fw-bold">#{{ rol.id_rol }}</span></td>
              <td class="fw-semibold text-primary">{{ rol.nombre }}</td>
              <td class="text-muted">{{ rol.descripcion }}</td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" @click="abrirEditar(rol)">Configurar Permisos</button>
                <button class="btn btn-sm btn-outline-danger" @click="eliminarRol(rol.id_rol)">Eliminar</button>
              </td>
            </tr>
            <tr v-if="roles.length === 0">
              <td colspan="4" class="text-center text-muted py-4">No se encontraron roles registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- VISTA DE FORMULARIO MATRIZ -->
    <div v-else class="card shadow-sm p-4 w-100 mx-auto">
      <h4 class="text-center mb-4">{{ form.id_rol ? 'Modificar' : 'Configurar Nuevo' }} Rol</h4>
      
      <form @submit.prevent="guardarRol">
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <label class="form-label text-muted">Nombre del Rol <span class="text-danger">*</span></label>
            <input type="text" class="form-control" v-model="form.nombre" placeholder="Ej. Supervisor de Almacén" required maxlength="50">
          </div>
          <div class="col-md-8">
            <label class="form-label text-muted">Descripción</label>
            <input type="text" class="form-control" v-model="form.descripcion" placeholder="Breve explicación de las responsabilidades..." maxlength="255">
          </div>
        </div>

        <h6 class="text-primary mb-3 border-bottom pb-2">Matriz de Acceso (CRUD)</h6>
        
        <div class="table-responsive mb-4">
          <table class="table table-bordered align-middle text-center">
            <thead class="table-light">
              <tr>
                <th class="text-start">Módulo del Sistema</th>
                <th>Ver (Read)</th>
                <th>Crear (Create)</th>
                <th>Editar (Update)</th>
                <th>Eliminar (Delete)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(modulo, index) in modulosSistema" :key="index">
                <td class="text-start fw-semibold text-muted">{{ modulo }}</td>
                <td>
                  <div class="form-check form-switch d-flex justify-content-center">
                    <input class="form-check-input" type="checkbox" role="switch" v-model="matrizPermisos[modulo].leer">
                  </div>
                </td>
                <td>
                  <div class="form-check form-switch d-flex justify-content-center">
                    <input class="form-check-input" type="checkbox" role="switch" v-model="matrizPermisos[modulo].crear">
                  </div>
                </td>
                <td>
                  <div class="form-check form-switch d-flex justify-content-center">
                    <input class="form-check-input" type="checkbox" role="switch" v-model="matrizPermisos[modulo].actualizar">
                  </div>
                </td>
                <td>
                  <div class="form-check form-switch d-flex justify-content-center">
                    <input class="form-check-input" type="checkbox" role="switch" v-model="matrizPermisos[modulo].eliminar">
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-center gap-2">
          <button type="submit" class="btn btn-success">{{ form.id_rol ? 'Guardar Cambios' : 'Guardar Rol y Permisos' }}</button>
          <button type="button" class="btn btn-outline-secondary" @click="cerrarFormulario">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>