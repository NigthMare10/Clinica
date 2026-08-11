<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { hondurasDate } from '@/Composables/hondurasDate';
import type { Paginated } from '@/types';
type Invoice = { id:string; ncf:string|null; status:string; total:string; issued_at:string|null; recipient_name:string|null; patient?:{first_name:string;last_name:string}|null; medical_document?:{public_code:string}|null; clinic?:{name:string}|null };
const props = defineProps<{ invoices:Paginated<Invoice>; clinics:Array<{id:string;name:string}>; filters:{status?:string;clinic_id?:string;q?:string}; canCreate:boolean }>();
const filters = reactive({ status: props.filters.status || '', clinic_id: props.filters.clinic_id || '', q: props.filters.q || '' });
const applyFilters = () => router.get(route('admin.invoices.index'), filters, { preserveState: true, replace: true });
const money = (value:string) => new Intl.NumberFormat('es-HN', { style:'currency', currency:'HNL' }).format(Number(value));
const client = (invoice:Invoice) => invoice.patient ? `${invoice.patient.first_name} ${invoice.patient.last_name}` : invoice.recipient_name || 'Consumidor final';
</script>
<template>
  <Head title="Facturación" /><AdminLayout title="Facturación" eyebrow="Operación fiscal"><PageMeta title="Facturación" noindex />
    <section class="admin-content invoices-page"><div class="content-toolbar"><div><p class="kicker">Control de facturas</p><h2>Facturas y borradores</h2><p>La numeración NCF se asigna solamente al emitir una factura.</p></div><Link v-if="canCreate" class="button button--admin" :href="route('admin.invoices.create')">+ Nueva factura</Link></div>
      <form class="panel invoice-filters" @submit.prevent="applyFilters"><input v-model="filters.q" type="search" placeholder="NCF, cliente o paciente"><select v-model="filters.status"><option value="">Todos los estados</option><option value="DRAFT">Borradores</option><option value="ISSUED">Emitidas</option><option value="VOID">Anuladas</option></select><select v-model="filters.clinic_id"><option value="">Todas las clínicas</option><option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">{{ clinic.name }}</option></select><button class="button button--admin" type="submit">Filtrar</button><Link v-if="filters.q || filters.status || filters.clinic_id" class="row-action" :href="route('admin.invoices.index')">Limpiar</Link></form>
      <div class="panel table-scroll"><table><thead><tr><th>NCF / registro</th><th>Cliente</th><th>Clínica</th><th>Documento</th><th>Estado</th><th>Total</th><th></th></tr></thead><tbody><tr v-for="invoice in invoices.data" :key="invoice.id"><td><strong>{{ invoice.ncf || 'BORRADOR' }}</strong><small>{{ invoice.issued_at ? hondurasDate(invoice.issued_at) : 'Sin emitir' }}</small></td><td>{{ client(invoice) }}</td><td>{{ invoice.clinic?.name }}</td><td>{{ invoice.medical_document?.public_code || 'No asociado' }}</td><td><StatusBadge :status="invoice.status" /></td><td>{{ money(invoice.total) }}</td><td><Link class="row-action" :href="route('admin.invoices.show', invoice.id)">Ver</Link></td></tr><tr v-if="!invoices.data.length"><td colspan="7" class="invoice-empty">No hay facturas con estos filtros.</td></tr></tbody></table></div><Pagination :links="invoices.links" />
    </section>
  </AdminLayout>
</template>
