<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h3 class="fw-bold mb-0">Proveedores</h3>
                    <button class="btn btn-outline-primary" @click="openModal()">+ Nuevo Proveedor</button>
                </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex gap-2">
                    <select class="form-select" style="width: auto" v-model="filtroActivo" @change="recargar">
                        <option :value="null">Todos</option>
                        <option :value="1">Activos</option>
                        <option :value="0">Inactivos</option>
                    </select>
                </div>
                <div class="w-50">
                    <input type="text" class="form-control" v-model="busqueda" placeholder="Buscar por nombre o documento...">
                </div>
            </div>

            <!-- Tabla -->
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>RUC / Doc</th>
                            <th>Nombre Comercial</th>
                            <th>Teléfono</th>
                            <th>Calificación</th>
                            <th>Estado</th>
                            <th>Opciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="prov in proveedoresFiltrados" :key="prov.id_proveedor">
                            <td>{{ prov.id_proveedor }}</td>
                            <td>{{ prov.numero_documento || '-' }}</td>
                            <td>{{ prov.nombre_comercial }}</td>
                            <td>{{ prov.telefono || '-' }}</td>
                            <td>
                                <span class="text-warning">
                                    <span v-for="estrella in 5" :key="estrella"
                                          @click="cambiarCalificacion(prov, estrella)"
                                          :title="'Calificar con ' + estrella + ' estrellas'"
                                          style="cursor: pointer;">
                                        {{ estrella <= prov.calificacion ? '★' : '☆' }}
                                    </span>
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="prov.activo ? 'bg-success' : 'bg-danger'">
                                    {{ prov.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning me-2" @click="openModal(prov)">Actualizar</button>
                                <button class="btn btn-sm btn-outline-danger" @click="alternarEstado(prov)">
                                    {{ prov.activo ? 'Deshabilitar' : 'Habilitar' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="proveedoresFiltrados.length === 0">
                            <td colspan="7" class="text-muted py-3 text-center">{{ busqueda ? 'No se encontraron proveedores con ese criterio.' : 'No se encontraron registros de proveedores.' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Paginador
              :pagina-actual="pagina"
              :total-paginas="totalPaginas"
              @cambiar="cambiarPagina"
            />
          </div>
        </div>

        <!-- Modal de Captura y Edición -->
        <div class="modal fade" id="proveedorModal" tabindex="-1" ref="modalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ isEditing ? 'Actualizar Proveedor' : 'Nuevo Proveedor' }}</h5>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <!-- Tipo de Persona -->
                            <div class="col-md-6 mb-3">
                                <label>Tipo de Persona</label>
                                <select class="form-select" v-model="form.tipo_persona">
                                    <option value="moral">Moral (Empresa)</option>
                                    <option value="fisica">Física (Independiente)</option>
                                </select>
                            </div>
                            <!-- RUC / Documento -->
                            <div class="col-md-6 mb-3">
                                <label>RUC / Documento</label>
                                <input type="text" class="form-control" :class="{'is-invalid': errors.numero_documento}" 
                                       v-model="form.numero_documento" @blur="validarCampo('numero_documento')" placeholder="Ej. 20456789012">
                                <div class="invalid-feedback">{{ errors.numero_documento }}</div>
                            </div>
                            <!-- Nombre Comercial -->
                            <div class="col-md-12 mb-3">
                                <label>Nombre Comercial</label>
                                <input type="text" class="form-control" :class="{'is-invalid': errors.nombre_comercial}" 
                                       v-model="form.nombre_comercial" @blur="validarCampo('nombre_comercial')" placeholder="Nombre visible del negocio">
                                <div class="invalid-feedback">{{ errors.nombre_comercial }}</div>
                            </div>
                            <!-- Teléfono -->
                            <div class="col-md-6 mb-3">
                                <label>Teléfono</label>
                                <input type="text" class="form-control" :class="{'is-invalid': errors.telefono}" 
                                       v-model="form.telefono" @blur="form.telefono = formatearTelefono(form.telefono); validarCampo('telefono')" placeholder="Ej. 55-1234-5678">
                                <div class="invalid-feedback">{{ errors.telefono }}</div>
                            </div>
                            <!-- Correo Electrónico -->
                            <div class="col-md-6 mb-3">
                                <label>Correo Electrónico</label>
                                <input type="email" class="form-control" :class="{'is-invalid': errors.correo}" 
                                       v-model="form.correo" @blur="validarCampo('correo')" placeholder="contacto@empresa.com">
                                <div class="invalid-feedback">{{ errors.correo }}</div>
                            </div>
                            <!-- Condiciones de Pago -->
                            <div class="col-md-12 mb-3">
                                <label>Condiciones de Pago</label>
                                <input type="text" class="form-control" v-model="form.condiciones_pago" placeholder="Ej. Crédito 30 días, Contado, etc.">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" @click="closeModal">Cancelar</button>
                        <button class="btn btn-outline-success" @click="guardarFormulario">{{ isEditing ? 'Guardar Cambios' : 'Registrar' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import {
  obtenerProveedores,
  crearProveedor,
  actualizarProveedor,
  actualizarCalificacionProveedor,
  cambiarEstadoProveedor,
} from '../services/proveedoresService'

const isEditing = ref(false)
const modalRef = ref(null)
const busqueda = ref('')
const filtroActivo = ref(null)
const proveedores = ref([])

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value))

// Estructura limpia del formulario
const form = ref({
    tipo_persona: 'moral',
    numero_documento: '',
    nombre_comercial: '',
    telefono: '',
    correo: '',
    condiciones_pago: ''
});

// Estructura reactiva para el control de errores individuales
const errors = ref({
    numero_documento: '',
    nombre_comercial: '',
    telefono: '',
    correo: ''
});

// Hook inicial del ciclo de vida
onMounted(async () => {
    await cargarProveedoresDesdeServidor();
});

// Consulta principal al servidor Laravel
const cargarProveedoresDesdeServidor = async () => {
  try {
    const params = { pagina: pagina.value, por_pagina: porPagina.value }
    if (filtroActivo.value !== null) params.activo = filtroActivo.value
    const data = await obtenerProveedores(params)
    proveedores.value = data.resultados
    totalRegistros.value = data.total
  } catch {
    alert('No se pudo establecer comunicación con el servidor API. Verifique que el servidor local de Laravel esté en ejecución.')
  }
}

const recargar = () => {
  pagina.value = 1
  cargarProveedoresDesdeServidor()
}

const cambiarPagina = (nuevaPagina) => {
  pagina.value = nuevaPagina
  cargarProveedoresDesdeServidor()
}

const formatearTelefono = (tel) => {
  if (!tel) return tel
  const digits = tel.replace(/\D/g, '')
  if (digits.length === 10) {
    return digits.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3')
  }
  if (digits.length >= 12) {
    return digits.replace(/(\d{2})(\d{4})(\d{4})/, '$1-$2-$3')
  }
  return tel
}

/**
 * Función: validarCampo
 * Propósito: Evaluar instantáneamente la validez de un control de texto al perder el foco (blur).
 */
const validarCampo = (campo) => {
    const valor = form.value[campo] ? form.value[campo].toString().trim() : '';

    if (!valor) {
        errors.value[campo] = 'Campo vacío. Requiere información.';
        return false;
    }

    if (campo === 'numero_documento') {
        const rucRegex = /^\d{11}$/;
        if (!rucRegex.test(valor)) {
            errors.value[campo] = 'Estructura inválida. El RUC requiere exactamente 11 números enteros.';
            return false;
        }
    }

    if (campo === 'telefono') {
        const telRegex = /^\d{7,10}$/;
        if (!telRegex.test(valor)) {
            errors.value[campo] = 'Estructura inválida. Debe ser un número entero (entre 7 y 10 dígitos).';
            return false;
        }
    }

    if (campo === 'correo') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(valor)) {
            errors.value[campo] = 'Estructura inválida. Ingrese un formato de correo electrónico válido.';
            return false;
        }
    }

    errors.value[campo] = '';
    return true;
};

// Validación masiva antes de confirmar el envío de Axios
const validarTodoElFormulario = () => {
    const campos = ['numero_documento', 'nombre_comercial', 'telefono', 'correo'];
    let valido = true;
    campos.forEach(c => {
        if (!validarCampo(c)) valido = false;
    });
    return valido;
};

const cambiarCalificacion = async (prov, nuevaCalificacion) => {
    const calificacionAnterior = prov.calificacion;
    prov.calificacion = nuevaCalificacion;
    try {
        await actualizarCalificacionProveedor(prov.id_proveedor, nuevaCalificacion);
    } catch {
        prov.calificacion = calificacionAnterior;
        alert("Error al registrar la calificación en la base de datos.");
    }
};

const alternarEstado = async (prov) => {
    try {
        const nuevoEstado = !prov.activo;
        await cambiarEstadoProveedor(prov.id_proveedor, nuevoEstado);
        prov.activo = nuevoEstado;
    } catch {
        alert("Error al modificar el estado operacional del proveedor.");
    }
};

const guardarFormulario = async () => {
    if (!validarTodoElFormulario()) {
        alert("Por favor, corrija los errores del formulario antes de continuar.");
        return;
    }
    try {
        if (isEditing.value) {
            await actualizarProveedor(form.value.id_proveedor, form.value);
        } else {
            await crearProveedor(form.value);
        }
        await cargarProveedoresDesdeServidor();
        closeModal();
    } catch {
        alert("Ocurrió un error al intentar guardar la información del proveedor.");
    }
};

// Filtrado de la tabla reactiva (HU-PROV-002: buscar por nombre, RUC, teléfono, correo)
const proveedoresFiltrados = computed(() => {
    if (!busqueda.value) return proveedores.value;
    const q = busqueda.value.toLowerCase();
    return proveedores.value.filter(p =>
        (p.nombre_comercial && p.nombre_comercial.toLowerCase().includes(q)) ||
        (p.numero_documento && p.numero_documento.toLowerCase().includes(q)) ||
        (p.telefono && p.telefono.toLowerCase().includes(q)) ||
        (p.correo && p.correo.toLowerCase().includes(q))
    );
});

const openModal = (prov = null) => {
    // Resetear mapa de errores al abrir
    Object.keys(errors.value).forEach(k => errors.value[k] = '');
    
    if (prov) {
        isEditing.value = true;
        form.value = { ...prov };
    } else {
        isEditing.value = false;
        form.value = { tipo_persona: 'moral', numero_documento: '', nombre_comercial: '', telefono: '', correo: '', condiciones_pago: '' };
    }
    Modal.getOrCreateInstance(modalRef.value).show();
};

const closeModal = () => {
    Modal.getOrCreateInstance(modalRef.value).hide();
};
</script>

<style scoped>
</style>
