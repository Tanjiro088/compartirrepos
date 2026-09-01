<script setup>
// Modal base reutilizable. El contenido va en el slot por defecto;
// el pie tiene botones Cancelar/Guardar por defecto (personalizables vía slot "footer").
defineProps({
  title: { type: String, default: '' },
  big: { type: Boolean, default: false },
  hideFooter: { type: Boolean, default: false },
});
defineEmits(['close', 'save']);
</script>

<template>
  <div class="modal fade show d-block bg-dark bg-opacity-50" tabindex="-1" @click.self="$emit('close')">
    <div class="modal-dialog" :class="{ 'modal-lg': big }">
      <div class="modal-content shadow">
        <div class="modal-header">
          <h5 class="modal-title fw-semibold">{{ title }}</h5>
          <button type="button" class="btn-close" aria-label="Close" @click="$emit('close')"></button>
        </div>
        <div class="modal-body">
          <slot />
        </div>
        <div v-if="!hideFooter" class="modal-footer">
          <slot name="footer">
            <button type="button" class="btn btn-outline-secondary" @click="$emit('close')">Cancelar</button>
            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" data-testid="modal-save-btn" @click="$emit('save')">
              <i class="bi bi-check-lg"></i> Guardar
            </button>
          </slot>
        </div>
      </div>
    </div>
  </div>
</template>
