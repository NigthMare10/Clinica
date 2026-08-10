<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { BookOpenText, ChevronLeft, ChevronRight, ClipboardPlus, FileCheck2, FileText, History, Home, Menu, Search, Settings, ShieldCheck, Stethoscope, Users } from '@lucide/vue';
import BrandMark from '@/Components/BrandMark.vue';

defineProps<{title:string;eyebrow?:string}>();
const open=ref(false);
const collapsed=ref(false);
const search=ref('');
const page=usePage();
const groups=[
    {label:'RESUMEN',items:[{label:'Inicio',pattern:'admin.dashboard',routeName:'admin.dashboard',icon:Home}]},
    {label:'OPERACIÓN',items:[
        {label:'Nueva constancia',pattern:'admin.documents.generate',routeName:'admin.documents.generate',icon:ClipboardPlus,parameter:'constancia'},
        {label:'Nueva incapacidad',pattern:'admin.documents.generate',routeName:'admin.documents.generate',icon:FileCheck2,parameter:'incapacidad'},
        {label:'Documentos',pattern:'admin.documents.*',routeName:'admin.documents.index',icon:FileText},
        {label:'Pacientes',pattern:'admin.patients.*',routeName:'admin.patients.index',icon:Users},
        {label:'Verificaciones',pattern:'admin.verifications.*',routeName:'admin.verifications.index',icon:ShieldCheck},
    ]},
    {label:'CONFIGURACIÓN',items:[
        {label:'Plantillas',pattern:'admin.templates.*',routeName:'admin.templates.index',icon:BookOpenText},
        {label:'Contenido',pattern:'admin.content.*',routeName:'admin.content.index',icon:Stethoscope},
        {label:'Auditoría',pattern:'admin.audit.*',routeName:'admin.audit.index',icon:History},
        {label:'Configuración',pattern:'admin.settings.*',routeName:'admin.settings.index',icon:Settings},
    ]},
];
type NavItem=(typeof groups)[number]['items'][number];
const active=(item:NavItem)=>Boolean(route().current(item.pattern))&&(!('parameter' in item)||route().params.kind===item.parameter);
const submitSearch=()=>{if(search.value.trim().length>=2)router.get(route('admin.search'),{q:search.value.trim()});};
const onKeydown=(event:KeyboardEvent)=>{if(event.key==='Escape')open.value=false;};
onMounted(()=>window.addEventListener('keydown',onKeydown));
onUnmounted(()=>window.removeEventListener('keydown',onKeydown));
</script>

<template><div :class="['admin-shell',{'sidebar-collapsed':collapsed}]"><aside :class="['admin-sidebar',{open,collapsed}]"><div class="sidebar-brand"><Link :href="route('admin.dashboard')"><BrandMark light/></Link><button type="button" @click="open=false" aria-label="Cerrar menú">×</button></div><nav aria-label="Administración"><section v-for="group in groups" :key="group.label"><small>{{ group.label }}</small><Link v-for="item in group.items" :key="item.label" :href="route(item.routeName,'parameter' in item?item.parameter:undefined)" :class="{active:active(item)}" :title="collapsed?item.label:undefined" @click="open=false"><component :is="item.icon" :size="19" :stroke-width="1.8" aria-hidden="true"/><span>{{ item.label }}</span></Link></section></nav><button class="sidebar-collapse" type="button" :aria-label="collapsed?'Expandir barra lateral':'Colapsar barra lateral'" @click="collapsed=!collapsed"><ChevronRight v-if="collapsed" :size="17"/><ChevronLeft v-else :size="17"/><span>{{ collapsed?'Expandir':'Colapsar' }}</span></button><div class="sidebar-foot"><span class="presence"></span><div><strong>{{ page.props.auth?.user?.name||'Usuario' }}</strong><small>Sesión protegida</small></div></div></aside><button v-if="open" class="sidebar-scrim" aria-label="Cerrar menú" @click="open=false"></button><div class="admin-main"><header class="admin-topbar"><button class="admin-menu" type="button" @click="open=true" aria-label="Abrir menú"><Menu :size="20"/></button><div><span>{{ eyebrow||'Gestión clínica' }}</span><h1>{{ title }}</h1></div><form class="admin-global-search" role="search" @submit.prevent="submitSearch"><label class="sr-only" for="global-search">Buscar pacientes, documentos o clínicas</label><input id="global-search" v-model="search" type="search" minlength="2" placeholder="Buscar en el sistema..." autocomplete="off"><button type="submit" aria-label="Buscar"><Search :size="17"/></button></form><div class="top-actions"><Link :href="route('public.home')" target="_blank" rel="noopener">Ver sitio</Link><Link :href="route('logout')" method="post" as="button">Salir</Link></div></header><main><div v-if="page.props.flash?.status" class="flash" role="status">{{ page.props.flash.status }}</div><slot/></main></div></div></template>
