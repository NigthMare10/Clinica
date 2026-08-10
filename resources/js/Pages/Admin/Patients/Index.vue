<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';
import Pagination from '@/Components/Pagination.vue';
import type { Paginated, Patient } from '@/types';

defineProps<{ patients: Paginated<Patient> }>();
const initials = (patient: Patient) => `${patient.first_name[0] || ''}${patient.last_name[0] || ''}`.toUpperCase();
const identity = (patient: Patient) => [patient.document_type, patient.document_number].filter(Boolean).join(' ') || 'Identidad no indicada';
</script>

<template>
    <AdminLayout title="Pacientes" eyebrow="Expedientes clínicos">
        <PageMeta title="Pacientes" noindex />
        <section class="admin-content patients-page"><div class="content-toolbar patients-toolbar"><div><p class="kicker">Registro clínico</p><h2>{{ patients.total }} pacientes</h2><p>Consulta datos de contacto, identidad e historial documental.</p></div><div class="toolbar-actions"><span class="privacy-chip">Datos protegidos</span><Link class="button button--admin" :href="route('admin.patients.create')">+ Crear paciente</Link></div></div><div v-if="patients.data.length" class="patient-card-grid"><article v-for="item in patients.data" :key="item.id" class="patient-card"><div class="patient-card__head"><span>{{ initials(item) }}</span><div><h3>{{ item.first_name }} {{ item.last_name }}</h3><small>{{ identity(item) }}</small></div><i>Activo</i></div><dl><div><dt>Edad</dt><dd>{{ item.age ? `${item.age} años` : 'No registrada' }}</dd></div><div><dt>Sexo</dt><dd>{{ item.sex || 'No indicado' }}</dd></div><div><dt>Teléfono</dt><dd>{{ item.phone || 'No registrado' }}</dd></div><div><dt>Correo</dt><dd>{{ item.email || 'No registrado' }}</dd></div></dl><div class="patient-card__actions"><Link :href="route('admin.patients.show', item.id)">Ver expediente <span>→</span></Link><Link :href="route('admin.patients.edit', item.id)">Editar</Link></div></article></div><div v-else class="patient-empty"><span>+</span><h3>Crea el primer expediente</h3><p>Registra un paciente para comenzar a asociar documentos médicos.</p><Link class="button button--admin" :href="route('admin.patients.create')">Nuevo paciente</Link></div><Pagination :links="patients.links" /></section>
    </AdminLayout>
</template>
