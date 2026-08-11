<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { hondurasDate } from '@/Composables/hondurasDate';
import type { MedicalDocument, Paginated, Patient } from '@/types';

const props = defineProps<{ patient: Patient; documents: Paginated<MedicalDocument> }>();
const initials = `${props.patient.first_name.charAt(0)}${props.patient.last_name.charAt(0)}`.toUpperCase();
const date = (value?: string | null) => value ? hondurasDate(value) : 'No registrada';
</script>

<template>
    <AdminLayout :title="`${patient.first_name} ${patient.last_name}`" eyebrow="Expediente documental">
        <PageMeta title="Expediente de paciente" noindex />
        <section class="admin-content">
            <div class="content-toolbar"><div><p class="kicker">Perfil autorizado</p><h2>Documentos y trazabilidad</h2></div><div class="toolbar-actions"><span class="privacy-chip">Datos protegidos</span><Link class="button button--admin" :href="route('admin.documents.create', { patient_id: patient.id })">+ Agregar documento</Link></div></div>
            <div class="patient-profile-grid">
                <aside class="panel patient-identity"><div class="patient-avatar" aria-hidden="true">{{ initials }}</div><p class="kicker">Paciente</p><h2>{{ patient.first_name }} {{ patient.last_name }}</h2><dl class="patient-details"><div><dt>Identidad</dt><dd>{{ [patient.document_type, patient.document_number].filter(Boolean).join(' ') || 'No registrada' }}</dd></div><div><dt>Nacimiento</dt><dd>{{ date(patient.birth_date) }}</dd></div><div><dt>Contacto</dt><dd>{{ patient.email || patient.phone || 'No registrado' }}</dd></div><div><dt>Documentos</dt><dd>{{ documents.total }} en el expediente</dd></div></dl><Link class="button button--outline button--full" :href="route('admin.patients.edit', patient.id)">Editar datos</Link></aside>
                <div class="panel"><div class="panel-head"><div><p class="kicker">Linea de tiempo</p><h2>Actividad documental</h2></div></div><div v-if="documents.data.length" class="timeline-list"><article v-for="document in documents.data" :key="document.id" class="timeline-item"><i aria-hidden="true"></i><div><h3>{{ document.certificate_kind === 'INCAPACIDAD' ? 'Incapacidad médica' : 'Constancia médica' }}</h3><p>{{ document.doctor ? `${document.doctor.first_name} ${document.doctor.last_name}` : 'Profesional institucional' }} · {{ document.clinic?.department || 'Clinica no asignada' }}</p><time :datetime="document.created_at">{{ date(document.created_at) }} · {{ document.verification_logs_count || 0 }} verificaciones</time></div><div><StatusBadge :status="document.status"/><Link class="row-action" :href="route('admin.documents.review', document.id)">Abrir</Link></div></article></div><EmptyState v-else title="Sin documentos en el expediente"/><Pagination :links="documents.links"/></div>
            </div>
        </section>
    </AdminLayout>
</template>
