<template>
  <div class="container mt-4 mb-5 animate-fade-in">
    <!-- Modal Centralizado para Evitar Salida de Pantalla Completa -->
    <ConfirmModal ref="modalConfirm" />

    <div class="card shadow-sm border-light">
      <div class="card-body p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
          <h3 class="h4 text-dark fw-bold mb-0">Aprobar Mermas y Devoluciones</h3>
          
          <div class="d-flex align-items-center gap-2">
            <button 
              type="button" 
              class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
              :disabled="cargando || Boolean(procesandoId)"
              @click="cargarMermasPendientes"
              title="Sincronizar mermas"
            >
              <span v-if="cargando" class="spinner-border spinner-border-sm"></span>
              <i v-else class="bi bi-arrow-clockwise"></i>
              <span>Actualizar</span>
            </button>
          </div>
        </div>

        <!-- TARJETA DE RESUMEN Y FILTRO -->
        <div class="row g-3 align-items-center mb-4">
          <div class="col-md-7">
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
              <input 
                type="text" 
                class="form-control" 
                placeholder="Buscar por folio, almacén o solicitante..." 
                v-model="busqueda"
                :disabled="cargando"
              />
            </div>
          </div>
          
          <div class="col-md-5">
            <div class="d-flex justify-content-md-end gap-3 text-muted small">
              <div>
                <span>Pendientes: </span>
                <strong class="text-dark">{{ resumenPendientes.totalRegistros }}</strong>
              </div>
              <div>
                <span>Impacto Total: </span>
                <strong class="text-danger">$ {{ formatoMoneda(resumenPendientes.totalMonto) }}</strong>
              </div>
            </div>
          </div>
        </div>
        
        <!-- SPINNER DE CARGA -->
        <div v-if="cargando" class="text-center py-5">
          <div class="spinner-border text-primary" role="status"></div>
          <p class="small text-muted mt-2 mb-0">Sincronizando mermas pendientes y almacenes vigentes...</p>
        </div>

        <!-- TABLA DE MERMAS PENDIENTES -->
        <div v-else class="table-responsive">
          <table class="table table-hover align-middle text-center">
            <thead class="table-light">
              <tr>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Almacén / Sucursal</th>
                <th>Monto</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="merma in mermasFiltradas" :key="merma.id_merma || merma.id">
                <td class="fw-bold text-primary">{{ merma.folio || ('MER-' + (merma.id_merma || merma.id)) }}</td>
                <td>{{ merma.fecha || merma.fecha_merma || '—' }}</td>
                <td class="text-capitalize">{{ (merma.tipo || merma.tipo_merma || 'General').replace('_', ' ') }}</td>
                <td>{{ merma.almacen_nombre || (merma.almacen ? (merma.almacen + (merma.sucursal_nombre ? ' (' + merma.sucursal_nombre + ')' : '')) : 'Almacén general') }}</td>
                <td class="fw-bold text-danger">$ {{ formatoMoneda(merma.monto || merma.total || 0) }}</td>
                <td>
                  <span class="badge bg-secondary">
                    {{ (merma.estado || 'REGISTRADA').toUpperCase() }}
                  </span>
                </td>
                <td>
                  <div class="d-inline-flex gap-2">
                    <button 
                      class="btn btn-sm btn-outline-success fw-semibold d-inline-flex align-items-center gap-1" 
                      :disabled="Boolean(procesandoId)"
                      @click="aprobar(merma)"
                      title="Aprobar merma y descontar inventario"
                    >
                      <span v-if="procesandoId === (merma.id_merma || merma.id)" class="spinner-border spinner-border-sm" role="status"></span>
                      <i v-else class="bi bi-check-lg"></i>
                      <span>Aprobar</span>
                    </button>
                    <button 
                      class="btn btn-sm btn-outline-danger fw-semibold d-inline-flex align-items-center gap-1" 
                      :disabled="Boolean(procesandoId)"
                      @click="rechazar(merma)"
                      title="Rechazar merma con motivo"
                    >
                      <i class="bi bi-x-lg"></i>
                      <span>Rechazar</span>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="mermasFiltradas.length === 0">
                <td colspan="7" class="text-muted py-4 fst-italic">
                  {{ busqueda ? 'No se encontraron mermas coincidentes con el filtro de búsqueda.' : 'No hay mermas pendientes de aprobación en almacenes activos.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useGlobalStore } from '@/stores/store.js'
import { obtenerMermasPendientes, actualizarEstadoMerma } from '@/services/mermasService'
import ConfirmModal from '@/components/ConfirmModal.vue'

const store = useGlobalStore()
const url = computed(() => {
  const base = store.baseUrl || ''
  return base.endsWith('/') ? base : `${base}/`
})

// Referencia al modal interactivo
const modalConfirm = ref(null)

const mermasPendientes = ref([])
const almacenesActivos = ref([])
const cargando = ref(false)
const procesandoId = ref(null)
const busqueda = ref('')

const formatoMoneda = (valor) => {
  return Number(valor || 0).toLocaleString('es-MX', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

// Extrae el mensaje de error con soporte para respuestas 422 de Laravel
const extraerMensajeError = (error, mensajeDefecto = 'Ocurrió un error en la operación.') => {
  if (error?.response?.data) {
    const data = error.response.data
    if (data.errors && typeof data.errors === 'object') {
      const primerCampo = Object.keys(data.errors)[0]
      if (primerCampo && Array.isArray(data.errors[primerCampo]) && data.errors[primerCampo].length) {
        return data.errors[primerCampo][0]
      }
    }
    return data.Mensaje || data.message || data.error || mensajeDefecto
  }
  return error?.message || mensajeDefecto
}

// Mermas filtradas por texto (folio, almacén, solicitante o motivo)
const mermasFiltradas = computed(() => {
  const lista = Array.isArray(mermasPendientes.value) ? mermasPendientes.value : []
  if (!busqueda.value.trim()) return lista
  const q = busqueda.value.toLowerCase().trim()

  return lista.filter(m => {
    return [
      m.folio,
      m.motivo,
      m.almacen,
      m.almacen_nombre,
      m.usuario,
      m.solicitante
    ].some(v => String(v || '').toLowerCase().includes(q))
  })
})

// Totales acumulados pendientes de dictamen
const resumenPendientes = computed(() => {
  const lista = mermasFiltradas.value
  const totalMonto = lista.reduce((acc, m) => acc + (Number(m.monto || m.total || 0)), 0)
  return {
    totalRegistros: lista.length,
    totalMonto: Math.round(totalMonto * 100) / 100
  }
})

// 📥 Cargar catálogo de almacenes estrictamente activos
const cargarAlmacenesActivos = async () => {
  try {
    let datosAlms = []
    try {
      const res = await axios.get(`${url.value}inventarios/catalogo-almacenes`)
      datosAlms = Array.isArray(res.data) ? res.data : (res.data.data || [])
    } catch {
      const resFallback = await axios.get(`${url.value}almacenes`)
      datosAlms = Array.isArray(resFallback.data) ? resFallback.data : (resFallback.data.data || [])
    }

    almacenesActivos.value = datosAlms.filter(alm => {
      const estaActivo = alm.activo === 1 || alm.activo === true || alm.activo === '1' || alm.activo === undefined
      const noEliminado = !alm.deleted_at
      return estaActivo && noEliminado
    })
  } catch (error) {
    almacenesActivos.value = []
    console.error("Error al cargar almacenes activos:", error)
  }
}

// 📥 Cargar mermas pendientes y filtrar solo las de almacenes vigentes
const cargarMermasPendientes = async () => {
  cargando.value = true
  try {
    await cargarAlmacenesActivos()
    const data = await obtenerMermasPendientes()
    const rawMermas = Array.isArray(data) ? data : (data.data || data.mermas || [])

    const idsAlmacenesValidos = almacenesActivos.value.map(a => Number(a.id_almacen || a.id))

    // Si existen almacenes activos, filtrar mermas de almacenes no vigentes
    mermasPendientes.value = rawMermas.filter(m => {
      if (idsAlmacenesValidos.length === 0) return true
      const idAlm = Number(m.id_almacen)
      return !idAlm || idsAlmacenesValidos.includes(idAlm)
    })
  } catch (error) {
    console.error('Error al cargar mermas pendientes:', error)
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Error de Conexión',
        mensaje: 'No fue posible cargar las mermas pendientes desde el servidor.',
        tipo: 'danger',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
  } finally {
    cargando.value = false
  }
}

// ✅ Aprobar merma con confirmación modal y prevención de doble clic
const aprobar = async (merma) => {
  const id = merma.id_merma || merma.id || merma
  if (procesandoId.value === id) return

  const folioTxt = merma.folio ? ` (Folio: ${merma.folio})` : ` #${id}`
  const montoTxt = merma.monto ? ` por $${formatoMoneda(merma.monto)}` : ''

  if (modalConfirm.value) {
    const confirmado = await modalConfirm.value.open({
      titulo: '¿Aprobar Merma?',
      mensaje: `¿Está seguro de autorizar la merma${folioTxt}${montoTxt}?\n\nEsta acción descontará las existencias físicas del inventario de forma irreversible.`,
      tipo: 'warning',
      mostrarCancelar: true,
      textoConfirmar: 'Sí, Aprobar y Descontar',
      textoCancelar: 'Cancelar'
    })
    if (!confirmado) return
  }

  procesandoId.value = id
  try {
    await actualizarEstadoMerma(id, 'aprobada')
    
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Merma Aprobada',
        mensaje: `La merma${folioTxt} ha sido aprobada y el inventario se actualizó exitosamente.`,
        tipo: 'success',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
    await cargarMermasPendientes()
  } catch (error) {
    console.error('Error al aprobar merma:', error)
    const msg = extraerMensajeError(error, `Error al aprobar la merma #${id}.`)
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Error al Aprobar',
        mensaje: msg,
        tipo: 'danger',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
  } finally {
    procesandoId.value = null
  }
}

// ❌ Rechazar merma con captura de motivo en el modal
const rechazar = async (merma) => {
  const id = merma.id_merma || merma.id || merma
  if (procesandoId.value === id) return

  const folioTxt = merma.folio ? ` (Folio: ${merma.folio})` : ` #${id}`
  let motivo = ''
  
  if (modalConfirm.value) {
    motivo = await modalConfirm.value.open({
      titulo: 'Rechazar Merma',
      mensaje: `Especifique la justificación para rechazar la merma${folioTxt}:`,
      tipo: 'danger',
      input: true,
      inputLabel: 'Motivo del rechazo (obligatorio):',
      inputPlaceholder: 'Ej. Producto apto para reacondicionar / Registro erróneo...',
      inputType: 'text',
      textoConfirmar: 'Rechazar Merma',
      textoCancelar: 'Cancelar'
    })
  }

  if (!motivo || !motivo.toString().trim()) return
  
  procesandoId.value = id
  try {
    await actualizarEstadoMerma(id, 'rechazada', motivo.toString().trim())
    
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Merma Rechazada',
        mensaje: `La merma${folioTxt} ha sido rechazada correctamente.`,
        tipo: 'warning',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
    await cargarMermasPendientes()
  } catch (error) {
    console.error('Error al rechazar merma:', error)
    const msg = extraerMensajeError(error, `Error al rechazar la merma #${id}.`)
    if (modalConfirm.value) {
      await modalConfirm.value.open({
        titulo: 'Error al Rechazar',
        mensaje: msg,
        tipo: 'danger',
        mostrarCancelar: false,
        textoConfirmar: 'Aceptar'
      })
    }
  } finally {
    procesandoId.value = null
  }
}

onMounted(async () => {
  await cargarMermasPendientes()
})
</script>

<style scoped>
</style>
