<script setup>
// Barra reutilizable de búsqueda + filtro por estado para las tablas.
defineProps({
  search: { type: String, default: '' },
  placeholder: { type: String, default: 'Buscar…' },
  estado: { type: String, default: '' },
  estados: { type: Array, default: () => [] }, // [{ value, label }]
  idPrefix: { type: String, default: 'table' },
});
defineEmits(['update:search', 'update:estado']);
</script>

<template>
  <div class="toolbar">
    <input
      class="search"
      :value="search"
      :placeholder="placeholder"
      :data-testid="idPrefix + '-search'"
      @input="$emit('update:search', $event.target.value)"
    />
    <select
      v-if="estados.length"
      class="search"
      :value="estado"
      :data-testid="idPrefix + '-estado-filter'"
      @change="$emit('update:estado', $event.target.value)"
    >
      <option value="">Todos los estados</option>
      <option v-for="e in estados" :key="e.value" :value="e.value">{{ e.label }}</option>
    </select>
  </div>
</template>