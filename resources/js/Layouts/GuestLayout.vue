<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import BrandMark from "@/Components/BrandMark.vue";
</script>

<template>
    <div class="auth-shell auth-shell--visual">
        <div class="auth-art">
            <img
                class="auth-art__photo"
                src="/images/photography/patient-assistance-1280.webp"
                srcset="/images/photography/patient-assistance-640.webp 640w, /images/photography/patient-assistance-1280.webp 1280w"
                sizes="50vw"
                width="1280"
                height="853"
                alt="Equipo médico brindando atención profesional"
                decoding="async"
            />
            <div class="auth-art__shade"></div>
            <Link class="auth-art__brand" :href="route('public.home')"
                ><BrandMark light
            /></Link>
            <div class="auth-art__copy">
                <p class="eyebrow">Portal profesional</p>
                <h1>Gestiona salud con claridad.</h1>
                <p>
                    Pacientes, documentos verificables y operación clínica desde
                    un entorno seguro.
                </p>
                <div class="auth-art__proof">
                    <span>✓ Trazabilidad completa</span
                    ><span>✓ Revisión antes de emitir</span>
                </div>
            </div>
            <small class="auth-art__foot"
                >Conexión protegida · Sistema institucional</small
            >
        </div>
        <main class="auth-main">
            <Link class="auth-mobile-brand" :href="route('public.home')"
                ><BrandMark
            /></Link>
            <div class="auth-card"><slot /></div>
            <Link class="back-site" :href="route('public.home')"
                >← Volver al sitio público</Link
            >
        </main>
    </div>
</template>
