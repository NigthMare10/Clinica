<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { hondurasDate } from '@/Composables/hondurasDate';
import type { Paginated, Patient } from '@/types';

type TimelineDocument = {
    id: string;
    code: string | null;
    type: string;
    certificate_kind: string | null;
    status: string;
    consultation_date: string | null;
    consultation_time: string | null;
    issued_at: string | null;
    verification_logs_count: number;
    doctor: { first_name: string; last_name: string } | null;
    clinic: { department: string } | null;
    invoice: {
        state: 'none' | 'active' | 'voided';
        historical_count: number;
        linked: { id: string; ncf: string | null; status: string; pdf_available: boolean; is_active: boolean } | null;
    };
};

const props = defineProps<{ patient: Patient; documents: Paginated<TimelineDocument> }>();
const initials = `${props.patient.first_name.charAt(0)}${props.patient.last_name.charAt(0)}`.toUpperCase();
const date = (value?: string | null) => value ? hondurasDate(value) : 'No registrada';
const consultationTime = (value?: string | null) => {
    if (!value) return 'Hora no registrada';
    const [hours, minutes] = value.split(':').map(Number);
    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours < 12 ? 'a. m.' : 'p. m.'}`;
};
const documentType = (document: TimelineDocument) => {
    if (document.certificate_kind === 'INCAPACIDAD') return 'Incapacidad médica';
    if (document.certificate_kind === 'CONSTANCIA') return 'Constancia médica';
    return ({ MEDICAL_CERTIFICATE: 'Certificado médico', MEDICAL_REPORT: 'Informe médico', PRESCRIPTION: 'Receta médica', LAB_RESULT: 'Resultado de laboratorio', REFERRAL: 'Referencia médica', OTHER: 'Documento médico' } as Record<string, string>)[document.type] || document.type;
};
const invoiceLabel = (state: TimelineDocument['invoice']['state']) => ({ none: 'Factura: No', active: 'Factura: Sí', voided: 'Factura: Anulada' })[state];
const historicalInvoices = (count: number) => `${count} factura${count === 1 ? '' : 's'} histórica${count === 1 ? '' : 's'} no duplica${count === 1 ? '' : 'n'} la línea de tiempo.`;
</script>

<template>
    <AdminLayout :title="`${patient.first_name} ${patient.last_name}`" eyebrow="Expediente documental">
        <PageMeta title="Expediente de paciente" noindex />
        <section class="admin-content">
            <div class="content-toolbar"><div><p class="kicker">Perfil autorizado</p><h2>Documentos y trazabilidad</h2></div><div class="toolbar-actions"><span class="privacy-chip">Datos protegidos</span><Link class="button button--admin" :href="route('admin.documents.create', { patient_id: patient.id })">+ Agregar documento</Link></div></div>
            <div class="patient-profile-grid">
                <aside class="panel patient-identity"><div class="patient-avatar" aria-hidden="true">{{ initials }}</div><p class="kicker">Paciente</p><h2>{{ patient.first_name }} {{ patient.last_name }}</h2><dl class="patient-details"><div><dt>Identidad</dt><dd>{{ [patient.document_type, patient.document_number].filter(Boolean).join(' ') || 'No registrada' }}</dd></div><div><dt>Nacimiento</dt><dd>{{ date(patient.birth_date) }}</dd></div><div><dt>Contacto</dt><dd>{{ patient.email || patient.phone || 'No registrado' }}</dd></div><div><dt>Documentos</dt><dd>{{ documents.total }} en el expediente</dd></div></dl><Link class="button button--outline button--full" :href="route('admin.patients.edit', patient.id)">Editar datos</Link></aside>
                <div class="panel"><div class="panel-head"><div><p class="kicker">Línea de tiempo</p><h2>Actividad documental</h2><p>Una entrada por documento médico vigente; las facturas vinculadas permanecen en su historial.</p></div></div><div v-if="documents.data.length" class="timeline-list"><article v-for="document in documents.data" :key="document.id" class="timeline-item"><i aria-hidden="true"></i><div><h3>{{ documentType(document) }}</h3><p><strong>{{ document.code || 'Código pendiente' }}</strong> · {{ document.doctor ? `${document.doctor.first_name} ${document.doctor.last_name}` : 'Profesional institucional' }} · {{ document.clinic?.department || 'Clínica no asignada' }}</p><time :datetime="document.consultation_date || undefined">Consulta: {{ date(document.consultation_date) }} · {{ consultationTime(document.consultation_time) }} · {{ document.verification_logs_count || 0 }} verificaciones</time><p v-if="document.invoice.linked">{{ document.invoice.linked.is_active ? 'Factura actual:' : 'Última factura anulada:' }} {{ document.invoice.linked.ncf || 'Borrador sin NCF' }}</p><time v-if="document.invoice.historical_count">{{ historicalInvoices(document.invoice.historical_count) }}</time></div><div><StatusBadge :status="document.status"/><span class="privacy-chip">{{ invoiceLabel(document.invoice.state) }}</span><Link class="row-action" :href="route('admin.documents.review', document.id)">Ver documento</Link><Link v-if="document.invoice.linked" class="row-action" :href="route('admin.invoices.show', document.invoice.linked.id)">Ver factura</Link><a v-if="document.issued_at" class="row-action timeline-pdf-link" :href="route('admin.documents.download', { document: document.id, version: 'issued' })" target="_blank" rel="noopener">PDF documento</a><a v-if="document.invoice.linked?.pdf_available" class="row-action timeline-pdf-link" :href="route('admin.invoices.download', document.invoice.linked.id)" target="_blank" rel="noopener">PDF factura</a></div></article></div><EmptyState v-else title="Sin documentos en el expediente"/><Pagination :links="documents.links"/></div>
            </div>
        </section>
    </AdminLayout>
</template>

<style scoped>
@media (max-width: 767px) {
    .timeline-pdf-link { display: none; }
}
</style>
