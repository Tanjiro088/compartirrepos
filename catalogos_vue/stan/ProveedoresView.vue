<template>
    <div class="container mt-4 mb-5 animate-fade-in">
        <!-- Modal Centralizado para Pantalla Completa -->
        <ConfirmModal ref="modalConfirm" />

        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                    <div>
                        <h3 class="fw-bold mb-0 text-primary">Proveedores</h3>
                        <p class="text-muted small mb-0">Catálogo general de proveedores, condiciones de pago y calificaciones.</p>
                    </div>
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1 shadow-sm" @click="openModal()">
                        <i class="bi bi-plus-lg"></i> Nuevo Proveedor
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <select class="form-select form-select-sm" style="width: auto" v-model="filtroActivo" @change="recargar">
                            <option :value="null">Todos los estados</option>
                            <option :value="1">Activos</option>
                            <option :value="0">Inactivos</option>
                        </select>
                    </div>
                    <div class="w-100 w-md-50">
                        <input 
                            type="text" 
                            class="form-control form-control-sm" 
                            v-model="busqueda" 
                            @input="recargar"
                            placeholder="Buscar por nombre, documento o teléfono..."
                        >
                    </div>
                </div>

                <!-- Tabla -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th width="8%">ID</th>
                                <th width="15%">Documento</th>
                                <th width="25%">Nombre Comercial</th>
                                <th width="15%">Teléfono</th>
                                <th width="15%">Calificación</th>
                                <th width="10%">Estado</th>
                                <th width="12%">Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="prov in proveedoresFiltrados" :key="prov.id_proveedor || prov.id">
                                <td class="fw-semibold font-monospace">{{ prov.id_proveedor || prov.id }}</td>
                                <td class="font-monospace">{{ prov.numero_documento || '—' }}</td>
                                <td class="fw-semibold text-start">{{ prov.nombre_comercial }}</td>
                                <td class="font-monospace">{{ formatearTelefono(prov.telefono) || '—' }}</td>
                                <td>
                                    <span class="text-warning">
                                        <span 
                                            v-for="estrella in 5" 
                                            :key="estrella"
                                            @click="cambiarCalificacion(prov, estrella)"
                                            :title="'Calificar con ' + estrella + ' estrellas'"
                                            style="cursor: pointer;"
                                        >
                                            {{ estrella <= (prov.calificacion || 0) ? '★' : '☆' }}
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="prov.activo ? 'bg-success' : 'bg-secondary'">
                                        {{ prov.activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-inline-flex gap-1 justify-content-center">
                                        <button 
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary" 
                                            @click="openModal(prov)" 
                                            title="Actualizar datos"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button 
                                            type="button"
                                            class="btn btn-sm" 
                                            :class="prov.activo ? 'btn-outline-danger' : 'btn-outline-success'" 
                                            @click="alternarEstado(prov)"
                                            :title="prov.activo ? 'Deshabilitar proveedor' : 'Habilitar proveedor'"
                                        >
                                            <i :class="prov.activo ? 'bi bi-slash-circle' : 'bi bi-check-lg'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="proveedoresFiltrados.length === 0">
                                <td colspan="7" class="text-muted py-4 text-center fst-italic">
                                    {{ busqueda ? 'No se encontraron proveedores con ese criterio.' : 'No se encontraron registros de proveedores.' }}
                                </td>
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
                <div class="modal-content shadow">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold text-primary">{{ isEditing ? 'Actualizar Proveedor' : 'Nuevo Proveedor' }}</h5>
                        <button type="button" class="btn-close" @click="closeModal" :disabled="isSaving"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Tipo de Persona -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Tipo de Persona *</label>
                                <select class="form-select" v-model="form.tipo_persona" :disabled="isSaving">
                                    <option value="moral">Moral (Empresa / R.L.)</option>
                                    <option value="fisica">Física (Independiente)</option>
                                </select>
                            </div>
                            
                            <!-- RUC / Documento -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">RUC / Documento Fiscal *</label>
                                <input 
                                    type="text" 
                                    class="form-control font-monospace" 
                                    :class="{'is-invalid': errors.numero_documento}" 
                                    v-model.trim="form.numero_documento" 
                                    @blur="validarCampo('numero_documento')" 
                                    placeholder="Ej. 20456789012"
                                    :disabled="isSaving"
                                >
                                <div class="invalid-feedback">{{ errors.numero_documento }}</div>
                            </div>

                            <!-- Nombre Comercial -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Nombre Comercial / Razón Social *</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    :class="{'is-invalid': errors.nombre_comercial}" 
                                    v-model.trim="form.nombre_comercial" 
                                    @blur="validarCampo('nombre_comercial')" 
                                    placeholder="Nombre visible o denominación del negocio"
                                    :disabled="isSaving"
                                >
                                <div class="invalid-feedback">{{ errors.nombre_comercial }}</div>
                            </div>

                            <!-- Teléfono -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Teléfono de Contacto *</label>
                                <input 
                                    type="text" 
                                    class="form-control font-monospace" 
                                    :class="{'is-invalid': errors.telefono}" 
                                    v-model.trim="form.telefono" 
                                    @blur="form.telefono = formatearTelefono(form.telefono); validarCampo('telefono')" 
                                    placeholder="Ej. 5512345678"
                                    :disabled="isSaving"
                                >
                                <div class="invalid-feedback">{{ errors.telefono }}</div>
                            </div>

                            <!-- Correo Electrónico -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Correo Electrónico *</label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    :class="{'is-invalid': errors.correo}" 
                                    v-model.trim="form.correo" 
                                    @blur="validarCampo('correo')" 
                                    placeholder="contacto@empresa.com"
                                    :disabled="isSaving"
                                >
                                <div class="invalid-feedback">{{ errors.correo }}</div>
                            </div>

                            <!-- Condiciones de Pago -->
                            <div class="col-12">
                                <label class="form-label fw-semibold small">Condiciones de Pago</label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    v-model.trim="form.condiciones_pago" 
                                    placeholder="Ej. Crédito a 30 días, Contado, 50% anticipo"
                                    :disabled="isSaving"
                                >
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary px-4" @click="closeModal" :disabled="isSaving">
                            Cancelar
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-success px-4 fw-bold text-white d-inline-flex align-items-center gap-2" 
                            @click="guardarFormulario" 
                            :disabled="isSaving"
                        >
                            <span v-if="isSaving" class="spinner-border spinner-border-sm" role="status"></span>
                            <span>{{ isSaving ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Registrar Proveedor') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ToastNotification ref="toast" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Modal } from 'bootstrap'
import Paginador from '@/components/Paginador.vue'
import ConfirmModal from '@/components/ConfirmModal.vue'
import ToastNotification from '@/components/ToastNotification.vue'
import {
  obtenerProveedores,
  crearProveedor,
  actualizarProveedor,
  actualizarCalificacionProveedor,
  cambiarEstadoProveedor,
} from '@/services/proveedoresService'

const modalConfirm = ref(null)
const toast = ref(null)
const isEditing = ref(false)
const isSaving = ref(false)
const modalRef = ref(null)

const busqueda = ref('')
const filtroActivo = ref(null)
const proveedores = ref([])

const pagina = ref(1)
const porPagina = ref(8)
const totalRegistros = ref(0)
const totalPaginas = computed(() => Math.ceil(totalRegistros.value / porPagina.value) || 1)

// Estructura limpia del formulario
const form = ref({
    tipo_persona: 'moral',
    numero_documento: '',
    nombre_comercial: '',
    telefono: '',
    correo: '',
    condiciones_pago: ''
})

// Estructura reactiva para el control de errores individuales
const errors = ref({
    numero_documento: '',
    nombre_comercial: '',
    telefono: '',
    correo: ''
})

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Error en la operación del proveedor.') => {
  if (error?.response?.data) {
    const data = error.response.data
    if (data.errors && typeof data.errors === 'object') {
      const primerCampo = Object.keys(data.errors)[0]
      if (primerCampo && Array.isArray(data.errors[primerCampo]) && data.errors[primerCampo].length) {
        return data.errors[primerCampo][0]
      }
    }
    return data.message || data.Mensaje || data.error || mensajeDefecto
  }
  return error?.message || mensajeDefecto
}

// Hook inicial del ciclo de vida
onMounted(async () => {
    await cargarProveedoresDesdeServidor()
})

// Consulta principal al servidor Laravel
const cargarProveedoresDesdeServidor = async () => {
  try {
    const params = { pagina: pagina.value, por_pagina: porPagina.value }
    if (filtroActivo.value !== null) params.activo = filtroActivo.value
    if (busqueda.value.trim()) params.search = busqueda.value.trim()

    const data = await obtenerProveedores(params)
    proveedores.value = Array.isArray(data) ? data : (data.resultados || data.data || [])
    totalRegistros.value = data.total || proveedores.value.length
  } catch (error) {
    console.error('Error al conectar con el servidor:', error)
    const msg = extraerMensajeError(error, 'No se pudo establecer comunicación con el servidor API.')
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Error de Comunicación',
        mensaje: msg,
        tipo: 'danger',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
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
    const valor = form.value[campo] ? form.value[campo].toString().trim() : ''

    if (!valor) {
        errors.value[campo] = 'Campo vacío. Requiere información.'
        return false
    }

    if (campo === 'numero_documento') {
        const rucRegex = /^\d{11}$/
        if (!rucRegex.test(valor)) {
            errors.value[campo] = 'Estructura inválida. El documento requiere exactamente 11 números.'
            return false
        }
    }

    if (campo === 'telefono') {
        const telRegex = /^\d{7,10}$/
        const rawDigits = valor.replace(/\D/g, '')
        if (!telRegex.test(rawDigits)) {
            errors.value[campo] = 'Estructura inválida. Debe ser un número entre 7 y 10 dígitos.'
            return false
        }
    }

    if (campo === 'correo') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailRegex.test(valor)) {
            errors.value[campo] = 'Estructura inválida. Ingrese un correo electrónico válido.'
            return false
        }
    }

    errors.value[campo] = ''
    return true
}

// Validación masiva antes de confirmar el envío
const validarTodoElFormulario = () => {
    const campos = ['numero_documento', 'nombre_comercial', 'telefono', 'correo']
    let valido = true
    campos.forEach(c => {
        if (!validarCampo(c)) valido = false
    })
    return valido
}

const cambiarCalificacion = async (prov, nuevaCalificacion) => {
    const idProv = prov.id_proveedor || prov.id
    const calificacionAnterior = prov.calificacion
    prov.calificacion = nuevaCalificacion
    try {
        await actualizarCalificacionProveedor(idProv, nuevaCalificacion)
        toast.value?.notify?.('Calificación actualizada')
    } catch (error) {
        prov.calificacion = calificacionAnterior
        console.error('Error al calificar proveedor:', error)
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error de Calificación',
                mensaje: 'No se pudo registrar la calificación en la base de datos.',
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    }
}

const alternarEstado = async (prov) => {
    const idProv = prov.id_proveedor || prov.id
    const nuevoEstado = !prov.activo
    const nombreProv = prov.nombre_comercial || 'este proveedor'

    if (modalConfirm.value) {
        const confirmado = await modalConfirm.value.open({
            titulo: nuevoEstado ? 'Activar Proveedor' : 'Desactivar Proveedor',
            mensaje: `¿Estás seguro de que deseas ${nuevoEstado ? 'activar' : 'desactivar'} a "${nombreProv}"?`,
            tipo: nuevoEstado ? 'warning' : 'danger',
            mostrarCancelar: true,
            textoConfirmar: nuevoEstado ? 'Sí, Activar' : 'Sí, Desactivar',
            textoCancelar: 'Cancelar'
        })
        if (!confirmado) return
    }

    try {
        await cambiarEstadoProveedor(idProv, nuevoEstado)
        prov.activo = nuevoEstado
        toast.value?.notify?.(`Proveedor ${nuevoEstado ? 'activado' : 'desactivado'}`)
    } catch (error) {
        console.error('Error al cambiar estado:', error)
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error Operacional',
                mensaje: 'No se pudo modificar el estado operacional del proveedor.',
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    }
}

const guardarFormulario = async () => {
    if (isSaving.value) return

    if (!validarTodoElFormulario()) {
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Formulario Incompleto',
                mensaje: 'Por favor, corrija los errores marcados antes de continuar.',
                tipo: 'warning',
                mostrarCancelar: false,
                textoConfirmar: 'Revisar'
            })
        }
        return
    }

    isSaving.value = true
    try {
        const payload = {
            ...form.value,
            numero_documento: String(form.value.numero_documento || '').trim(),
            nombre_comercial: String(form.value.nombre_comercial || '').trim(),
            correo: String(form.value.correo || '').trim().toLowerCase(),
            telefono: String(form.value.telefono || '').trim()
        }

        if (isEditing.value && form.value.id_proveedor) {
            await actualizarProveedor(form.value.id_proveedor, payload)
        } else {
            await crearProveedor(payload)
        }

        await cargarProveedoresDesdeServidor()
        closeModal()

        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: '¡Operación Exitosa!',
                mensaje: `La información del proveedor "${payload.nombre_comercial}" fue guardada correctamente.`,
                tipo: 'success',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
        toast.value?.notify?.('Proveedor guardado con éxito')
    } catch (error) {
        console.error('Error al guardar proveedor:', error)
        if (error.response && error.response.status === 422 && error.response.data?.errors) {
            const errs = error.response.data.errors
            Object.keys(errs).forEach(k => {
                if (errors.value[k] !== undefined) {
                    errors.value[k] = errs[k][0]
                }
            })
        }
        const msg = extraerMensajeError(error, 'Ocurrió un error al intentar guardar la información.')
        if (modalConfirm.value) {
            await modalConfirm.value.open({
                titulo: 'Error al Guardar',
                mensaje: msg,
                tipo: 'danger',
                mostrarCancelar: false,
                textoConfirmar: 'Aceptar'
            })
        }
    } finally {
        isSaving.value = false
    }
}

// Filtrado de la tabla reactiva
const proveedoresFiltrados = computed(() => {
    if (!busqueda.value) return proveedores.value
    const q = busqueda.value.toLowerCase()
    return proveedores.value.filter(p =>
        (p.nombre_comercial && p.nombre_comercial.toLowerCase().includes(q)) ||
        (p.numero_documento && p.numero_documento.toLowerCase().includes(q)) ||
        (p.telefono && p.telefono.toLowerCase().includes(q)) ||
        (p.correo && p.correo.toLowerCase().includes(q))
    )
})

const openModal = (prov = null) => {
    Object.keys(errors.value).forEach(k => errors.value[k] = '')
    
    if (prov) {
        isEditing.value = true
        form.value = { ...prov }
    } else {
        isEditing.value = false
        form.value = { 
            tipo_persona: 'moral', 
            numero_documento: '', 
            nombre_comercial: '', 
            telefono: '', 
            correo: '', 
            condiciones_pago: '' 
        }
    }
    Modal.getOrCreateInstance(modalRef.value).show()
}

const closeModal = () => {
    Modal.getOrCreateInstance(modalRef.value).hide()
}
</script>

<style scoped>
</style>