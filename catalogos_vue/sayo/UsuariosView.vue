<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useGlobalStore } from '../stores/store.js';

// Estado global
const store = useGlobalStore();
const url = store.baseUrl;

// Estados del componente
const usuarios = ref([]);
const mostrarFormulario = ref(false);

// MOCKS TEMPORALES HASTA TENER LOS ENDPOINTS
const rolesActivos = ref([
  { id_rol: 1, nombre: 'Administrador General' },
  { id_rol: 2, nombre: 'Gerente de Sucursal' },
  { id_rol: 3, nombre: 'Cajero' }
]);

const empleadosMock = ref([
  { id_empleado: 1, nombre: 'Juan Pérez (Cajero)' },
  { id_empleado: 2, nombre: 'Carlos Gómez (Gerente)' }
]);

// Filtros y Búsqueda
const filtroRol = ref('todos');
const busqueda = ref('');

// Objeto para el formulario
const form = ref({
  id_usuario: null,
  id_empleado: '',
  usuario: '',
  correo: '',
  password: '',
  id_rol: '',
  activo: true
});

// ===============================
// PETICIONES A LA API (AXIOS)
// ===============================

const obtenerUsuarios = async () => {
  try {
    const respuesta = await axios.get(url + 'usuarios');
    usuarios.value = respuesta.data;
  } catch (error) {
    console.error('Error al obtener usuarios:', error);
  }
};

const guardarUsuario = async () => {
  try {
    if (form.value.id_usuario) {
      await axios.put(`${url}usuarios/${form.value.id_usuario}`, form.value);
    } else {
      await axios.post(`${url}usuarios`, form.value);
    }
    await obtenerUsuarios();
    cerrarFormulario();
  } catch (error) {
    console.error('Error al guardar:', error);
    alert('Ocurrió un error al guardar el usuario. Revisa que el username o correo no existan ya.');
  }
};

const cambiarEstado = async (user) => {
  if (confirm(`¿Estás seguro de ${user.activo ? 'deshabilitar' : 'habilitar'} este usuario?`)) {
    try {
      // Necesitamos pasar la contraseña vacía para que el backend la ignore en la actualización
      const datosActualizados = { ...user, activo: !user.activo, password: '' };
      await axios.put(`${url}usuarios/${user.id_usuario}`, datosActualizados);
      await obtenerUsuarios();
    } catch (error) {
      console.error('Error al cambiar estado:', error);
    }
  }
};

// ===============================
// CONTROLADORES DE INTERFAZ
// ===============================

const abrirNuevo = () => {
  form.value = { 
    id_usuario: null, id_empleado: '', usuario: '', correo: '', 
    password: '', id_rol: '', activo: true 
  };
  mostrarFormulario.value = true;
};

const abrirEditar = (user) => {
  form.value = { 
    ...user, 
    activo: user.activo == 1,
    password: '' // Al editar, la contraseña va vacía por defecto
  };
  mostrarFormulario.value = true;
};

const cerrarFormulario = () => {
  mostrarFormulario.value = false;
};

// Computed property para filtrar la tabla
const usuariosFiltrados = computed(() => {
  let resultado = usuarios.value;

  if (filtroRol.value !== 'todos') {
    resultado = resultado.filter(u => u.id_rol == filtroRol.value);
  }

  if (busqueda.value.trim()) {
    const termino = busqueda.value.toLowerCase();
    resultado = resultado.filter(u => 
      u.usuario.toLowerCase().includes(termino) || 
      (u.correo && u.correo.toLowerCase().includes(termino))
    );
  }

  return resultado;
});

// Cargar datos al montar la vista
onMounted(() => {
  obtenerUsuarios();
});
</script>

<template>
  <div class="container mt-5 mb-5">
    <h2 class="text-center mb-4 text-secondary">Gestión de Usuarios</h2>

    <!-- VISTA DE TABLA -->
    <div v-if="!mostrarFormulario" class="card shadow-sm p-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Cuentas de Acceso</h4>
        <button class="btn btn-primary" @click="abrirNuevo">
          <i class="bi bi-person-plus"></i> Nuevo Usuario
        </button>
      </div>

      <!-- Filtros -->
      <div class="row mb-3 bg-light p-3 rounded align-items-end">
        <div class="col-md-4">
          <label class="form-label text-muted small">Filtrar por Rol</label>
          <select class="form-select form-select-sm" v-model="filtroRol">
            <option value="todos">Todos los roles...</option>
            <option v-for="rol in rolesActivos" :key="rol.id_rol" :value="rol.id_rol">
              {{ rol.nombre }}
            </option>
          </select>
        </div>
        <div class="col-md-8">
          <label class="form-label text-muted small">Búsqueda rápida</label>
          <input type="text" class="form-control form-select-sm" v-model="busqueda" placeholder="Buscar por username o correo...">
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle border">
          <thead class="table-light">
            <tr>
              <th>Username</th>
              <th>Correo Electrónico</th>
              <th>Rol Asignado</th>
              <th class="text-center">Último Acceso</th>
              <th class="text-center">Estado</th>
              <th class="text-center">Opciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in usuariosFiltrados" :key="user.id_usuario">
              <td class="fw-bold text-primary">@{{ user.usuario }}</td>
              <td class="text-muted">{{ user.correo }}</td>
              <td><span class="badge bg-secondary">{{ user.rol_nombre || 'Sin Rol' }}</span></td>
              <td class="text-center small text-muted">{{ user.ultimo_acceso || 'Nunca' }}</td>
              <td class="text-center">
                <span class="badge" :class="user.activo && !user.bloqueado ? 'bg-success' : 'bg-danger'">
                  {{ user.activo && !user.bloqueado ? 'Activo' : 'Inactivo/Bloqueado' }}
                </span>
              </td>
              <td class="text-center text-nowrap">
                <button class="btn btn-sm btn-outline-secondary me-2" title="Editar" @click="abrirEditar(user)">✏️ Editar</button>
                <button class="btn btn-sm" :class="user.activo ? 'btn-outline-danger' : 'btn-outline-success'" @click="cambiarEstado(user)">
                  {{ user.activo ? 'Deshabilitar' : 'Habilitar' }}
                </button>
              </td>
            </tr>
            <tr v-if="usuariosFiltrados.length === 0">
              <td colspan="6" class="text-center text-muted py-4">No se encontraron cuentas de usuario.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- VISTA DE FORMULARIO -->
    <div v-else class="card shadow-sm p-4 w-75 mx-auto">
      <h4 class="text-center mb-4">{{ form.id_usuario ? 'Modificar' : 'Crear' }} Cuenta de Usuario</h4>
      <form @submit.prevent="guardarUsuario">
        
        <div class="mb-4 p-3 bg-light border rounded">
          <label class="form-label text-muted">Vincular a Empleado <span class="text-danger">*</span></label>
          <select class="form-select" v-model="form.id_empleado" required>
            <option value="" disabled>Seleccione el empleado al que pertenece esta cuenta...</option>
            <option v-for="emp in empleadosMock" :key="emp.id_empleado" :value="emp.id_empleado">
              {{ emp.nombre }}
            </option>
          </select>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label text-muted">Nombre de Usuario (Username) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-light">@</span>
              <input type="text" class="form-control" v-model="form.usuario" placeholder="Ej. jperez" required maxlength="50">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Correo Electrónico <span class="text-danger">*</span></label>
            <input type="email" class="form-control" v-model="form.correo" placeholder="correo@empresa.com" required maxlength="150">
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-muted">
              Contraseña 
              <span v-if="!form.id_usuario" class="text-danger">*</span>
              <span v-else class="small text-primary">(Dejar en blanco para no cambiar)</span>
            </label>
            <input type="password" class="form-control" v-model="form.password" placeholder="••••••••" :required="!form.id_usuario" minlength="6">
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Rol en el sistema <span class="text-danger">*</span></label>
            <select class="form-select" v-model="form.id_rol" required>
              <option value="" disabled>Seleccione un rol...</option>
              <option v-for="rol in rolesActivos" :key="rol.id_rol" :value="rol.id_rol">
                {{ rol.nombre }}
              </option>
            </select>
          </div>
        </div>

        <div class="d-flex justify-content-center gap-2">
          <button type="submit" class="btn btn-success">{{ form.id_usuario ? 'Guardar Cambios' : 'Registrar Usuario' }}</button>
          <button type="button" class="btn btn-outline-secondary" @click="cerrarFormulario">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</template>