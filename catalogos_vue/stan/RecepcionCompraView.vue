<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent text-info border border-info text-center py-3">
                <h3 class="mb-0">Recepción de Mercancía</h3>
            </div>
            
            <div class="card-body p-4">
                <!-- Información del Maestro (Orden de Compra) -->
                <div class="alert alert-secondary mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Folio:</strong> <br> {{ orden.folio }}
                        </div>
                        <div class="col-md-3">
                            <strong>Proveedor:</strong> <br> {{ orden.proveedor }}
                        </div>
                        <div class="col-md-3">
                            <strong>Almacén Destino:</strong> <br> {{ orden.almacen }}
                        </div>
                        <div class="col-md-3">
                            <strong>Fecha Esperada:</strong> <br> {{ orden.fecha_entrega }}
                        </div>
                    </div>
                </div>

                    <h5 class="text-dark mb-3 border-bottom pb-2">Inspección de Productos</h5>                

                <!-- Tabla de Recepción -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Producto / Presentación</th>
                                <th>Cantidad Pedida</th>
                                <th width="20%">Cantidad Recibida</th>
                                <th width="30%">Observaciones (Faltantes/Daños)</th>
                                <th>Estado de Fila</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in detallesCompra" :key="index" :class="{'table-warning': item.cantidad_recibida < item.cantidad_pedida}">
                                <td class="text-start">{{ item.producto }}</td>
                                <td class="fw-bold fs-5">{{ item.cantidad_pedida }}</td>
                                <td>
                                    <!-- Validación frontend para no recibir más de lo pedido ni menos de 0 -->
                                    <input type="number" class="form-control text-center fw-bold text-primary" 
                                           v-model.number="item.cantidad_recibida" 
                                           min="0" :max="item.cantidad_pedida">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           v-model="item.observacion" 
                                           :placeholder="item.cantidad_recibida < item.cantidad_pedida ? 'Especifique el motivo del faltante...' : 'Todo en orden'">
                                </td>
                                <td>
                                    <span class="badge" :class="item.cantidad_recibida === item.cantidad_pedida ? 'bg-success' : 'bg-warning text-dark'">
                                        {{ item.cantidad_recibida === item.cantidad_pedida ? 'Completo' : 'Incompleto' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Resumen y Guardado -->
                <div class="row align-items-center bg-light p-3 rounded">
                    <div class="col-md-7">
                        <label class="form-label fw-bold">Observaciones Generales de la Recepción</label>
                        <textarea class="form-control" v-model="observacionesGenerales" rows="2" placeholder="Ej. El camión llegó con retraso, cajas mojadas..."></textarea>
                    </div>
                    <div class="col-md-5 text-end">
                        <div class="mb-2">
                            Estado resultante de la orden: 
                            <span class="badge fs-6" :class="estadoResultante === 'recibido' ? 'bg-success' : 'bg-warning text-dark'">
                                {{ estadoResultante.toUpperCase() }}
                            </span>
                        </div>
                        <button class="btn btn-outline-info btn-lg fw-bold" @click="confirmarRecepcion">
                            📥 Ingresar al Inventario
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

// Datos simulados del encabezado de la orden de compra a recibir
const orden = ref({
    id_compra: 1,
    folio: 'OC-2026-001',
    proveedor: 'Distribuidora del Sur S.A.C.',
    almacen: 'Almacén Central',
    fecha_entrega: '2026-07-15'
});

// Datos simulados del detalle de la orden. 
// cantidad_recibida se inicializa asumiendo que llegó completo.
const detallesCompra = ref([
    {
        id_presentacion: 1,
        producto: 'Coca-Cola 2.5L - Paquete 12',
        cantidad_pedida: 100,
        cantidad_recibida: 100,
        observacion: ''
    },
    {
        id_presentacion: 2,
        producto: 'Pepsi 3L - Paquete 6',
        cantidad_pedida: 50,
        cantidad_recibida: 50,
        observacion: ''
    }
]);

const observacionesGenerales = ref('');

/**
 * Propiedad Computada: estadoResultante
 * Propósito: Determina si la compra pasa a estado "recibido" (completo) o "parcial" 
 * verificando si algún producto tiene una cantidad recibida menor a la pedida.
 */
const estadoResultante = computed(() => {
    const hayFaltantes = detallesCompra.value.some(item => item.cantidad_recibida < item.cantidad_pedida);
    return hayFaltantes ? 'parcial' : 'recibido';
});

/**
 * Función: confirmarRecepcion
 * Propósito: Simula el envío de los datos de recepción al backend para detonar 
 * la transacción de actualización de inventarios y cuentas por pagar.
 */
const confirmarRecepcion = () => {
    if(confirm(`¿Estás seguro de ingresar estos productos al inventario? La orden se marcará como ${estadoResultante.value.toUpperCase()}.`)) {
        console.log("Datos enviados al backend:", {
            id_compra: orden.value.id_compra,
            estado_nuevo: estadoResultante.value,
            observaciones: observacionesGenerales.value,
            detalles_recibidos: detallesCompra.value.map(d => ({
                id_presentacion: d.id_presentacion,
                cantidad_recibida: d.cantidad_recibida,
                observacion: d.observacion
            }))
        });
        alert("Recepción registrada correctamente. El inventario ha sido actualizado.");
        // Aquí iría el router.push('/compras') para volver a la tabla principal
    }
};
</script>

<style scoped>
.btn-outline-info { 
    border-color: #0dcaf0; 
    color: #0dcaf0; 
    background: transparent; 
}
.btn-outline-info:hover { 
    background: #0dcaf0; 
    color: white; 
}
.btn { transition: all 0.2s ease; }
</style>