<script setup lang="ts">
import { computed, ref } from 'vue';
import axios from 'axios';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';

type Clinic = { id: string; name: string; department: string };
type Provider = { name: string; credential_type: string; credential_number: string };
type Fields = {
    patient_name: string | null; age: number | null; identity: string | null;
    consultation_date: string | null; consultation_time: string | null;
    symptoms: string | null; diagnosis: string | null; recommendations: string | null;
    leave_days: number | null; leave_start_date: string | null; leave_end_date: string | null; body: string;
};
type Analysis = {
    fields: Fields; checks: Record<string, boolean>; score: number;
    conflicts: Array<{ field: string; message: string }>; requires_review: boolean;
    patient: { id: string; name: string; document_number: string } | null;
};
type QuickBilling = { service: string; quantity: string; unit_price: string; tax_category: string; payment_method: string };
const props = defineProps<{ kind: 'constancia' | 'incapacidad'; provider: Provider; clinic: Clinic | null; canIssue: boolean; quickBilling: QuickBilling | null }>();
const analysis = ref<Analysis | null>(null);
const analyzing = ref(false);
const analysisError = ref('');
const mobileTab = ref<'data' | 'preview'>('data');
const form = useForm({
    patient_id: '', patient_name: '', identity: '', age_at_consultation: null as number | null,
    clinic_id: props.clinic?.id || '', consultation_date: '', consultation_time: '', symptoms: '',
    medical_reason: '', diagnosis: '', recommendations: '', leave_start_date: '', leave_end_date: '',
    leave_days: null as number | null, free_text: '', create_patient: false, confirm: false,
    intent: 'draft' as 'draft' | 'issue',
    quick_invoice: false,
});
const title = computed(() => props.kind === 'incapacidad' ? 'Nueva incapacidad' : 'Nueva constancia');
const ready = computed(() => Boolean(form.patient_name && form.identity && form.consultation_date && form.diagnosis
    && (props.kind === 'constancia' || (form.leave_start_date && form.leave_end_date && form.leave_days))));
const previewParagraphs = computed(() => form.free_text.replaceAll('**', '').replaceAll('__', '').split(/\n\s*\n/).filter(Boolean));
const date = (value: string) => value ? new Intl.DateTimeFormat('es-HN', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`)) : 'No detectada';
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
const analyze = async () => {
    analysisError.value = '';
    analyzing.value = true;
    try {
        const response = await axios.post<Analysis>(route('admin.documents.generate.analyze', props.kind), { text: form.free_text });
        analysis.value = response.data;
        const fields = response.data.fields;
        form.patient_id = response.data.patient?.id || '';
        form.patient_name = fields.patient_name || response.data.patient?.name || '';
        form.identity = fields.identity || '';
        form.age_at_consultation = fields.age;
        form.consultation_date = fields.consultation_date || '';
        form.consultation_time = fields.consultation_time || '';
        form.symptoms = fields.symptoms || '';
        form.medical_reason = fields.body;
        form.diagnosis = fields.diagnosis || '';
        form.recommendations = fields.recommendations || '';
        form.leave_start_date = fields.leave_start_date || '';
        form.leave_end_date = fields.leave_end_date || '';
        form.leave_days = fields.leave_days;
        form.create_patient = !response.data.patient;
        mobileTab.value = 'data';
    } catch {
        analysisError.value = 'No fue posible analizar el texto. Revise el contenido e inténtelo nuevamente.';
    } finally {
        analyzing.value = false;
    }
};
const submit = (intent: 'draft' | 'issue') => {
    if (intent === 'issue' && !window.confirm('Confirma que los datos son correctos y autoriza firmar y emitir el documento definitivo?')) return;
    form.intent = intent;
    form.confirm = true;
    form.post(route('admin.documents.generate.store', props.kind), { preserveScroll: true });
};
</script>

<template>
    <AdminLayout :title="title" eyebrow="Operación documental">
        <PageMeta :title="title" noindex />
        <section class="admin-content quick-certificate">
            <div class="generator-heading">
                <div><Link class="back-admin" :href="route('admin.documents.index')">← Documentos</Link><p class="kicker">Creación en segundos</p><h2>{{ title }}</h2><p>Pegue la redacción clínica. El sistema solo extrae lo escrito; nunca inventa un diagnóstico.</p></div>
                <div class="fixed-provider"><small>Profesional emisor</small><strong>{{ provider.name }}</strong><span>{{ provider.credential_type }} {{ provider.credential_number }}</span></div>
            </div>
            <div class="mobile-preview-tabs"><button :class="{ active: mobileTab === 'data' }" @click="mobileTab = 'data'">Datos</button><button :class="{ active: mobileTab === 'preview' }" @click="mobileTab = 'preview'">Vista previa</button></div>
            <div class="quick-certificate__grid">
                <div :class="['quick-certificate__data', { 'mobile-hidden': mobileTab !== 'data' }]">
                    <div class="panel text-analysis">
                        <label for="medical-text">Pegue aquí el contenido de la {{ kind }}</label>
                        <textarea id="medical-text" v-model="form.free_text" rows="15" placeholder="Por medio de la presente se hace constar que..."></textarea>
                        <span v-if="form.errors.free_text" class="field-error">{{ form.errors.free_text }}</span>
                        <button class="button button--admin analyze-button" type="button" :disabled="!form.free_text || analyzing" @click="analyze">{{ analyzing ? 'Analizando…' : 'ANALIZAR TEXTO' }}</button>
                        <p v-if="analysisError" class="field-error">{{ analysisError }}</p>
                    </div>
                    <div v-if="analysis" class="panel extraction-summary">
                        <div class="analysis-title"><div><p class="kicker">Análisis del documento</p><h3>{{ Math.round(analysis.score * 100) }}% detectado</h3></div><span :class="{ warning: analysis.requires_review }">{{ analysis.requires_review ? 'Revisar campos' : 'Correcto ✓' }}</span></div>
                        <div class="extraction-fields">
                            <label><span :class="{ missing: !form.patient_name }">{{ form.patient_name ? '✓' : '⚠' }} Paciente</span><input v-model="form.patient_name" placeholder="Nombre no detectado"></label>
                            <label><span :class="{ missing: !form.identity }">{{ form.identity ? '✓' : '⚠' }} Identidad</span><input v-model="form.identity" placeholder="Identidad no detectada"></label>
                            <label><span :class="{ missing: form.age_at_consultation === null }">{{ form.age_at_consultation !== null ? '✓' : '⚠' }} Edad</span><input v-model="form.age_at_consultation" type="number" min="0" max="125"></label>
                            <label><span :class="{ missing: !form.consultation_date }">{{ form.consultation_date ? '✓' : '⚠' }} Consulta</span><input v-model="form.consultation_date" type="date"></label>
                            <label><span>✓ Hora</span><input v-model="form.consultation_time" type="time"></label>
                            <label class="wide"><span>✓ Síntomas extraídos</span><textarea v-model="form.symptoms" rows="3"></textarea></label>
                            <label class="wide"><span :class="{ missing: !form.diagnosis }">{{ form.diagnosis ? '✓' : '⚠' }} Diagnóstico</span><textarea v-model="form.diagnosis" rows="3" placeholder="Diagnóstico no detectado"></textarea></label>
                            <label class="wide"><span>✓ Recomendaciones</span><textarea v-model="form.recommendations" rows="3"></textarea></label>
                            <template v-if="kind === 'incapacidad'">
                                <label><span :class="{ missing: !form.leave_days }">{{ form.leave_days ? '✓' : '⚠' }} Días</span><input v-model="form.leave_days" type="number" min="1"><small v-if="form.errors.leave_days" class="field-error">{{ form.errors.leave_days }}</small></label>
                                <label><span :class="{ missing: !form.leave_start_date }">{{ form.leave_start_date ? '✓' : '⚠' }} Desde</span><input v-model="form.leave_start_date" type="date"></label>
                                <label><span :class="{ missing: !form.leave_end_date }">{{ form.leave_end_date ? '✓' : '⚠' }} Hasta</span><input v-model="form.leave_end_date" type="date"></label>
                            </template>
                        </div>
                        <div v-if="analysis.conflicts.length" class="analysis-conflicts"><strong>Conflictos detectados</strong><p v-for="conflict in analysis.conflicts" :key="conflict.field">⚠ {{ conflict.message }}</p></div>
                        <div class="patient-match" :class="{ new: !analysis.patient }"><strong>{{ analysis.patient ? '✓ Expediente vinculado' : 'NUEVO PACIENTE DETECTADO' }}</strong><span>{{ analysis.patient?.name || form.patient_name }}</span><label v-if="!analysis.patient"><input v-model="form.create_patient" type="checkbox"> Crear paciente automáticamente al confirmar</label></div>
                        <span v-if="form.errors.patient_id" class="field-error">{{ form.errors.patient_id }}</span>
                        <label v-if="canIssue && quickBilling" class="quick-billing-option"><input v-model="form.quick_invoice" type="checkbox"><span><strong>Emitir factura al finalizar</strong><small>{{ quickBilling.service }} · {{ quickBilling.quantity }} × {{ money(quickBilling.unit_price) }} · {{ quickBilling.tax_category }} · {{ quickBilling.payment_method }}</small></span></label>
                        <span v-if="form.errors.quick_invoice" class="field-error">{{ form.errors.quick_invoice }}</span>
                        <div class="quick-actions"><button class="button button--outline" type="button" @click="mobileTab = 'preview'">Vista previa</button><button class="button button--admin" type="button" :disabled="(canIssue && !ready) || form.processing" @click="submit(canIssue ? 'issue' : 'draft')">{{ canIssue ? (form.quick_invoice ? 'Emitir documento y factura' : 'Firmar y emitir documento') : 'Generar borrador' }}</button></div>
                    </div>
                </div>
                <aside :class="['certificate-preview', { 'mobile-hidden': mobileTab !== 'preview' }]">
                    <div class="certificate-preview__paper">
                        <header><span class="medical-symbol">+</span><div><strong>CLÍNICA MÉDICA SANTA ANA</strong><b>{{ provider.name }}</b><small>Entrada Principal colonia Torocagua, Frente a supermercado La Colonia<br>Comayagüela M.D.C., Honduras C.A.<br>Tel: +504 9485-5657 · Atención 24/7</small></div></header>
                        <i class="double-line"></i>
                        <dl><div><dt>PACIENTE</dt><dd>{{ form.patient_name || 'Pendiente' }}</dd></div><div><dt>EDAD</dt><dd>{{ form.age_at_consultation ?? '—' }} AÑOS</dd></div><div><dt>FECHA</dt><dd>{{ date(form.consultation_date) }}</dd></div></dl>
                        <h3>{{ kind === 'incapacidad' ? 'Incapacidad Médica' : 'Constancia Médica' }}</h3>
                        <div class="certificate-body"><p v-for="paragraph in previewParagraphs" :key="paragraph">{{ paragraph }}</p></div>
                        <footer><div><span>Atentamente.</span><i></i><strong>{{ provider.name }}</strong><small>{{ provider.credential_type }}: {{ provider.credential_number }}</small></div><div class="preview-qr">QR<small>Se genera al emitir</small></div></footer>
                    </div>
                    <small>Vista previa institucional. El QR, código y hash se incorporan al emitir.</small>
                </aside>
            </div>
        </section>
    </AdminLayout>
</template>
