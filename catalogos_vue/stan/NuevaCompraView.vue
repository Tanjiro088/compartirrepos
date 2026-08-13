<template>
    <div class="container mt-4 mb-5">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent text-primary border border-primary text-center py-3">
                <h3 class="mb-0">Registrar Orden de Compra</h3>
            </div>
            <div class="card-body p-4">
                
                <!-- SECCIÓN MAESTRO: Datos Generales de la Compra -->
                <h5 class="text-dark mb-3 border-bottom pb-2">1. Datos Generales (Maestro)</h5>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Proveedor</label>
                        <select class="form-select" v-model="compra.id_proveedor">
                            <option value="" disabled>Seleccione un proveedor...</option>
                            <option value="1">Distribuidora del Sur S.A.C.</option>
                            <option value="2">Insumos del Norte S.A.</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Almacén Destino</label>
                        <select class="form-select" v-model="compra.id_almacen">
                            <option value="" disabled>Seleccione un almacén...</option>
                            <option value="1">Almacén Central</option>
                            <option value="2">Sucursal Norte</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Folio</label>
                        <input type="text" class="form-control" v-model="compra.folio" placeholder="Ej. OC-2026-001">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Compra</label>
                        <input type="date" class="form-control" v-model="compra.fecha_compra">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fecha de Entrega (Estimada)</label>
                        <input type="date" class="form-control" v-model="compra.fecha_entrega">
                    </div>
                </div>

                <!-- SECCIÓN DETALLE: Productos a comprar -->
                <div class="d-flex justify-content-between align-items-end border-bottom pb-2 mb-3">
                    <h5 class="text-dark mb-0">2. Productos (Detalle)</h5>
                    <button class="btn btn-sm btn-outline-primary" @click="agregarFila">+ Agregar Producto</button>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th width="30%">Producto / Presentación</th>
                                <th width="15%">Cantidad</th>
                                <th width="15%">Precio Unit. ($)</th>
                                <th width="15%">Descuento ($)</th>
                                <th width="15%">Subtotal ($)</th>
                                <th width="10%">Opciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(detalle, index) in detalles" :key="index">
                                <td>
                                    <select class="form-select form-select-sm" v-model="detalle.id_presentacion">
                                        <option value="" disabled>Seleccione...</option>
                                        <option value="1">Coca-Cola 2.5L</option>
                                        <option value="2">Hojas de Papel Bond</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm text-center" v-model.number="detalle.cantidad" min="1">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm text-center" v-model.number="detalle.precio_unitario" step="0.01" min="0">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm text-center" v-model.number="detalle.descuento" step="0.01" min="0">
                                </td>
                                <td class="fw-bold bg-light">
                                    {{$formatCurrency( calcularSubtotalFila(detalle)) }}
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" @click="eliminarFila(index)" title="Quitar Fila">✖</button>
                                </td>
                            </tr>
                            <!-- Mensaje visible únicamente cuando el arreglo de productos está vacío -->
                            <tr v-if="detalles.length === 0">
                                <td colspan="6" class="text-muted py-3">No hay productos agregados. Haga clic en "+ Agregar Producto".</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- SECCIÓN TOTALES -->
                <div class="row justify-content-end">
                    <div class="col-md-4">
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Subtotal:
                                <span class="fw-bold">$ {{$formatCurrency( totales.subtotal) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Impuesto (16%):
                                <span class="fw-bold">$ {{$formatCurrency( totales.impuesto) }}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                                <h5 class="mb-0">TOTAL:</h5>
                                <h5 class="mb-0 text-primary fw-bold">$ {{$formatCurrency( totales.total) }}</h5>
                            </li>
                        </ul>
                        <div class="d-grid gap-2">
                            <!-- Ahora tiene el estilo outline-success y se comporta como solicitaste -->
                            <button class="btn btn-outline-success btn-lg" 
                                    :disabled="detalles.length === 0">
                                ✔ Guardar Orden de Compra
                            </button>
                            <button class="btn btn-outline-secondary btn-lg" @click="volverAlHistorial">
                                Cancelar y Volver
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

/**
 * Variable: compra (Maestro)
 * Propósito: Almacena los datos generales del encabezado de la orden.
 */
const compra = ref({
    id_proveedor: '',
    id_almacen: '',
    folio: '',
    fecha_compra: new Date().toISOString().split('T')[0], // Se asigna la fecha actual por defecto del sistema
    fecha_entrega: ''
});

/**
 * Variable: detalles (Detalle)
 * Propósito: Arreglo reactivo que almacena cada fila de productos agregados.
 */
const detalles = ref([]);

/**
 * Función: agregarFila
 * Propósito: Inserta un nuevo objeto vacío en el arreglo de detalles para renderizar una nueva fila en la tabla.
 */
const agregarFila = () => {
    detalles.value.push({
        id_presentacion: '',
        cantidad: 1,
        precio_unitario: 0,
        descuento: 0
    });
};

/**
 * Función: eliminarFila
 * Propósito: Remueve un producto del arreglo de detalles según su índice posicional.
 */
const eliminarFila = (index) => {
    detalles.value.splice(index, 1);
};

/**
 * Función: calcularSubtotalFila
 * Propósito: Ejecuta la fórmula matemática por fila dictada por la lógica de negocio.
 */
const calcularSubtotalFila = (detalle) => {
    const cantidad = parseFloat(detalle.cantidad) || 0;
    const precio = parseFloat(detalle.precio_unitario) || 0;
    const descuento = parseFloat(detalle.descuento) || 0;
    
    let subtotal = (cantidad * precio) - descuento;
    return subtotal > 0 ? subtotal : 0; // Prevenir visualización de montos negativos
};

/**
 * Función Computada: totales
 * Propósito: Recorre el arreglo de detalles en tiempo real para sumar los subtotales, 
 * aplicar el impuesto de ley y generar el total final del documento.
 */
const totales = computed(() => {
    let sumaSubtotal = 0;
    
    detalles.value.forEach(detalle => {
        sumaSubtotal += calcularSubtotalFila(detalle);
    });

    const impuesto = sumaSubtotal * 0.16;
    const total = sumaSubtotal + impuesto;

    return {
        subtotal: sumaSubtotal,
        impuesto: impuesto,
        total: total
    };
});

/**
 * Función: volverAlHistorial
 * Propósito: Navega de regreso a la tabla general de compras descartando los cambios actuales 
 * mediante un aviso de confirmación.
 */
const volverAlHistorial = () => {
    if(confirm('¿Seguro que deseas salir? Los datos no guardados se perderán.')) {
        router.push('/compras');
    }
};
</script>

<style scoped>
/* Estilo general para botones tipo "Outline" que cambian de color al pasar el mouse */
.btn-outline-success { 
    border-color: #198754; 
    color: #198754; 
    background: transparent; 
}
.btn-outline-success:hover { 
    background: #198754; 
    color: white; 
}

.btn-outline-secondary { 
    border-color: #6c757d; 
    color: #6c757d; 
    background: transparent; 
}
.btn-outline-secondary:hover { 
    background: #6c757d; 
    color: white; 
}

/* Ajuste del título */
.card-header {
    border-radius: 0.375rem 0.375rem 0 0;
}
</style>