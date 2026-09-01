<script setup>
import { computed } from 'vue';
import { badge } from '../helpers.js';

// Etiqueta de estado. Usa el color automático según "estado",
// o uno forzado con "variant" (p. ej. activo/inactivo).
const props = defineProps({
  estado: { type: String, default: '' },
  variant: { type: String, default: '' },
});

const cls = computed(() => props.variant || badge(props.estado));
</script>

<template>
  <span class="badge" :class="cls"><slot>{{ estado }}</slot></span>
</template>