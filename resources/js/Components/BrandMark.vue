<script setup lang="ts">defineProps<{ compact?: boolean; light?: boolean }>();</script>
<template><span class="brand" :class="{ 'brand--light': light }"><span class="brand__mark" aria-hidden="true"><i></i><b></b></span><span v-if="!compact" class="brand__text"><strong>Santa Ana</strong><small>Clínica Médica</small></span></span></template>
