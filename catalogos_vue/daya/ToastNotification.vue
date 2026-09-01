<script setup>
import { ref } from 'vue';

// Notificación flotante reutilizable. Encapsula toda su lógica y la expone
// con defineExpose para llamarla desde cualquier vista: toast.value.notify('...').
const show = ref(false);
const msg = ref('');
const err = ref(false);
let timer = null;

function notify(m, e = false) {
  msg.value = m;
  err.value = e;
  show.value = true;
  clearTimeout(timer);
  timer = setTimeout(() => {
    show.value = false;
  }, 2600);
}

function apiErr(e) {
  notify(e?.response?.data?.message || 'Error en la operación', true);
}

defineExpose({ notify, apiErr });
</script>

<template>
  <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div v-if="show" class="toast show align-items-center text-white border-0" :class="err ? 'bg-danger' : 'bg-success'" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2">
          <i :class="['bi', err ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill', 'fs-5']"></i>
          <span>{{ msg }}</span>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="show = false" aria-label="Close"></button>
      </div>
    </div>
  </div>
</template>
