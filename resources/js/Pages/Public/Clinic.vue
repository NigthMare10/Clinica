<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";
import { useScrollReveal } from "@/Composables/useScrollReveal";
type Page = { title?: string; content?: string; body?: string };
defineProps<{ page: Page | null }>();
useScrollReveal();
</script>

<template>
    <PublicLayout
        ><PageMeta
            title="La clínica"
            description="Conozca Clínica Médica Santa Ana, atención disponible 24/7." />
        <section class="clinic-story-hero">
            <div class="clinic-story-hero__photo">
                <img
                    src="/images/photography/clinic-reception-1280.webp"
                    srcset="/images/photography/clinic-reception-640.webp 640w, /images/photography/clinic-reception-1280.webp 1280w"
                    sizes="100vw"
                    width="1280"
                    height="960"
                    alt="Atrio y recepción de un centro hospitalario moderno"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                />
            </div>
            <div class="clinic-story-hero__shade"></div>
            <div class="container">
                <p class="eyebrow">Clínica Médica Santa Ana</p>
                <h1>{{ page?.title || "Cuidamos la salud de Honduras" }}</h1>
                <p>
                    Atención humana, responsabilidad médica y disponibilidad
                    todos los días.
                </p>
                <div class="clinic-story-hero__metrics">
                    <div>
                        <strong>24/7</strong><span>atención continua</span>
                    </div>
                    <div>
                        <strong>18</strong><span>puntos de cobertura</span>
                    </div>
                    <div><strong>15+</strong><span>especialidades</span></div>
                </div>
            </div>
        </section>
        <section class="section">
            <div class="container story-split">
                <div class="story-split__collage" data-reveal>
                    <img
                        class="story-split__large"
                        src="/images/photography/consultation-room-1280.webp"
                        srcset="/images/photography/consultation-room-640.webp 640w, /images/photography/consultation-room-1280.webp 1280w"
                        sizes="(max-width: 1000px) 78vw, 40vw"
                        width="1280"
                        height="853"
                        alt="Consultorio médico equipado"
                        loading="lazy"
                        decoding="async"
                    /><img
                        class="story-split__small"
                        src="/images/photography/clinic-corridor-640.webp"
                        srcset="/images/photography/clinic-corridor-640.webp 640w, /images/photography/clinic-corridor-1280.webp 1280w"
                        sizes="48vw"
                        width="640"
                        height="427"
                        alt="Pasillo interior de un centro clínico"
                        loading="lazy"
                        decoding="async"
                    />
                    <div>
                        <strong>Atención 24/7</strong
                        ><span>Emergencias todos los días</span>
                    </div>
                </div>
                <article data-reveal>
                    <p class="eyebrow">Quiénes somos</p>
                    <div
                        v-if="page?.content || page?.body"
                        class="prose story-content"
                        v-html="page.content || page.body"
                    ></div>
                    <p>
                        Nuestro modelo combina atención clínica, control
                        preventivo y emisión de documentos médicos verificables
                        digitalmente.
                    </p>
                    <p>
                        <strong>Cobertura nacional:</strong><br />Presencia
                        institucional en los 18 departamentos de Honduras.
                    </p>
                    <Link
                        class="button button--primary"
                        :href="route('public.contact')"
                        >Contactar ahora →</Link
                    >
                </article>
            </div>
        </section>
        <section class="section clinic-gallery">
            <div class="container">
                <div class="directory-heading" data-reveal>
                    <div>
                        <p class="eyebrow">Espacios para cuidarle</p>
                        <h2>Una experiencia clínica cercana</h2>
                    </div>
                    <p>
                        Ambientes para una atención profesional, cómoda y
                        segura.
                    </p>
                </div>
                <div class="clinic-gallery__grid" data-reveal>
                    <img
                        src="/images/photography/clinic-reception-1280.webp"
                        srcset="/images/photography/clinic-reception-640.webp 640w, /images/photography/clinic-reception-1280.webp 1280w"
                        sizes="(max-width: 760px) 100vw, 50vw"
                        width="1280"
                        height="960"
                        alt="Recepción hospitalaria moderna"
                        loading="lazy"
                        decoding="async"
                    /><img
                        src="/images/photography/female-doctor-consultation-640.webp"
                        srcset="/images/photography/female-doctor-consultation-640.webp 640w, /images/photography/female-doctor-consultation-1280.webp 1280w"
                        sizes="(max-width: 760px) 100vw, 35vw"
                        width="640"
                        height="474"
                        alt="Doctora atendiendo a una paciente"
                        loading="lazy"
                        decoding="async"
                    /><img
                        src="/images/photography/patient-assistance-640.webp"
                        srcset="/images/photography/patient-assistance-640.webp 640w, /images/photography/patient-assistance-1280.webp 1280w"
                        sizes="(max-width: 760px) 100vw, 35vw"
                        width="640"
                        height="427"
                        alt="Asistencia clínica profesional"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            </div></section
    ></PublicLayout>
</template>
