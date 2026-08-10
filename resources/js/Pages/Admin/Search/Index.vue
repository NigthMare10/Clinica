<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import type { Clinic, MedicalDocument, Patient } from '@/types';

defineProps<{ query: string; results: { patients: Patient[]; documents: MedicalDocument[]; clinics: Clinic[] } }>();
</script>

<template>
    <AdminLayout title="Busqueda global" eyebrow="Red clinica">
        <PageMeta title="Busqueda global" noindex />
        <section class="admin-content"><div class="content-toolbar"><div><p class="kicker">Resultados autorizados</p><h2 v-if="query">Coincidencias para “{{ query }}”</h2><h2 v-else>Escriba al menos dos caracteres</h2></div><span class="privacy-chip">Sin contenido OCR</span></div><div v-if="query.length >= 2" class="search-results-grid"><section class="panel result-group"><div class="panel-head"><h2>Pacientes</h2><span>{{ results.patients.length }}</span></div><Link v-for="patient in results.patients" :key="patient.id" :href="route('admin.patients.show', patient.id)"><span>{{ patient.first_name }} {{ patient.last_name }}</span><b>Expediente →</b></Link><EmptyState v-if="!results.patients.length" title="Sin pacientes" /></section><section class="panel result-group"><div class="panel-head"><h2>Documentos</h2><span>{{ results.documents.length }}</span></div><Link v-for="document in results.documents" :key="document.id" :href="route('admin.documents.review', document.id)"><span>{{ document.public_code || document.original_filename }}</span><StatusBadge :status="document.status" /></Link><EmptyState v-if="!results.documents.length" title="Sin documentos" /></section><section class="panel result-group"><div class="panel-head"><h2>Clínicas</h2><span>{{ results.clinics.length }}</span></div><div v-for="clinic in results.clinics" :key="clinic.id" class="timeline-item"><i></i><div><h3>{{ clinic.department }}</h3><p>{{ clinic.status }}</p></div></div><EmptyState v-if="!results.clinics.length" title="Sin clínicas" /></section></div></section>
    </AdminLayout>
</template>
