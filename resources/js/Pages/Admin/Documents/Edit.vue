<script setup lang="ts">
import axios from 'axios';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';

const props = defineProps<{ document: any; previewUrl: string; sourceText: string; fields: Record<string, any>; currentRevisionId: string; invoice?: any }>();
const form = useForm({ current_revision_id: props.currentRevisionId, reason: 'Error de redacción', source_text: props.sourceText, fields: { ...props.fields } });
const activeTab = ref<'edit' | 'preview'>('edit');
const previewUrl = ref(props.previewUrl);
const temporaryPreview = ref(false);
const previewMode = ref<'current' | 'changes'>('current');
const previewFailed = ref(false);
const analyzing = ref(false);
const previewing = ref(false);
const changes = ref<{ field: string; before: unknown; after: unknown }[]>([]);
const dirty = ref(false);
const saveError = ref('');
const fieldNames: Record<string, string> = { patient_name: 'Paciente', identity: 'Identidad', age_at_consultation: 'Edad', consultation_date: 'Fecha', consultation_time: 'Hora', diagnosis: 'Diagnóstico', leave_days: 'Días', leave_start_date: 'Inicio', leave_end_date: 'Final', recommendations: 'Recomendaciones' };
const title = computed(() => props.document.certificate_kind === 'INCAPACIDAD' ? 'Editar incapacidad médica' : 'Editar constancia médica');
const updated = computed(() => (usePage().props.flash as { status?: string } | undefined)?.status === 'DOCUMENTO ACTUALIZADO');
const dateTitle = computed(() => {
  const value = form.fields.consultation_date;
  if (!value || !/^\d{2}\/\d{2}\/\d{4}$/.test(value)) return 'Fecha no indicada';
  const [day, month, year] = value.split('/').map(Number);
  return new Intl.DateTimeFormat('es-HN', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
});
const timeTitle = computed(() => {
  if (!form.fields.consultation_time) return 'Hora no indicada';
  const [hour, minute] = form.fields.consultation_time.split(':').map(Number);
  return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'a. m.' : 'p. m.'}`;
});

watch(() => [form.source_text, JSON.stringify(form.fields)], () => { dirty.value = true; temporaryPreview.value = false; previewMode.value = 'current'; });
window.addEventListener('beforeunload', (event) => { if (dirty.value && !form.processing) { event.preventDefault(); event.returnValue = ''; } });
onBeforeUnmount(() => { if (previewUrl.value.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value); });

const analyze = async () => {
  analyzing.value = true;
  try {
    const { data } = await axios.post(route('admin.documents.edit.analyze', props.document.id), { source_text: form.source_text, fields: form.fields });
    changes.value = data.changes;
    Object.entries(data.fields).forEach(([key, value]) => { if (value !== null && value !== '') form.fields[key] = value; });
  } finally { analyzing.value = false; }
};
const updatePreview = async () => {
  previewing.value = true;
  try {
    const { data } = await axios.post(route('admin.documents.edit.preview', props.document.id), { source_text: form.source_text, fields: form.fields }, { responseType: 'blob' });
    if (previewUrl.value.startsWith('blob:')) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = URL.createObjectURL(data);
    temporaryPreview.value = true;
    previewMode.value = 'changes';
    previewFailed.value = false;
    activeTab.value = 'preview';
  } finally { previewing.value = false; }
};
const save = async () => {
  saveError.value = '';
  form.processing = true;
  try {
    const { data } = await axios.patch(route('admin.documents.update', props.document.id), form.data(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    dirty.value = false;
    router.visit(data.redirect_url);
  } catch (error: any) {
    const status = error.response?.status;
    const details = error.response?.data;
    saveError.value = status === 401 || status === 419 ? 'Tu sesión expiró. Vuelve a iniciar sesión.' : [details?.error_code, details?.message].filter(Boolean).join(': ') || 'No fue posible regenerar el documento. La versión anterior continúa vigente.';
  } finally { form.processing = false; }
};
const leave = () => { if (!dirty.value || window.confirm('Hay cambios sin guardar.')) window.location.assign(route('admin.documents.index')); };
const openPreview = () => window.open(previewMode.value === 'current' ? props.previewUrl : previewUrl.value, '_blank');
const showCurrentPreview = () => { previewMode.value = 'current'; previewFailed.value = false; };
const showChangedPreview = () => { previewMode.value = 'changes'; previewFailed.value = false; };
</script>

<template>
  <AdminLayout :title="`Editar documento · ${document.public_code}`" eyebrow="Documentos">
    <PageMeta title="Editar documento" noindex />
    <section class="admin-content document-editor">
      <header class="editor-header"><div><p class="kicker">Editar documento</p><h2>{{ title }}</h2><strong>{{ document.public_code }}</strong></div><div class="appointment"><span>Paciente: <b>{{ form.fields.patient_name }}</b></span><span>Fecha: <b>{{ dateTitle }}</b></span><span>Hora: <b>{{ timeTitle }}</b></span></div></header>
      <div v-if="updated" class="updated-note"><div><b>DOCUMENTO ACTUALIZADO</b><span>Código: {{ document.public_code }}</span></div><div><a :href="previewUrl" target="_blank">Ver documento</a><a :href="route('admin.documents.download', { document: document.id, version: 'issued' })">Descargar</a><Link :href="route('public.verify.lookup')">Validar</Link><Link :href="route('admin.documents.index')">Volver a documentos</Link></div></div>
      <div v-if="invoice" class="invoice-note"><span>Factura relacionada: <b>{{ invoice.ncf || 'Borrador' }}</b></span><Link :href="route('admin.invoices.show', invoice.id)">Ver factura</Link><small>Los cambios en nombre, identidad o fecha no modificarán automáticamente una factura ya emitida.</small></div>
      <div class="mobile-tabs"><button :class="{ selected: activeTab === 'edit' }" @click="activeTab = 'edit'">Editar</button><button :class="{ selected: activeTab === 'preview' }" @click="activeTab = 'preview'">Vista previa</button></div>
      <div class="editor-grid">
        <section class="panel preview-panel" :class="{ 'mobile-hidden': activeTab !== 'preview' }"><div class="preview-sticky"><div class="panel-head"><div><h2>{{ previewMode === 'changes' ? 'Vista previa de cambios' : 'Vista previa actual' }}</h2><small v-if="previewMode === 'changes'" class="preview-badge">VISTA PREVIA - NO GUARDADO</small></div><div><button class="row-action" type="button" @click="openPreview">Abrir documento</button><a v-if="previewMode === 'current'" class="row-action" :href="route('admin.documents.download', { document: document.id, version: 'issued' })">Descargar actual</a></div></div><div v-if="temporaryPreview" class="preview-selector"><button :class="{ selected: previewMode === 'current' }" type="button" @click="showCurrentPreview">Actual</button><button :class="{ selected: previewMode === 'changes' }" type="button" @click="showChangedPreview">Cambios</button></div><iframe v-if="!previewFailed" :src="previewMode === 'current' ? props.previewUrl : previewUrl" title="PDF vigente" @error="previewFailed = true" /><div v-else class="preview-error">No fue posible cargar la vista previa.<button class="row-action" type="button" @click="openPreview">Abrir documento</button></div></div></section>
        <section class="panel edit-panel" :class="{ 'mobile-hidden': activeTab !== 'edit' }"><div class="panel-head"><div><h2>Contenido del documento</h2><p>Edite el texto completo; el PDF se regenerará sólo al guardar.</p></div><button class="row-action" :disabled="analyzing" type="button" @click="analyze">{{ analyzing ? 'Analizando...' : 'Analizar cambios' }}</button></div>
          <div class="canonical-grid"><label>Paciente<input v-model="form.fields.patient_name"></label><label>Identidad<input v-model="form.fields.identity"></label><label>Edad<input v-model.number="form.fields.age_at_consultation" type="number"></label><label>Fecha<input v-model="form.fields.consultation_date" placeholder="14/08/2026"></label><label>Hora<input v-model="form.fields.consultation_time" placeholder="11:00"></label></div>
          <label class="source-label">Texto completo<textarea v-model="form.source_text" rows="18" /></label>
          <details><summary>Datos detectados</summary><div class="detected-grid"><label>Diagnóstico<input v-model="form.fields.diagnosis"></label><label>Días de incapacidad<input v-model.number="form.fields.leave_days" type="number"></label><label>Inicio<input v-model="form.fields.leave_start_date" placeholder="14/08/2026"></label><label>Final<input v-model="form.fields.leave_end_date" placeholder="14/08/2026"></label><label class="wide">Recomendaciones<textarea v-model="form.fields.recommendations" rows="3" /></label></div></details>
          <div v-if="changes.length" class="changes"><b>Cambios detectados</b><p v-for="change in changes" :key="change.field"><strong>{{ fieldNames[change.field] || change.field }}:</strong> {{ change.before || 'No indicado' }} → {{ change.after }}</p></div>
          <label class="reason">Motivo interno<select v-model="form.reason"><option>Error de redacción</option><option>Error de datos</option><option>Solicitud del paciente</option><option>Otro</option></select></label>
          <p v-if="form.errors.source_text || form.errors.current_revision_id" class="form-error">{{ form.errors.source_text || form.errors.current_revision_id }}</p>
          <p v-if="saveError" class="form-error">{{ saveError }}</p>
        </section>
      </div>
      <footer class="sticky-actions"><span v-if="dirty" class="unsaved">Cambios sin guardar</span><button class="button button--outline-small" type="button" @click="leave">Cancelar</button><button class="button button--outline-small" :disabled="previewing" type="button" @click="updatePreview">{{ previewing ? 'Actualizando...' : 'Actualizar vista previa' }}</button><button class="button button--admin" :disabled="form.processing" type="button" @click="save">{{ form.processing ? 'Regenerando documento...' : 'Guardar cambios y regenerar' }}</button></footer>
    </section>
  </AdminLayout>
</template>

<style scoped>
.document-editor{padding-bottom:6.5rem}.editor-header,.invoice-note,.updated-note,.panel-head,.sticky-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem}.editor-header{margin-bottom:1rem}.editor-header h2{margin:.2rem 0}.appointment{display:flex;flex-wrap:wrap;gap:1rem;font-size:.9rem}.updated-note{padding:.8rem 1rem;margin-bottom:1rem;background:#eaf7ef;border-left:3px solid #228550}.updated-note div{display:flex;gap:.6rem;flex-wrap:wrap}.updated-note span{display:block;font-size:.85rem}.updated-note a{font-size:.85rem}.invoice-note{padding:.65rem .9rem;margin-bottom:1rem;background:#fff7df;border-left:3px solid #d99a14;font-size:.85rem}.invoice-note small{color:#59616d}.editor-grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:1rem}.panel{min-width:0}.preview-sticky{position:sticky;top:1rem}.panel-head{align-items:flex-start}.panel-head h2{margin:0;font-size:1rem}.panel-head p{margin:.25rem 0 0;color:#59616d;font-size:.85rem}.preview-panel iframe{width:100%;height:min(720px,calc(100vh - 180px));border:0;margin-top:1rem;background:#f5f7f8}.preview-selector{display:flex;gap:.35rem;margin-top:.65rem}.preview-selector button{border:1px solid #cdd6dc;background:#fff;padding:.35rem .65rem}.preview-selector .selected{background:#0a5f89;color:#fff}.preview-error{display:grid;place-items:center;gap:.75rem;min-height:360px;margin-top:1rem;background:#f5f7f8;color:#59616d}.preview-badge{display:inline-block;margin-top:.3rem;color:#9c6410;font-weight:700}.canonical-grid,.detected-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.7rem;margin:1rem 0}.canonical-grid label,.detected-grid label,.source-label,.reason{display:grid;gap:.3rem;font-size:.78rem;font-weight:700;color:#334}.canonical-grid input,.detected-grid input,.source-label textarea,.reason select{width:100%;font:inherit;font-weight:400}.source-label textarea{resize:vertical;min-height:420px;font-size:.95rem;line-height:1.55}.detected-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wide{grid-column:1/-1}.edit-panel details{border-top:1px solid #e3e7eb;padding-top:.85rem}.edit-panel summary{cursor:pointer;font-weight:700}.changes{margin:1rem 0;padding:.75rem;background:#eef7fb;border-left:3px solid #177ba8;font-size:.85rem}.changes p{margin:.35rem 0}.reason{max-width:280px;margin-top:1rem}.sticky-actions{position:sticky;bottom:0;margin-top:1rem;padding:.75rem;padding-bottom:max(.75rem,env(safe-area-inset-bottom));background:rgba(255,255,255,.98);border:1px solid #dfe5ea;border-top:2px solid #cdd6dc;z-index:10}.unsaved{margin-right:auto;font-size:.8rem;color:#9c6410;font-weight:700}.mobile-tabs{display:none}.form-error{color:#b42318}@media (max-width:760px){.document-editor{padding-bottom:8rem}.editor-header,.invoice-note,.updated-note{align-items:flex-start;flex-direction:column}.editor-grid{display:block}.preview-sticky{position:static}.mobile-tabs{display:flex;gap:.5rem;margin-bottom:1rem}.mobile-tabs button{flex:1;padding:.65rem;border:1px solid #cdd6dc;background:white}.mobile-tabs .selected{background:#0a5f89;color:white}.mobile-hidden{display:none}.preview-panel iframe{height:62vh}.canonical-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.canonical-grid label:first-child{grid-column:1/-1}.sticky-actions{gap:.5rem;flex-wrap:wrap}.sticky-actions .button{font-size:.75rem;padding:.65rem .45rem}.unsaved{width:100%}.source-label textarea{min-height:300px}}
</style>
