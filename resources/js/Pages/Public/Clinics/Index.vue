<script setup lang="ts">
import { computed, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import type { Clinic } from '@/types';

const props = defineProps<{ clinics: Clinic[] }>();
const query = ref('');
const selectedClinic = ref<string | null>(null);
const filtered = computed(() => props.clinics.filter((clinic) => clinic.department.toLocaleLowerCase('es').includes(query.value.toLocaleLowerCase('es'))));
const selected = computed(() => props.clinics.find((clinic) => clinic.id === selectedClinic.value));
const pointStyle = (clinic: Clinic) => ({
    left: `${((70 + ((((clinic.longitude as number) + 89.5) / 6.5) * 760)) / 900) * 100}%`,
    top: `${((60 + (((16.2 - (clinic.latitude as number)) / 3.5) * 390)) / 540) * 100}%`,
});
const focusClinic = (clinic: Clinic) => { selectedClinic.value = clinic.id; };
useScrollReveal();
</script>

<template>
    <PublicLayout>
        <PageMeta title="Red de clínicas" description="18 ubicaciones de cobertura de Clínica Médica Santa Ana en Honduras." canonical="/clinicas" />
        <section class="network-hero network-hero--visual"><div class="network-hero__image"><img src="/images/photography/clinic-reception-1280.webp" srcset="/images/photography/clinic-reception-640.webp 640w, /images/photography/clinic-reception-1280.webp 1280w" sizes="100vw" width="1280" height="960" alt="Imagen institucional ilustrativa de un centro médico moderno" loading="eager" decoding="async" fetchpriority="high"></div><div class="container"><p class="eyebrow">Cobertura nacional</p><h1>Atención más cerca de ti.</h1><p>Nuestra cobertura referencial conecta los 18 departamentos de Honduras.</p><div class="network-hero__stats"><strong>{{ clinics.length }}</strong><span>puntos de cobertura referencial</span></div></div></section>
        <section class="section network-section">
            <div class="container network-layout">
                <div class="network-map-panel" data-reveal>
                    <div class="map-status"><span><i></i> Mapa geográfico local</span><b>Honduras</b></div>
                    <div class="network-map-shell">
                        <div class="network-map-fallback"><img src="/images/maps/honduras-fallback.svg" width="900" height="540" alt="Mapa geográfico local de Honduras con 18 puntos departamentales"><button v-for="clinic in clinics" :key="clinic.id" :style="pointStyle(clinic)" :class="{ selected: selectedClinic === clinic.id }" type="button" :title="`${clinic.department}: ${clinic.name}`" :aria-label="`${clinic.department}, ${clinic.name}`" :aria-pressed="selectedClinic === clinic.id" @click="focusClinic(clinic)"></button></div>
                    </div>
                    <p class="map-selection" aria-live="polite">{{ selected ? `${selected.department} · ${selected.name}` : 'Seleccione un marcador para consultar el departamento y su ciudad referencial.' }}</p>
                    <p class="map-disclaimer">Ubicaciones referenciales de cobertura. No representan una dirección de calle. Atención 24/7.</p>
                </div>
                <div data-reveal><p class="eyebrow">Directorio de cobertura</p><h2>Encuentra tu ubicación</h2><p class="network-copy">Busca por departamento y selecciona un punto para ubicarlo en el mapa.</p><label class="network-search"><span class="sr-only">Filtrar departamento</span><input v-model="query" type="search" placeholder="Buscar departamento" autocomplete="off"></label><div class="department-list department-list--cards"><article v-for="clinic in filtered" :key="clinic.id" :class="{ 'is-selected': selectedClinic === clinic.id }"><span>{{ clinic.code }}</span><div><h3>{{ clinic.name }}</h3><p>{{ clinic.department }} · Atención 24/7</p><small>{{ clinic.address }}</small></div><button type="button" :aria-pressed="selectedClinic === clinic.id" @click="focusClinic(clinic)">Ver en mapa</button></article></div></div>
            </div>
        </section>
    </PublicLayout>
</template>
