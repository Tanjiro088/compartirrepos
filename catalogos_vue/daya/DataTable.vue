<script setup>
// Tabla genérica reutilizable: recibe encabezados y renderiza las filas vía slot.
defineProps({
  headers: { type: Array, default: () => [] },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: 'Sin registros' },
});
</script>

<template>
  <div class="card shadow-sm border-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th v-for="(h, i) in headers" :key="i" class="py-3 px-3 fw-semibold text-secondary">
              {{ h }}
            </th>
          </tr>
        </thead>
        <tbody>
          <slot />
          <tr v-if="empty">
            <td :colspan="headers.length" class="text-center py-4 text-muted fst-italic">
              {{ emptyText }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
