<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

type Asset={id:string,kind:string,sha256:string,is_active:boolean,preview_url:string,created_at:string};
const props=defineProps<{assets:Asset[]}>();
const tab=ref<'upload'|'extract'>('upload');
const upload=useForm({kind:'signature',asset:null as File|null,active:true});
const extract=useForm({kind:'signature',document:null as File|null,x:0,y:0,width:200,height:200,confirmed_authorized:false});
const activeCombined=computed(()=>props.assets.find(asset=>asset.kind==='SIGNATURE_STAMP_COMBINED'&&asset.is_active));
const label=(kind:string)=>({signature:'Firma',stamp:'Sello',SIGNATURE_STAMP_COMBINED:'Firma + sello'}[kind]||kind);
const selectFile=(event:Event,form:typeof upload,key:'asset')=>form[key]=(event.target as HTMLInputElement).files?.[0]??null;
const selectDocument=(event:Event)=>extract.document=(event.target as HTMLInputElement).files?.[0]??null;
</script>

<template>
  <Head title="Firma y sello"/>
  <AdminLayout title="Firma y sello" eyebrow="Configuración institucional">
    <section class="admin-content signature-settings">
      <header class="signature-hero">
        <div><p class="kicker">Activos visuales privados</p><h2>Firma y sello institucional</h2><p>Los archivos permanecen en almacenamiento privado y solo se muestran a usuarios autorizados. Una marca visual no equivale a una firma criptográfica.</p></div>
        <form method="post" :action="route('admin.settings.signature.import-combined')"><input type="hidden" name="_token" :value="$page.props.csrf_token"><button class="button button--admin" type="submit">IMPORTAR FIRMA + SELLO DESDE DOCS</button></form>
      </header>

      <div v-if="activeCombined" class="combined-notice" role="status"><img :src="activeCombined.preview_url" alt="Vista previa de firma y sello combinados"><div><strong>Activo combinado en uso</strong><p>Al emitir documentos generados, esta marca tiene prioridad sobre la firma y el sello por separado.</p></div></div>

      <section class="signature-panel panel">
        <div class="signature-panel__head"><div><h3>Activos disponibles</h3><p>Historial y activación por tipo de marca.</p></div><span>{{ assets.length }} registrados</span></div>
        <div v-if="assets.length" class="asset-grid">
          <article v-for="asset in assets" :key="asset.id" class="asset-card" :class="{active:asset.is_active,combined:asset.kind==='SIGNATURE_STAMP_COMBINED'}"><div class="asset-card__image"><img :src="asset.preview_url" :alt="`Vista previa: ${label(asset.kind)}`"></div><div><p class="asset-card__type">{{ label(asset.kind) }}</p><strong>{{ asset.is_active?'Activo':'Histórico' }}</strong><small>{{ new Date(asset.created_at).toLocaleDateString('es-HN') }} · {{ asset.sha256.slice(0,12) }}</small></div><form v-if="!asset.is_active" method="post" :action="route('admin.settings.signature.activate',asset.id)"><input type="hidden" name="_token" :value="$page.props.csrf_token"><button class="row-action" type="submit">Activar</button></form></article>
        </div>
        <p v-else class="signature-empty">No hay activos registrados. Importe el recorte autorizado o cargue una imagen PNG/WebP.</p>
      </section>

      <section class="signature-workspace panel">
        <div class="signature-tabs" role="tablist" aria-label="Método de incorporación"><button :class="{selected:tab==='upload'}" type="button" role="tab" :aria-selected="tab==='upload'" @click="tab='upload'">Cargar imagen</button><button :class="{selected:tab==='extract'}" type="button" role="tab" :aria-selected="tab==='extract'" @click="tab='extract'">Extraer de PDF</button></div>
        <form v-if="tab==='upload'" class="signature-form" @submit.prevent="upload.post(route('admin.settings.signature.store'))"><div><p class="kicker">Carga controlada</p><h3>Nueva marca institucional</h3><p>Se normaliza como PNG con transparencia y se guarda en el disco privado configurado.</p></div><label class="field"><span>Tipo de activo</span><select v-model="upload.kind"><option value="signature">Firma</option><option value="stamp">Sello</option><option value="SIGNATURE_STAMP_COMBINED">Firma + sello combinados</option></select><small v-if="upload.kind==='SIGNATURE_STAMP_COMBINED'">Esta opción prevalece al estampar documentos generados.</small></label><label class="file-drop"><input type="file" accept="image/png,image/webp" @change="selectFile($event,upload,'asset')"><strong>{{ upload.asset?.name||'Seleccione una imagen PNG o WebP' }}</strong><span>Máximo 4 MB</span></label><label class="check-row"><input v-model="upload.active" type="checkbox"> Activar al guardar</label><p v-if="upload.errors.asset||upload.errors.kind" class="field-error">{{ upload.errors.asset||upload.errors.kind }}</p><button class="button button--admin" :disabled="upload.processing">Guardar activo</button></form>
        <form v-else class="signature-form extract-form" @submit.prevent="extract.post(route('admin.settings.signature.extract'))"><div><p class="kicker">Extracción local</p><h3>Recortar desde un documento autorizado</h3><p>La primera página se renderiza localmente a 300 DPI. Confirme la autorización antes de guardar el recorte.</p></div><label class="field"><span>Tipo de activo</span><select v-model="extract.kind"><option value="signature">Firma</option><option value="stamp">Sello</option><option value="SIGNATURE_STAMP_COMBINED">Firma + sello combinados</option></select></label><label class="file-drop"><input type="file" accept="application/pdf" @change="selectDocument"><strong>{{ extract.document?.name||'Seleccione un PDF autorizado' }}</strong><span>Máximo 15 MB</span></label><div class="crop-grid"><label class="field"><span>X</span><input v-model.number="extract.x" type="number" min="0"></label><label class="field"><span>Y</span><input v-model.number="extract.y" type="number" min="0"></label><label class="field"><span>Ancho</span><input v-model.number="extract.width" type="number" min="20"></label><label class="field"><span>Alto</span><input v-model.number="extract.height" type="number" min="20"></label></div><label class="check-row"><input v-model="extract.confirmed_authorized" type="checkbox" required> Confirmo que el documento y el recorte están autorizados.</label><p v-if="Object.keys(extract.errors).length" class="field-error">{{ Object.values(extract.errors)[0] }}</p><button class="button button--admin" :disabled="extract.processing">Extraer y guardar</button></form>
      </section>
    </section>
  </AdminLayout>
</template>
