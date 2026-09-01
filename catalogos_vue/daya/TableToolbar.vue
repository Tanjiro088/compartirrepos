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
  <div class="row g-3 mb-4">
    <div :class="estados.length ? 'col-12 col-md-8' : 'col-12'">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted">
          <i class="bi bi-search"></i>
        </span>
        <input
          type="text"
          class="form-control border-start-0 ps-0"
          :value="search"
          :placeholder="placeholder"
          :data-testid="idPrefix + '-search'"
          @input="$emit('update:search', $event.target.value)"
        />
      </div>
    </div>
    <div v-if="estados.length" class="col-12 col-md-4">
      <select
        class="form-select"
        :value="estado"
        :data-testid="idPrefix + '-estado-filter'"
        @change="$emit('update:estado', $event.target.value)"
      >
        <option value="">Todos los estados</option>
        <option v-for="e in estados" :key="e.value" :value="e.value">{{ e.label }}</option>
      </select>
    </div>
  </div>
</template>
