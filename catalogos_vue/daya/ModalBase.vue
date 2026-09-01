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
  <div class="overlay" @click.self="$emit('close')">
    <div class="modal-base" :class="{ lg: big }">
      <div class="modal-head">
        <h3>{{ title }}</h3>
        <button class="btn btn-ghost" @click="$emit('close')"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="modal-body">
        <slot />
      </div>
      <div v-if="!hideFooter" class="modal-foot">
        <slot name="footer">
          <button class="btn" @click="$emit('close')">Cancelar</button>
          <button class="btn btn-brand" data-testid="modal-save-btn" @click="$emit('save')">
            <i class="bi bi-check-lg"></i> Guardar
          </button>
        </slot>
      </div>
    </div>
  </div>
</template>