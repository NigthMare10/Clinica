<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import { useInstitution } from '@/Composables/useInstitution';

type Page = { title?: string; content?: string; body?: string };
defineProps<{ page: Page | null }>();
const institution = useInstitution();
</script>

<template>
    <PublicLayout>
        <PageMeta title="Contacto" :description="`${institution.availability} en ${institution.short_name}.`" canonical="/contacto" />
        <section class="contact-visual-hero">
            <div><img src="/images/photography/clinic-exterior-1280.webp" srcset="/images/photography/clinic-exterior-640.webp 640w, /images/photography/clinic-exterior-1280.webp 1280w" sizes="100vw" width="1280" height="853" alt="Imagen institucional ilustrativa de un centro médico moderno" loading="eager" decoding="async" fetchpriority="high"></div>
            <div class="container"><p class="eyebrow">{{ institution.availability }}</p><h1>{{ page?.title || 'Conversemos sobre su salud' }}</h1><p>{{ institution.emergencies }}</p></div>
        </section>
        <section class="section">
            <div class="container contact-panel contact-panel--visible">
                <div><p class="eyebrow">Orientación institucional</p><h2>{{ institution.hours }}</h2><p class="institution-address">{{ institution.address }}</p><div v-if="page?.content || page?.body" class="prose" v-html="page.content || page.body"></div><div class="contact-actions"><Link class="button button--primary" :href="route('public.clinics.index')">Ver cobertura nacional</Link><Link class="button button--outline" :href="route('public.verify.lookup')">Verificar documento</Link></div></div>
                <img src="/images/photography/front-desk-assistance-1280.webp" srcset="/images/photography/front-desk-assistance-640.webp 640w, /images/photography/front-desk-assistance-1280.webp 1280w" sizes="(max-width: 760px) 100vw, 45vw" width="1280" height="719" alt="Imagen institucional ilustrativa de orientación a pacientes" loading="lazy" decoding="async">
            </div>
        </section>
    </PublicLayout>
</template>
