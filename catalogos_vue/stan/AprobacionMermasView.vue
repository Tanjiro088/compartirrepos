<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm border-light">
            <div class="card-body p-4">
                <h3 class="text-center mb-4 text-dark fw-bold">Aprobar Mermas y Devoluciones</h3>
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Almacén</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="merma in mermasPendientes" :key="merma.id_merma">
                                <td class="fw-bold">{{ merma.folio }}</td>
                                <td>{{ merma.fecha }}</td>
                                <td>{{ merma.tipo }}</td>
                                <td>{{ merma.almacen }}</td>
                                <td class="fw-bold" :class="'text-status-' + merma.estado">$ {{ $formatCurrency(merma.monto || 0) }}</td>
                                <td><span class="badge bg-secondary">REGISTRADA</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-success me-2" @click="aprobar(merma.id_merma)">Aprobar</button>
                                    <button class="btn btn-sm btn-outline-danger" @click="rechazar(merma.id_merma)">Rechazar</button>
                                </td>
                            </tr>
                            <tr v-if="mermasPendientes.length === 0">
                                <td colspan="7" class="text-muted py-3">No hay mermas pendientes de aprobación.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { obtenerMermasPendientes, actualizarEstadoMerma } from '../services/mermasService'

const mermasPendientes = ref([])

onMounted(async () => { await cargarMermasPendientes() })

const cargarMermasPendientes = async () => {
    // TODO: Agregar paginación cuando el endpoint obtenerMermasPendientes lo soporte
    try {
        mermasPendientes.value = await obtenerMermasPendientes()
    } catch (error) {
        console.error(error)
        alert('Error al cargar mermas pendientes desde el servidor.')
    }
}

const aprobar = async (id) => {
    try {
        await actualizarEstadoMerma(id, 'aprobada')
        await cargarMermasPendientes()
    } catch (error) {
        console.error(error)
        alert(`Error al aprobar merma ${id}.`)
    }
}

const rechazar = async (id) => {
    const motivo = window.prompt('Motivo del rechazo (obligatorio):')
    if (!motivo || !motivo.trim()) return
    try {
        await actualizarEstadoMerma(id, 'rechazada', motivo.trim())
        await cargarMermasPendientes()
    } catch (error) {
        console.error(error)
        const msg = error?.response?.data?.errors?.motivo?.[0] || `Error al rechazar merma ${id}.`
        alert(msg)
    }
}
</script>

<style scoped>
</style>

