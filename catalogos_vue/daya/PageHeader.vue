<script setup>
// Encabezado de página con título, descripción y slot de acción (botón).
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
});
</script>

<template>
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
    <div class="mb-3 mb-md-0">
      <h1 class="h3 fw-bold mb-1">{{ title }}</h1>
      <p v-if="subtitle" class="text-muted mb-0">{{ subtitle }}</p>
    </div>
    <div>
      <slot name="action" />
    </div>
  </div>
</template>
