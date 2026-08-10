<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";
import type { Specialty } from "@/types";

defineProps<{ specialty: Specialty }>();
const image = (specialty: Specialty) => specialty.image_path || '/images/photography/female-doctor-consultation-1280.webp';
const responsiveSet = (path: string) => `${path.replace('-1280.webp', '-640.webp')} 640w, ${path} 1280w`;
</script>

<template>
    <PublicLayout
        ><PageMeta
            :title="specialty.name"
            :description="
                specialty.short_description ||
                specialty.description ||
                `Atención en ${specialty.name}.`
            "
        />
        <section class="specialty-detail-hero">
            <img
                :src="image(specialty)"
                :srcset="responsiveSet(image(specialty))"
                sizes="100vw"
                width="1280"
                height="853"
                :alt="`Atención de ${specialty.name}`"
                decoding="async"
                fetchpriority="high"
            />
            <div></div>
            <div class="container">
                <Link
                    :href="route('public.specialties.index')"
                    class="back-link"
                    >← Todas las especialidades</Link
                >
                <p class="eyebrow">Especialidad médica</p>
                <h1>{{ specialty.name }}</h1>
                <p>
                    {{
                        specialty.short_description ||
                        specialty.description ||
                        `Atención integral en ${specialty.name}.`
                    }}
                </p>
            </div>
        </section>
        <section class="section">
            <div class="container detail-layout">
                <article>
                    <p class="eyebrow">Cuidado especializado</p>
                    <h2>Sobre esta especialidad</h2>
                    <p class="prose">
                        {{
                            specialty.description ||
                            `Evaluación, orientación y seguimiento profesional en ${specialty.name}.`
                        }}
                    </p>
                    <h3>Motivos frecuentes de consulta</h3>
                    <div class="tag-list">
                        <span
                            v-for="reason in specialty.common_reasons"
                            :key="reason"
                            >{{ reason }}</span
                        >
                    </div>
                    <div class="notice">
                        <b>Información responsable</b>
                        <p>
                            Este contenido es informativo y no sustituye una
                            valoración médica.
                        </p>
                    </div>
                </article>
                <aside>
                    <div class="specialty-contact-card">
                        <span>24/7</span>
                        <h3>Atención disponible</h3>
                        <p>
                            Te orientamos para encontrar la atención adecuada
                            para esta especialidad.
                        </p>
                        <Link
                            class="button button--primary"
                            :href="route('public.contact')"
                            >Contactar la clínica</Link
                        >
                    </div>
                </aside>
            </div>
        </section></PublicLayout
    >
</template>
