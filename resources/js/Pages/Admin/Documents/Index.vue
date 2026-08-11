<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { hondurasDate } from '@/Composables/hondurasDate';
import type { MedicalDocument, Paginated } from '@/types';

const props = defineProps<{ documents: Paginated<MedicalDocument>; filters: { search?: string; status?: string } }>();
const filters = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
let timer: number;
const apply = () => router.get(route('admin.documents.index'), { search: filters.search || undefined, status: filters.status || undefined }, { preserveState: true, replace: true });
watch(() => filters.search, () => { window.clearTimeout(timer); timer = window.setTimeout(apply, 350); });
const name = (person?: { first_name: string; last_name: string } | null) => person ? `${person.first_name} ${person.last_name}` : 'Sin asignar';
const date = (value: string) => hondurasDate(value);
const reviewable = (document: MedicalDocument) => ['REVIEW_REQUIRED', 'READY'].includes(document.status);
let poll: number | undefined;
onMounted(() => { if (props.documents.data.some((document) => document.status === 'PROCESSING')) poll = window.setInterval(() => router.reload({ only: ['documents'] }), 2500); });
onUnmounted(() => window.clearInterval(poll));
const revoke = (document: MedicalDocument) => { const reason = window.prompt('Motivo de revocación'); if (reason?.trim()) router.post(route('admin.documents.revoke', document.id), { reason: reason.trim() }, { preserveScroll: true }); };
const reissue = (document: MedicalDocument) => { const reason = window.prompt('Motivo de la corrección'); if (reason?.trim()) router.post(route('admin.documents.corrections.store', document.id), { reason: reason.trim() }, { preserveScroll: true }); };
</script>

<template>
    <AdminLayout title="Documentos médicos" eyebrow="Gestión documental">
        <PageMeta title="Documentos médicos" noindex />
        <section class="admin-content documents-page"><div class="content-toolbar"><div><p class="kicker">Repositorio verificable</p><h2>{{ documents.total }} documentos</h2><p>Carga, genera, revisa y emite documentos desde un solo flujo.</p></div></div><div class="new-document-panel"><div><span>+</span><div><small>NUEVO DOCUMENTO MÉDICO</small><h3>¿Cómo deseas comenzar?</h3></div></div><nav><Link :href="route('admin.documents.create')"><b>↑</b><span><strong>Cargar PDF</strong><small>Procesar un archivo existente</small></span></Link><Link :href="route('admin.documents.generate', 'constancia')"><b>C</b><span><strong>Crear constancia</strong><small>Generar y enviar a revisión</small></span></Link><Link :href="route('admin.documents.generate', 'incapacidad')"><b>I</b><span><strong>Crear incapacidad</strong><small>Definir fechas y recomendaciones</small></span></Link></nav></div><div class="panel table-panel document-table-panel"><div class="filters"><label class="search"><span>⌕</span><input v-model="filters.search" type="search" placeholder="Buscar código, archivo o paciente" aria-label="Buscar documentos"></label><label><span class="sr-only">Filtrar por estado</span><select v-model="filters.status" @change="apply"><option value="">Todos los estados</option><option value="PROCESSING">Procesando</option><option value="REVIEW_REQUIRED">Revisión requerida</option><option value="READY">Listo</option><option value="ISSUED">Emitido</option><option value="REVOKED">Revocado</option><option value="REPLACED">Reemplazado</option><option value="FAILED">Fallido</option></select></label></div><div v-if="documents.data.length" class="table-scroll"><table><thead><tr><th>Documento</th><th>Paciente</th><th>Profesional</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody><tr v-for="document in documents.data" :key="document.id"><td><strong class="code-wrap">{{ document.public_code || document.original_filename }}</strong><small>{{ document.type.replaceAll('_', ' ') }}</small></td><td>{{ name(document.patient) }}</td><td>{{ name(document.doctor) }}</td><td><StatusBadge :status="document.status" /><small v-if="document.status === 'PROCESSING'">Actualización automática</small></td><td>{{ date(document.created_at) }}</td><td><div class="row-actions"><Link v-if="reviewable(document)" class="row-action" :href="route('admin.documents.review', document.id)">Revisar</Link><a class="row-action" :href="route('admin.documents.download', { document: document.id, version: 'original' })">Original</a><a v-if="document.status === 'ISSUED'" class="row-action" :href="route('admin.documents.download', { document: document.id, version: 'issued' })">Emitido</a><button v-if="document.status === 'ISSUED'" class="row-action" type="button" @click="revoke(document)">Anular</button><button v-if="document.status === 'REVOKED'" class="row-action" type="button" @click="reissue(document)">Reemitir</button></div></td></tr></tbody></table></div><EmptyState v-else title="No hay documentos con estos filtros" /><Pagination :links="documents.links" /></div></section>
    </AdminLayout>
</template>
