<script setup lang="ts">
import axios from 'axios';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageMeta from '@/Components/PageMeta.vue';

type Clinic = { id: string; name: string };
type Authorization = {
    id: string;
    clinic_id: string;
    clinic: Clinic;
    cai: string;
    rtn: string;
    document_type: string;
    status: string;
    is_active: boolean;
    source: string | null;
    full_range_start: string;
    full_range_end: string;
    next_ncf: string;
    available_count: number;
    consumption_percentage: number;
    valid_from: string;
    valid_until: string;
};
type BillingProfile = {
    clinic_id: string;
    certificate_kind: ProfileKind;
    default_quantity: string;
    price_override: string | null;
    tax_category: string;
    default_payment_method: string;
    service: { code: string; name: string; default_price: string };
};
type ProfileKind = 'CONSTANCIA' | 'INCAPACIDAD';

const props = defineProps<{ clinics: Clinic[]; authorizations: Authorization[]; billingProfiles: BillingProfile[] }>();
const error = ref('');
const saved = ref('');
const busy = ref(false);
const selectedClinicId = ref(props.clinics[0]?.id || '');
const profileMessages = reactive<Record<ProfileKind, { error: string; saved: string; busy: boolean }>>({
    CONSTANCIA: { error: '', saved: '', busy: false },
    INCAPACIDAD: { error: '', saved: '', busy: false },
});
const profileForms = reactive<Record<ProfileKind, {
    service_code: string;
    service_name: string;
    price: number;
    tax_category: string;
    quantity: number;
    default_payment_method: string;
}>>({
    CONSTANCIA: { service_code: '', service_name: 'Constancia médica', price: 0, tax_category: 'EXENTO', quantity: 1, default_payment_method: 'EFECTIVO' },
    INCAPACIDAD: { service_code: '', service_name: 'Incapacidad médica', price: 0, tax_category: 'EXENTO', quantity: 1, default_payment_method: 'EFECTIVO' },
});
const loadedProfileKey = reactive<Record<ProfileKind, string>>({ CONSTANCIA: '', INCAPACIDAD: '' });
const authorizationForm = reactive({
    clinic_id: props.clinics[0]?.id || '',
    cai: '', rtn: '', establishment: '', point_of_issue: '', document_type: 'FACTURA_CONTADO', ncf_prefix: '',
    range_start: null as number | null, range_end: null as number | null, number_padding: 8,
    valid_from: '', valid_until: '', is_active: true,
});

const activeAuthorizations = computed(() => props.authorizations.filter(item => item.is_active && item.status === 'ACTIVE'));
const number = (value: number) => new Intl.NumberFormat('es-HN').format(value);
const profileLabel = (kind: ProfileKind) => kind === 'CONSTANCIA' ? 'Constancia' : 'Incapacidad';
const validationMessage = (exception: any, fallback: string) => {
    const errors = exception.response?.data?.errors;
    return errors ? Object.values(errors).flat().join(' ') : exception.response?.data?.message || fallback;
};

const loadProfile = (kind: ProfileKind) => {
    const key = `${selectedClinicId.value}:${kind}`;
    if (loadedProfileKey[kind] === key) return;
    loadedProfileKey[kind] = key;
    const profile = props.billingProfiles.find(item => item.clinic_id === selectedClinicId.value && item.certificate_kind === kind);
    Object.assign(profileForms[kind], profile ? {
        service_code: profile.service.code,
        service_name: profile.service.name,
        price: Number(profile.price_override ?? profile.service.default_price),
        tax_category: profile.tax_category,
        quantity: Number(profile.default_quantity),
        default_payment_method: profile.default_payment_method,
    } : {
        service_code: '',
        service_name: `${profileLabel(kind)} médica`,
        price: 0,
        tax_category: 'EXENTO',
        quantity: 1,
        default_payment_method: 'EFECTIVO',
    });
};
const loadProfiles = () => {
    loadProfile('CONSTANCIA');
    loadProfile('INCAPACIDAD');
};

const submitAuthorization = async () => {
    error.value = '';
    saved.value = '';
    busy.value = true;
    try {
        await axios.post(route('admin.fiscal-authorizations.store'), authorizationForm);
        saved.value = 'Autorización fiscal registrada correctamente.';
        Object.assign(authorizationForm, { cai: '', rtn: '', establishment: '', point_of_issue: '', ncf_prefix: '', range_start: null, range_end: null, valid_from: '', valid_until: '' });
        router.reload({ only: ['authorizations'] });
    } catch (exception: any) {
        error.value = validationMessage(exception, 'No se pudo registrar la autorización.');
    } finally {
        busy.value = false;
    }
};
const submitProfile = async (kind: ProfileKind) => {
    const message = profileMessages[kind];
    message.error = '';
    message.saved = '';
    message.busy = true;
    try {
        await axios.put(route('admin.fiscal-authorizations.billing-profile.upsert'), {
            clinic_id: selectedClinicId.value,
            kind,
            ...profileForms[kind],
        });
        message.saved = 'Perfil guardado correctamente.';
        router.reload({ only: ['billingProfiles'] });
    } catch (exception: any) {
        message.error = validationMessage(exception, 'No se pudo guardar el perfil.');
    } finally {
        message.busy = false;
    }
};

loadProfiles();
</script>

<template>
    <Head title="Configuración fiscal" />
    <AdminLayout title="Configuración fiscal" eyebrow="Administración">
        <PageMeta title="Configuración fiscal" noindex />
        <section class="admin-content fiscal-page">
            <div class="content-toolbar">
                <div>
                    <p class="kicker">Autorizaciones fiscales</p>
                    <h2>CAI, RTN y rangos NCF</h2>
                    <p>Registre autorizaciones vigentes, controle la numeración y configure la facturación rápida.</p>
                </div>
            </div>

            <div v-if="activeAuthorizations.length" class="fiscal-active-grid">
                <article v-for="authorization in activeAuthorizations" :key="authorization.id" class="panel fiscal-active-card">
                    <div class="fiscal-card-head">
                        <div><p class="kicker">Autorización activa</p><h3>{{ authorization.clinic.name }}</h3></div>
                        <span v-if="authorization.source === 'REFERENCE_INVOICE_IMPORT'" class="reference-badge">Referencia importada</span>
                    </div>
                    <dl class="fiscal-metrics">
                        <div><dt>Rango exacto inicial</dt><dd>{{ authorization.full_range_start }}</dd></div>
                        <div><dt>Rango exacto final</dt><dd>{{ authorization.full_range_end }}</dd></div>
                        <div><dt>Siguiente NCF</dt><dd>{{ authorization.next_ncf }}</dd></div>
                        <div><dt>Disponibles</dt><dd>{{ number(authorization.available_count) }}</dd></div>
                    </dl>
                    <div class="fiscal-consumption">
                        <span><b>Consumo</b><strong>{{ authorization.consumption_percentage }}%</strong></span>
                        <div class="fiscal-consumption__track"><i :style="{ width: `${authorization.consumption_percentage}%` }"></i></div>
                    </div>
                    <small>CAI {{ authorization.cai }} · Vigente hasta {{ authorization.valid_until }}</small>
                </article>
            </div>
            <div v-else class="panel invoice-empty">No hay una autorización fiscal activa.</div>

            <section class="panel billing-settings">
                <div class="panel-head billing-settings__head">
                    <div><p class="kicker">Facturación rápida</p><h3>Perfiles de documentos médicos</h3><p>Defina el servicio facturable para constancias e incapacidades.</p></div>
                    <label class="field">Clínica
                        <select v-model="selectedClinicId" @change="loadProfiles">
                            <option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">{{ clinic.name }}</option>
                        </select>
                    </label>
                </div>
                <div class="billing-profile-grid">
                    <form v-for="kind in (['CONSTANCIA', 'INCAPACIDAD'] as ProfileKind[])" :key="kind" class="billing-profile-card" @submit.prevent="submitProfile(kind)">
                        <div><p class="kicker">{{ kind }}</p><h3>{{ profileLabel(kind) }}</h3></div>
                        <p v-if="profileMessages[kind].error" class="field-error">{{ profileMessages[kind].error }}</p>
                        <p v-if="profileMessages[kind].saved" class="success-note">{{ profileMessages[kind].saved }}</p>
                        <div class="form-grid">
                            <div class="field"><label>Código del servicio</label><input v-model="profileForms[kind].service_code" required maxlength="60"></div>
                            <div class="field"><label>Nombre del servicio</label><input v-model="profileForms[kind].service_name" required maxlength="255"></div>
                            <div class="field"><label>Precio (HNL)</label><input v-model.number="profileForms[kind].price" required type="number" min="0" step="0.01"></div>
                            <div class="field"><label>Cantidad</label><input v-model.number="profileForms[kind].quantity" required type="number" min="0.001" step="0.001"></div>
                            <div class="field"><label>Categoría fiscal</label><select v-model="profileForms[kind].tax_category"><option v-for="tax in ['EXENTO','EXONERADO','GRAVADO_15','GRAVADO_18']" :key="tax">{{ tax }}</option></select></div>
                            <div class="field"><label>Forma de pago predeterminada</label><select v-model="profileForms[kind].default_payment_method"><option v-for="method in ['EFECTIVO','TARJETA','TRANSFERENCIA','MIXTO','OTRO']" :key="method">{{ method }}</option></select></div>
                        </div>
                        <button class="button" type="submit" :disabled="profileMessages[kind].busy || !selectedClinicId">{{ profileMessages[kind].busy ? 'Guardando...' : 'Guardar perfil' }}</button>
                    </form>
                </div>
            </section>

            <div class="invoice-detail__grid fiscal-authorization-workspace">
                <form class="panel form-panel" @submit.prevent="submitAuthorization">
                    <h3>Nueva autorización</h3>
                    <p v-if="error" class="field-error">{{ error }}</p><p v-if="saved" class="success-note">{{ saved }}</p>
                    <div class="form-grid">
                        <div class="field"><label>Clínica</label><select v-model="authorizationForm.clinic_id" required><option v-for="clinic in clinics" :key="clinic.id" :value="clinic.id">{{ clinic.name }}</option></select></div>
                        <div class="field"><label>CAI</label><input v-model="authorizationForm.cai" required maxlength="100"></div>
                        <div class="field"><label>RTN</label><input v-model="authorizationForm.rtn" required maxlength="30"></div>
                        <div class="field"><label>Tipo de documento</label><input v-model="authorizationForm.document_type" required maxlength="30"></div>
                        <div class="field"><label>Establecimiento</label><input v-model="authorizationForm.establishment" required maxlength="20"></div>
                        <div class="field"><label>Punto de emisión</label><input v-model="authorizationForm.point_of_issue" required maxlength="20"></div>
                        <div class="field"><label>Prefijo NCF</label><input v-model="authorizationForm.ncf_prefix" required maxlength="30"></div>
                        <div class="field"><label>Relleno numérico</label><input v-model.number="authorizationForm.number_padding" required type="number" min="1" max="20"></div>
                        <div class="field"><label>Inicio de rango</label><input v-model.number="authorizationForm.range_start" required type="number" min="1"></div>
                        <div class="field"><label>Final de rango</label><input v-model.number="authorizationForm.range_end" required type="number" min="1"></div>
                        <div class="field"><label>Válida desde</label><input v-model="authorizationForm.valid_from" required type="date"></div>
                        <div class="field"><label>Válida hasta</label><input v-model="authorizationForm.valid_until" required type="date"></div>
                    </div>
                    <button class="button" type="submit" :disabled="busy">{{ busy ? 'Registrando...' : 'Registrar autorización' }}</button>
                </form>
                <aside class="panel fiscal-guidance"><h3>Control de numeración</h3><p>El siguiente NCF se reserva de forma transaccional al emitir una factura. Los rangos históricos permanecen visibles para auditoría.</p></aside>
            </div>

            <section class="panel">
                <div class="panel-head"><div><p class="kicker">Historial</p><h3>Autorizaciones registradas</h3></div></div>
                <div class="table-scroll"><table><thead><tr><th>Clínica</th><th>Rango exacto</th><th>Siguiente NCF</th><th>CAI / vigencia</th><th>Estado</th></tr></thead><tbody>
                    <tr v-for="authorization in authorizations" :key="authorization.id">
                        <td>{{ authorization.clinic.name }}<small v-if="authorization.source === 'REFERENCE_INVOICE_IMPORT'" class="reference-text">Referencia importada</small></td>
                        <td><strong>{{ authorization.full_range_start }}</strong><small>{{ authorization.full_range_end }}</small></td>
                        <td>{{ authorization.next_ncf }}<small>{{ number(authorization.available_count) }} disponibles</small></td>
                        <td>{{ authorization.cai }}<small>{{ authorization.valid_from }} a {{ authorization.valid_until }}</small></td>
                        <td><span class="status-pill">{{ authorization.status }}</span></td>
                    </tr>
                    <tr v-if="!authorizations.length"><td colspan="5" class="invoice-empty">No hay autorizaciones registradas.</td></tr>
                </tbody></table></div>
            </section>
        </section>
    </AdminLayout>
</template>
