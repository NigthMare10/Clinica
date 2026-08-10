<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import { useScrollReveal } from '@/Composables/useScrollReveal';
import type { Clinic, Specialty } from '@/types';
import 'leaflet/dist/leaflet.css';

const props = defineProps<{ specialties: Specialty[]; clinics: Clinic[] }>();
const miniMap = ref<HTMLElement | null>(null);
const miniMapReady = ref(false);
let disposeMap: (() => void) | undefined;
let mapObserver: IntersectionObserver | undefined;

const specialtyImage = (specialty: Specialty) => specialty.image_path || '/images/photography/female-doctor-consultation-1280.webp';
const responsiveSet = (path: string) => `${path.replace('-1280.webp', '-640.webp')} 640w, ${path} 1280w`;
const imageFallback = (event: Event) => { (event.target as HTMLImageElement).src = '/images/photography/female-doctor-consultation-1280.webp'; };

useScrollReveal();
const initializeMap = async () => {
    if (!miniMap.value) return;
    try {
        const leaflet = await import('leaflet');
        const map = leaflet.map(miniMap.value, { scrollWheelZoom: false, zoomControl: false }).setView([14.65, -86.55], 6.7);
        const tiles = leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' });
        tiles.on('tileload', () => { miniMapReady.value = true; });
        tiles.addTo(map);
        props.clinics.forEach((clinic) => {
            if (clinic.latitude !== null && clinic.longitude !== null) {
                leaflet.circleMarker([clinic.latitude, clinic.longitude], { radius: 6, color: '#ffffff', weight: 2, fillColor: '#16c9e8', fillOpacity: 1 }).addTo(map);
            }
        });
        window.setTimeout(() => map.invalidateSize(), 250);
        disposeMap = () => map.remove();
    } catch { miniMapReady.value = false; }
};
onMounted(() => {
    if (!miniMap.value) return;
    mapObserver = new IntersectionObserver(([entry]) => {
        if (!entry?.isIntersecting) return;
        mapObserver?.disconnect();
        void initializeMap();
    }, { rootMargin: '300px' });
    mapObserver.observe(miniMap.value);
});
onUnmounted(() => { mapObserver?.disconnect(); disposeMap?.(); });
</script>

<template>
    <PublicLayout>
        <PageMeta title="Tu salud en manos confiables" description="14 años brindando atención médica con cercanía, responsabilidad y cobertura nacional." canonical="/" />

        <section class="premium-hero">
            <div class="premium-hero__glow"></div>
            <div class="container premium-hero__grid">
                <div class="premium-hero__copy" data-reveal>
                    <p class="eyebrow"><span></span> Salud para toda Honduras</p>
                    <h1>Tu salud en <em>manos confiables</em></h1>
                    <p class="premium-hero__lead">14 años brindando atención médica con cercanía, responsabilidad y cobertura nacional.</p>
                    <div class="premium-hero__actions">
                        <Link class="button button--primary" :href="route('public.specialties.index')">Ver especialidades <span>→</span></Link>
                        <Link class="button button--glass" :href="route('public.clinics.index')">Ver clínicas</Link>
                        <Link class="premium-hero__verify" :href="route('public.verify.lookup')">Verificar documento</Link>
                    </div>
                    <div class="care-proof">
                        <div class="care-proof__avatars"><span>24/7</span></div>
                        <div><strong>Atención disponible siempre</strong><span>Emergencias 24 horas, todos los días</span></div>
                    </div>
                </div>
                <div class="premium-hero__visual" data-reveal data-parallax>
                    <img src="/images/photography/female-doctor-consultation-1280.webp" srcset="/images/photography/female-doctor-consultation-640.webp 640w, /images/photography/female-doctor-consultation-1280.webp 1280w" sizes="(max-width: 760px) 100vw, 50vw" width="1280" height="947" alt="Doctora conversando con una paciente durante una consulta" decoding="async" fetchpriority="high">
                    <div class="floating-card floating-card--top"><span class="pulse-dot"></span><div><small>Red activa</small><strong>18 clínicas en Honduras</strong></div></div>
                    <div class="floating-card floating-card--bottom"><b>14</b><div><strong>Años de experiencia</strong><small>Cuidado médico cercano</small></div></div>
                    <div class="hero-seal"><span>✓</span><small>Documentos<br>verificables</small></div>
                </div>
                <div class="specialty-cloud" data-reveal><span>También atendemos</span><Link v-for="item in specialties" :key="`quick-${item.id}`" :href="route('public.specialties.show', item.slug)">{{ item.name }}</Link></div>
            </div>
        </section>

        <section class="impact-strip">
            <div class="container impact-grid" data-reveal>
                <article><strong data-count="14">14</strong><span>Años de experiencia</span></article>
                <article><strong data-count="18">18</strong><span>Clínicas</span></article>
                <article><strong>100%</strong><span>Cobertura nacional</span></article>
                <article><strong>15+</strong><span>Áreas de atención</span></article>
            </div>
        </section>

        <section class="section specialty-showcase">
            <div class="container">
                <div class="section-heading" data-reveal><div><p class="eyebrow">Cuidado integral</p><h2>Especialidades cerca de ti</h2><p class="section-intro">Atención preventiva y especializada para cada etapa de la vida.</p></div><Link class="text-link" :href="route('public.specialties.index')">Explorar todas →</Link></div>
                <div class="premium-specialty-grid">
                    <Link v-for="item in specialties.slice(0, 6)" :key="item.id" class="premium-specialty-card" :href="route('public.specialties.show', item.slug)" data-reveal>
                        <img :src="specialtyImage(item)" :srcset="responsiveSet(specialtyImage(item))" sizes="(max-width: 760px) 100vw, 33vw" width="1280" height="853" :alt="`Atención profesional en ${item.name}`" loading="lazy" decoding="async" @error="imageFallback">
                        <div class="premium-specialty-card__shade"></div>
                        <div class="premium-specialty-card__body"><span>Atención especializada</span><h3>{{ item.name }}</h3><p>{{ item.short_description }}</p><b>Ver especialidad <i>→</i></b></div>
                    </Link>
                </div>
            </div>
        </section>

        <section class="section human-care">
            <div class="container human-care__grid">
                <div class="human-care__collage" data-reveal data-parallax>
                    <img class="human-care__main" src="/images/photography/consultation-room-1280.webp" srcset="/images/photography/consultation-room-640.webp 640w, /images/photography/consultation-room-1280.webp 1280w" sizes="(max-width: 1000px) 78vw, 40vw" width="1280" height="853" alt="Consultorio médico equipado para atención clínica" loading="lazy" decoding="async">
                    <img class="human-care__small" src="/images/photography/patient-assistance-640.webp" srcset="/images/photography/patient-assistance-640.webp 640w, /images/photography/patient-assistance-1280.webp 1280w" sizes="48vw" width="640" height="427" alt="Equipo clínico brindando asistencia a una paciente" loading="lazy" decoding="async">
                    <div class="human-care__badge"><strong>+ Humana</strong><span>Escuchamos antes de atender</span></div>
                </div>
                <div data-reveal><p class="eyebrow">Quiénes somos</p><h2>Medicina con experiencia, cercanía y propósito.</h2><p class="lead">Clínica Médica Santa Ana es una red de atención médica con 14 años de experiencia, enfocada en brindar servicios de salud accesibles, confiables y humanos para pacientes en todo el país.</p><p>A lo largo de nuestro crecimiento hemos consolidado presencia estratégica en los 18 departamentos de Honduras, acercando atención médica integral a más familias.</p><div class="value-pills"><span>Atención humana</span><span>Prevención</span><span>Confianza</span><span>Solidez institucional</span></div><Link class="button button--outline" :href="route('public.clinic')">Conocer nuestra historia</Link></div>
            </div>
        </section>

        <section class="section network-preview">
            <div class="container network-preview__grid">
                <div data-reveal><p class="eyebrow">Red Santa Ana</p><h2>Atención con cobertura nacional.</h2><p class="lead">18 clínicas conectadas para acercar servicios médicos a cada departamento de Honduras.</p><ul class="network-facts"><li><span>✓</span> Presencia en los 18 departamentos</li><li><span>✓</span> Directorio y ubicaciones referenciales</li><li><span>✓</span> Atención coordinada y confiable</li></ul><Link class="button button--primary" :href="route('public.clinics.index')">Explorar mapa completo →</Link></div>
                <div class="mini-map-shell" data-reveal><img class="mini-map-fallback" src="/images/maps/honduras-fallback.svg" width="900" height="540" alt="Mapa geográfico de Honduras con cobertura en sus 18 departamentos" loading="lazy" decoding="async"><div ref="miniMap" :class="['mini-map',{ready:miniMapReady}]" aria-label="Mapa interactivo de cobertura en Honduras"></div><div class="mini-map__label"><strong>{{ clinics.length }} ubicaciones</strong><span>Atención 24/7</span></div></div>
            </div>
        </section>

        <section class="section document-trust">
            <div class="container document-trust__grid">
                <div class="document-mockup" data-reveal>
                    <div class="document-sheet"><div class="document-sheet__brand"><span>+</span><b>Clínica Médica Santa Ana</b></div><small>CONSTANCIA MÉDICA</small><i></i><i></i><i class="short"></i><div class="mock-qr"><span></span><span></span><span></span><span></span></div><strong>SA-2026-01842</strong></div>
                    <div class="verified-chip"><span>✓</span><div><strong>Documento válido</strong><small>Integridad comprobada</small></div></div>
                </div>
                <div data-reveal><p class="eyebrow">Confianza digital</p><h2>Documentos médicos que se pueden comprobar.</h2><p class="lead">Cada documento emitido desde el sistema integra un código único y QR para consultar su estado institucional sin exponer información clínica sensible.</p><div class="document-steps"><div><b>01</b><span><strong>Escanea el QR</strong><small>Desde cualquier dispositivo compatible.</small></span></div><div><b>02</b><span><strong>Consulta su estado</strong><small>Válido, anulado o reemplazado.</small></span></div><div><b>03</b><span><strong>Confirma con confianza</strong><small>Validación institucional en segundos.</small></span></div></div><Link class="button button--light" :href="route('public.verify.lookup')">Verificar un documento →</Link></div>
            </div>
        </section>
    </PublicLayout>
</template>
