<script setup>
// Tabla genérica reutilizable: recibe encabezados y renderiza las filas vía slot.
defineProps({
  headers: { type: Array, default: () => [] },
  empty: { type: Boolean, default: false },
  emptyText: { type: String, default: 'Sin registros' },
});
</script>

<template>
  <div class="card">
    <table>
      <thead>
        <tr>
          <th v-for="(h, i) in headers" :key="i">{{ h }}</th>
        </tr>
      </thead>
      <tbody>
        <slot />
        <tr v-if="empty">
          <td :colspan="headers.length" class="empty">{{ emptyText }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>