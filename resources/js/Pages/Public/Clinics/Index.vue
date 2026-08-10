<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import type { Clinic } from '@/types';
import 'leaflet/dist/leaflet.css';

const props = defineProps<{ clinics: Clinic[] }>();
const query = ref('');
const mapElement = ref<HTMLElement | null>(null);
const mapReady = ref(false);
const mapFailed = ref(false);
const selectedClinic = ref<string | null>(null);
const filtered = computed(() => props.clinics.filter((clinic) => clinic.department.toLocaleLowerCase('es').includes(query.value.toLocaleLowerCase('es'))));
let mapInstance: import('leaflet').Map | undefined;
const markers = new Map<string, import('leaflet').CircleMarker>();
let disposeMap: (() => void) | undefined;

useScrollReveal();
onMounted(async () => {
    if (!mapElement.value) return;
    try {
        const leaflet = await import('leaflet');
        const map = leaflet.map(mapElement.value, { scrollWheelZoom: false, zoomSnap: 0.25, minZoom: 6.5 }).setView([14.75, -86.55], 7.25);
        mapInstance = map;
        let errors = 0;
        const tiles = leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 18 });
        tiles.on('tileload', () => { mapReady.value = true; mapFailed.value = false; });
        tiles.on('tileerror', () => { errors += 1; if (errors >= 4 && !mapReady.value) mapFailed.value = true; });
        tiles.addTo(map);
        props.clinics.filter((clinic) => clinic.latitude !== null && clinic.longitude !== null && clinic.is_public && clinic.status === 'ACTIVE').forEach((clinic) => {
            const popup = document.createElement('div');
            const name = document.createElement('strong');
            const department = document.createElement('span');
            name.textContent = clinic.name;
            department.textContent = clinic.department;
            popup.append(name, document.createElement('br'), department);
            const marker = leaflet.circleMarker([clinic.latitude as number, clinic.longitude as number], { radius: 9, weight: 3, color: '#ffffff', fillColor: '#0b789b', fillOpacity: 1 }).addTo(map).bindPopup(popup);
            marker.on('click', () => { selectedClinic.value = clinic.id; });
            markers.set(clinic.id, marker);
        });
        const bounds = props.clinics.filter((clinic) => clinic.latitude !== null && clinic.longitude !== null).map((clinic) => [clinic.latitude as number, clinic.longitude as number] as [number, number]);
        if (bounds.length) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 7.25 });
        const resize = new ResizeObserver(() => map.invalidateSize({ pan: false }));
        resize.observe(mapElement.value);
        const timeout = window.setTimeout(() => { map.invalidateSize(); if (!mapReady.value) mapFailed.value = true; }, 6000);
        disposeMap = () => { window.clearTimeout(timeout); resize.disconnect(); map.remove(); };
    } catch { mapFailed.value = true; }
});
onUnmounted(() => disposeMap?.());
const focusClinic = (clinic: Clinic) => {
    if (clinic.latitude === null || clinic.longitude === null) return;
    selectedClinic.value = clinic.id;
    if (mapReady.value) {
        mapInstance?.flyTo([clinic.latitude, clinic.longitude], 11, { duration: 1.2 });
        markers.get(clinic.id)?.openPopup();
    }
    mapElement.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};
const pointStyle = (clinic: Clinic) => ({
    left: `${((70 + ((((clinic.longitude as number) + 89.5) / 6.5) * 760)) / 900) * 100}%`,
    top: `${((60 + (((16.2 - (clinic.latitude as number)) / 3.5) * 390)) / 540) * 100}%`,
});
</script>

<template>
    <PublicLayout>
        <PageMeta title="Red de clínicas" description="18 ubicaciones de cobertura de Clínica Médica Santa Ana en Honduras." canonical="/clinicas" />
        <section class="network-hero network-hero--visual"><div class="network-hero__image"><img src="/images/photography/clinic-reception-1280.webp" srcset="/images/photography/clinic-reception-640.webp 640w, /images/photography/clinic-reception-1280.webp 1280w" sizes="100vw" width="1280" height="960" alt="Recepción de un centro hospitalario moderno" decoding="async" fetchpriority="high"></div><div class="container" data-reveal><p class="eyebrow">Cobertura nacional</p><h1>Atención más cerca de ti.</h1><p>Nuestra cobertura referencial conecta los 18 departamentos de Honduras.</p><div class="network-hero__stats"><strong>{{ clinics.length }}</strong><span>puntos de cobertura referencial</span></div></div></section>
        <section class="section network-section"><div class="container network-layout"><div class="network-map-panel" data-reveal><div class="map-status" aria-live="polite"><span><i></i> {{ mapReady && !mapFailed ? 'Mapa interactivo' : 'Mapa vectorial local' }}</span><b>Honduras</b></div><div class="network-map-shell"><div class="network-map-fallback"><img src="/images/maps/honduras-fallback.svg" width="900" height="540" alt="Mapa geográfico local de Honduras con 18 puntos departamentales"><button v-for="clinic in clinics" :key="`point-${clinic.id}`" :style="pointStyle(clinic)" :class="{selected:selectedClinic===clinic.id}" type="button" :title="`${clinic.name}, ${clinic.department}`" :aria-label="`Mostrar ${clinic.name} en ${clinic.department}`" :aria-pressed="selectedClinic===clinic.id" @click="focusClinic(clinic)"></button></div><div ref="mapElement" :class="['network-map',{ready:mapReady&&!mapFailed}]" role="region" aria-label="Mapa interactivo de cobertura en Honduras"></div></div><p v-if="mapFailed" class="map-offline">OpenStreetMap no está disponible. Se muestra la cobertura vectorial local.</p><p class="map-disclaimer">Ubicaciones referenciales de cobertura. No representan una dirección de calle. Atención 24/7.</p></div><div data-reveal><p class="eyebrow">Directorio de cobertura</p><h2>Encuentra tu ubicación</h2><p class="network-copy">Busca por departamento y selecciona un punto para ubicarlo en el mapa.</p><label class="network-search"><span class="sr-only">Filtrar departamento</span><input v-model="query" type="search" placeholder="Buscar departamento" autocomplete="off"></label><div class="department-list department-list--cards"><article v-for="clinic in filtered" :key="clinic.id" :class="{'is-selected':selectedClinic===clinic.id}"><span>{{ clinic.code }}</span><div><h3>{{ clinic.name }}</h3><p>{{ clinic.department }} · Atención 24/7</p><small>{{ clinic.address }}</small></div><button type="button" :aria-pressed="selectedClinic===clinic.id" @click="focusClinic(clinic)">Ver en mapa →</button></article></div></div></div></section>
    </PublicLayout>
</template>
