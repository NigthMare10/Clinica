<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
const props = withDefaults(defineProps<{ title: string; description?: string; noindex?: boolean; canonical?: string }>(), { description: 'Atención médica institucional de Clínica Médica Santa Ana.', noindex: false });
const current = typeof window !== 'undefined' ? window.location : null;
const url = props.canonical ? (current ? new URL(props.canonical, current.origin).href : '') : (current?.href.split('?')[0] || '');
</script>
<template><Head :title="title"><meta name="description" :content="description"><meta property="og:title" :content="`${title} | Clínica Médica Santa Ana`"><meta property="og:description" :content="description"><meta property="og:type" content="website"><meta v-if="url" property="og:url" :content="url"><link v-if="url" rel="canonical" :href="url"><meta v-if="noindex" name="robots" content="noindex,nofollow"></Head></template>
