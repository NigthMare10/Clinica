<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import { useInstitution } from '@/Composables/useInstitution';

type Page = { title?: string; content?: string; body?: string };
defineProps<{ page: Page | null }>();
const institution = useInstitution();
</script>

<template>
    <PublicLayout>
        <PageMeta title="Contacto" :description="`${institution.availability} en ${institution.short_name}.`" />
        <section class="contact-visual-hero">
            <div><img src="/images/photography/clinic-exterior-1280.webp" srcset="/images/photography/clinic-exterior-640.webp 640w, /images/photography/clinic-exterior-1280.webp 1280w" sizes="100vw" width="1280" height="853" alt="Fachada de un centro médico moderno" decoding="async" fetchpriority="high"></div>
            <div class="container"><p class="eyebrow">{{ institution.availability }}</p><h1>{{ page?.title || 'Conversemos sobre su salud' }}</h1><p>{{ institution.emergencies }}</p></div>
        </section>
        <section class="section">
            <div class="container contact-panel contact-panel--visible">
                <div><p class="eyebrow">{{ institution.short_name }}</p><h2>{{ institution.hours }}</h2><p class="institution-address">{{ institution.address }}</p><a class="contact-phone" :href="institution.phone_uri">{{ institution.phone }}</a><div class="contact-actions"><a class="button button--primary" :href="institution.phone_uri">Llamar ahora</a><a class="button button--outline" :href="institution.whatsapp_uri" target="_blank" rel="noopener">WhatsApp</a></div></div>
                <img src="/images/photography/front-desk-assistance-1280.webp" srcset="/images/photography/front-desk-assistance-640.webp 640w, /images/photography/front-desk-assistance-1280.webp 1280w" sizes="(max-width: 760px) 100vw, 45vw" width="1280" height="719" alt="Personal brindando asistencia institucional a una paciente" loading="lazy" decoding="async">
            </div>
        </section>
    </PublicLayout>
</template>
