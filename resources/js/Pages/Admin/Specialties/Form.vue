<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import type { Specialty } from '@/types';

const props = defineProps<{ specialty?: Specialty }>();
const form = useForm({
    name: props.specialty?.name ?? '', slug: props.specialty?.slug ?? '',
    short_description: props.specialty?.short_description ?? '', description: props.specialty?.description ?? '',
    common_reasons: props.specialty?.common_reasons ?? [] as string[], services: props.specialty?.services ?? [] as string[],
    icon: props.specialty?.icon ?? '', seo_title: props.specialty?.seo_title ?? '', seo_description: props.specialty?.seo_description ?? '',
    is_active: props.specialty?.is_active ?? true, is_public: props.specialty?.is_public ?? true, sort_order: props.specialty?.sort_order ?? 0,
});
const lines = (value: string) => value.split('\n').map(item => item.trim()).filter(Boolean);
let reasons = (form.common_reasons as string[]).join('\n');
let services = (form.services as string[]).join('\n');
const submit = () => {
    form.common_reasons = lines(reasons); form.services = lines(services);
    props.specialty ? form.put(route('admin.specialties.update', props.specialty.id)) : form.post(route('admin.specialties.store'));
};
</script>
<template><form class="panel form-panel" @submit.prevent="submit"><div class="form-grid"><div class="field"><label for="name">Nombre</label><input id="name" v-model="form.name" required maxlength="255"><span class="field-error">{{ form.errors.name }}</span></div><div class="field"><label for="slug">Slug</label><input id="slug" v-model="form.slug" required maxlength="255" pattern="[A-Za-z0-9_-]+"><span class="field-error">{{ form.errors.slug }}</span></div><div class="field field--wide"><label for="short-description">Descripción breve</label><textarea id="short-description" v-model="form.short_description" maxlength="1000" rows="3"></textarea><span class="field-error">{{ form.errors.short_description }}</span></div><div class="field field--wide"><label for="description">Descripción</label><textarea id="description" v-model="form.description" maxlength="20000" rows="7"></textarea><span class="field-error">{{ form.errors.description }}</span></div><div class="field"><label for="reasons">Motivos frecuentes <i>uno por línea</i></label><textarea id="reasons" v-model="reasons" rows="6"></textarea><span class="field-error">{{ form.errors.common_reasons }}</span></div><div class="field"><label for="services">Servicios <i>uno por línea</i></label><textarea id="services" v-model="services" rows="6"></textarea><span class="field-error">{{ form.errors.services }}</span></div><div class="field"><label for="icon">Icono</label><input id="icon" v-model="form.icon" maxlength="100"><span class="field-error">{{ form.errors.icon }}</span></div><div class="field"><label for="sort-order">Orden</label><input id="sort-order" v-model.number="form.sort_order" type="number" min="0" max="65535" required><span class="field-error">{{ form.errors.sort_order }}</span></div><div class="field"><label for="seo-title">Título SEO</label><input id="seo-title" v-model="form.seo_title" maxlength="255"><span class="field-error">{{ form.errors.seo_title }}</span></div><div class="field"><label for="seo-description">Descripción SEO</label><textarea id="seo-description" v-model="form.seo_description" maxlength="1000" rows="3"></textarea><span class="field-error">{{ form.errors.seo_description }}</span></div></div><div class="check-row"><label><input v-model="form.is_active" type="checkbox"> Activa</label><label><input v-model="form.is_public" type="checkbox"> Pública</label></div><div class="form-actions"><Link class="button button--outline-small" :href="route('admin.specialties.index')">Cancelar</Link><button class="button button--admin" :disabled="form.processing">{{ form.processing?'Guardando...':'Guardar especialidad' }}</button></div></form></template>
