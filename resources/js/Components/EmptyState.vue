<script setup lang="ts">defineProps<{ title?: string; text?: string }>();</script>
<template><div class="empty-state" role="status"><span aria-hidden="true">+</span><h3>{{ title || 'Información pendiente de confirmación' }}</h3><p>{{ text || 'Este contenido se publicará cuando haya sido verificado por la clínica.' }}</p></div></template>
