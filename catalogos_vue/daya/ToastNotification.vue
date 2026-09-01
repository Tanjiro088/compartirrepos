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
  <div v-if="show" class="toast" :class="{ err }">{{ msg }}</div>
</template>
