<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import Pagination from '@/Components/Pagination.vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import type { Paginated, Specialty } from '@/types';

defineProps<{ specialties: Paginated<Specialty> }>();
const image = (specialty: Specialty) => specialty.image_path || '/images/photography/female-doctor-consultation-1280.webp';
const responsiveSet = (path: string) => `${path.replace('-1280.webp', '-640.webp')} 640w, ${path} 1280w`;
useScrollReveal();
</script>

<template>
    <PublicLayout>
        <PageMeta title="Especialidades médicas" description="Atención médica integral y especializada en Clínica Médica Santa Ana." />
        <section class="visual-page-hero visual-page-hero--specialties">
            <img src="/images/photography/clinic-corridor-1280.webp" srcset="/images/photography/clinic-corridor-640.webp 640w, /images/photography/clinic-corridor-1280.webp 1280w" sizes="100vw" width="1280" height="853" alt="Pasillo interior de un centro clínico" loading="eager" decoding="async" fetchpriority="high">
            <div class="visual-page-hero__overlay"></div>
            <div class="container"><p class="eyebrow">Atención multidisciplinaria</p><h1>Especialidades para cuidar cada etapa de tu vida.</h1><p>Profesionales, prevención y seguimiento en una red médica cercana y confiable.</p><div class="hero-stat"><strong>{{ specialties.total }}</strong><span>áreas de atención disponibles</span></div></div>
        </section>
        <section class="section specialty-directory"><div class="container"><div class="directory-heading" data-reveal><div><p class="eyebrow">Encuentra el cuidado que necesitas</p><h2>Atención especializada</h2></div><p>Conoce el enfoque de cada especialidad y encuentra al profesional adecuado para tu consulta.</p></div><div class="specialty-directory__grid"><Link v-for="item in specialties.data" :key="item.id" class="directory-card" :href="route('public.specialties.show', item.slug)" data-reveal><div class="directory-card__image"><img :src="image(item)" :srcset="responsiveSet(image(item))" sizes="(max-width: 760px) calc(100vw - 28px), 380px" width="1280" height="853" :alt="`Consulta de ${item.name}`" loading="lazy" decoding="async"><span>{{ item.name.slice(0, 2).toUpperCase() }}</span></div><div class="directory-card__body"><small>Especialidad médica</small><h2>{{ item.name }}</h2><p>{{ item.short_description || item.description }}</p><b>Ver especialidad <i>→</i></b></div></Link></div><Pagination :links="specialties.links" /></div></section>
    </PublicLayout>
</template>
