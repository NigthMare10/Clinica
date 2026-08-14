<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import BrandMark from '@/Components/BrandMark.vue';
import { useInstitution } from '@/Composables/useInstitution';

const open = ref(false);
const institution = useInstitution();
const navigation = [
    ['Inicio', 'public.home', 'public.home'],
    ['Especialidades', 'public.specialties.*', 'public.specialties.index'],
    ['La clinica', 'public.clinic', 'public.clinic'],
    ['Clinicas', 'public.clinics.*', 'public.clinics.index'],
    ['Contacto', 'public.contact', 'public.contact'],
] as const;
const active = (pattern: string) => Boolean(route().current(pattern));
const close = () => { open.value = false; };
const onKeydown = (event: KeyboardEvent) => { if (event.key === 'Escape') close(); };
const schema = JSON.stringify({
    '@context': 'https://schema.org',
    '@type': 'MedicalClinic',
    name: institution.short_name,
    telephone: institution.phone,
    address: institution.address,
    openingHours: 'Mo-Su 00:00-23:59',
    url: typeof window !== 'undefined' ? window.location.origin : '',
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="site-shell">
        <a class="skip-link" href="#contenido">Saltar al contenido</a>
        <header class="public-header">
            <div class="container nav-wrap">
                <Link :href="route('public.home')" aria-label="Clinica Medica Santa Ana, inicio" @click="close">
                    <BrandMark />
                </Link>
                <button class="nav-toggle" type="button" :aria-expanded="open" aria-controls="public-nav" aria-label="Abrir menú" @click="open = !open">
                    <span></span><span></span><span></span><b class="sr-only">Menú</b>
                </button>
                <nav id="public-nav" :class="{ open }" aria-label="Navegación principal">
                    <Link v-for="item in navigation" :key="item[0]" :href="route(item[2])" :class="{ active: active(item[1]) }" @click="close">{{ item[0] }}</Link>
                    <Link class="nav-verify" :href="route('public.verify.lookup')" @click="close">Verificar documento</Link>
                </nav>
            </div>
        </header>
        <main id="contenido"><slot /></main>
        <footer class="public-footer">
            <div class="container footer-grid">
                <div><BrandMark light /><p>Atención médica 24/7 con rigor institucional, trato humano y procesos verificables.</p><p>Presencia nacional en los 18 departamentos de Honduras.</p></div>
                <div><strong>Institucional</strong><Link :href="route('public.specialties.index')">Especialidades</Link><Link :href="route('public.clinic')">La clínica</Link><Link :href="route('public.clinics.index')">Cobertura</Link><Link :href="route('public.contact')">Contacto</Link></div>
                <div><strong>Accesos</strong><Link :href="route('public.verify.lookup')">Verificar documento</Link><Link :href="route('login')">Acceso profesional</Link></div>
            </div>
            <div class="container footer-base"><span>© {{ new Date().getFullYear() }} {{ institution.short_name }}</span><span>{{ institution.hours }} · 7 DÍAS DE LA SEMANA</span></div>
        </footer>
        <component :is="'script'" type="application/ld+json">{{ schema }}</component>
    </div>
</template>
