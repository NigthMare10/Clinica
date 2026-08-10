<script setup lang="ts">defineProps<{ status: string }>(); const names: Record<string,string>={DRAFT:'Borrador',PROCESSING:'Procesando',REVIEW_REQUIRED:'Revisión requerida',READY:'Listo',ISSUED:'Emitido',REVOKED:'Revocado',FAILED:'Fallido'};</script>
<template><span class="status" :class="`status--${status.toLowerCase()}`"><i></i>{{ names[status] || status }}</span></template>
