<script setup lang="ts">
import axios from "axios";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
import { hondurasDateTime } from "@/Composables/hondurasDate";
const props = defineProps<{
    invoice: any;
    authorizations: any[];
    canIssue: boolean;
    canVoid: boolean;
    canUpdate: boolean;
    canCorrect: boolean;
}>();
const issueOpen = ref(false);
const voidOpen = ref(false);
const correctOpen = ref(false);
const authorizationId = ref("");
const voidReason = ref("");
const correctionReason = ref("");
const error = ref("");
const busy = ref(false);
const verificationUrl = ref("");
const available = computed(() =>
    props.authorizations.filter(
        (item) => item.status === "ACTIVE" && item.is_active,
    ),
);
const money = (value: string) =>
    new Intl.NumberFormat("es-HN", {
        style: "currency",
        currency: "HNL",
    }).format(Number(value));
const dateTime = (value?: string | null) => hondurasDateTime(value);
const serviceDate = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", {
              dateStyle: "long",
              timeZone: "UTC",
          }).format(new Date(`${value.slice(0, 10)}T12:00:00Z`))
        : "No registrada";
const serviceTime = (value?: string | null) => {
    if (!value) return "No registrada";
    const [hours, minutes] = value.split(":").map(Number);
    return `${hours % 12 || 12}:${String(minutes).padStart(2, "0")} ${hours < 12 ? "a. m." : "p. m."}`;
};
const documentType = (value?: string | null) =>
    ({
        CONSTANCIA: "Constancia médica",
        INCAPACIDAD: "Incapacidad médica",
        MEDICAL_CERTIFICATE: "Certificado médico",
        MEDICAL_REPORT: "Informe médico",
        PRESCRIPTION: "Receta médica",
        LAB_RESULT: "Resultado de laboratorio",
        REFERRAL: "Referencia médica",
        OTHER: "Otro documento médico",
    })[value || ""] || "No registrado";
const issue = async () => {
    error.value = "";
    busy.value = true;
    try {
        const { data } = await axios.post(
            route("admin.invoices.issue", props.invoice.id),
            { fiscal_authorization_id: authorizationId.value || null },
        );
        verificationUrl.value = data.verification_url;
        issueOpen.value = false;
        router.reload({ only: ["invoice", "authorizations"] });
    } catch (e: any) {
        error.value =
            e.response?.data?.message || "No fue posible emitir la factura.";
    } finally {
        busy.value = false;
    }
};
const voidInvoice = async () => {
    error.value = "";
    busy.value = true;
    try {
        await axios.post(route("admin.invoices.void", props.invoice.id), {
            reason: voidReason.value,
        });
        voidOpen.value = false;
        router.reload({ only: ["invoice"] });
    } catch (e: any) {
        error.value =
            e.response?.data?.message || "No fue posible anular la factura.";
    } finally {
        busy.value = false;
    }
};
const correctInvoice = async () => {
    error.value = "";
    busy.value = true;
    try {
        const { data } = await axios.post(route("admin.invoices.corrections.store", props.invoice.id), {
            reason: correctionReason.value,
        });
        router.visit(route("admin.invoices.show", data.replacement.id));
    } catch (e: any) {
        error.value = e.response?.data?.message || "No fue posible corregir la factura.";
    } finally {
        busy.value = false;
    }
};
</script>
<template>
    <Head :title="invoice.ncf || 'Borrador de factura'" /><AdminLayout
        title="Detalle de factura"
        eyebrow="Operación fiscal"
        ><PageMeta title="Factura" noindex />
        <section class="admin-content invoice-detail">
            <div class="content-toolbar">
                <div>
                    <p class="kicker">
                        {{ invoice.ncf || "Borrador sin NCF" }}
                    </p>
                    <h2>
                        {{
                            invoice.patient
                                ? `${invoice.patient.first_name} ${invoice.patient.last_name}`
                                : invoice.recipient_name || "Consumidor final"
                        }}
                    </h2>
                    <p>
                        {{ invoice.clinic?.name }} · Creada
                        {{
                            dateTime(invoice.created_at)
                        }}
                    </p>
                </div>
                <div class="toolbar-actions">
                    <StatusBadge :status="invoice.status" /><Link
                        class="row-action"
                        :href="route('admin.invoices.index')"
                        >Volver</Link
                    >
                </div>
            </div>
            <p v-if="error" class="field-error">{{ error }}</p>
            <div v-if="verificationUrl" class="panel success-note">
                Factura emitida. El enlace de verificación contiene el token
                privado y se muestra únicamente en esta sesión.
                <a :href="verificationUrl" target="_blank" rel="noopener"
                    >Verificar factura</a
                >
            </div>
            <nav
                v-if="invoice.status !== 'DRAFT' || invoice.medical_document"
                class="panel toolbar-actions"
                aria-label="Documentos relacionados"
            >
                <a
                    v-if="invoice.status !== 'DRAFT'"
                    class="button button--secondary"
                    :href="route('admin.invoices.download', invoice.id)"
                    >Descargar factura</a
                >
                <a
                    v-if="invoice.status !== 'DRAFT'"
                    class="button button--secondary"
                    :href="route('admin.invoices.preview', invoice.id)"
                    target="_blank"
                    rel="noopener"
                    >Vista previa factura</a
                >
                <a
                    v-if="verificationUrl"
                    class="button button--secondary"
                    :href="verificationUrl"
                    target="_blank"
                    rel="noopener"
                    >Verificar factura</a
                >
                <Link
                    v-if="invoice.medical_document"
                    class="button button--secondary"
                    :href="route('admin.documents.review', invoice.medical_document.id)"
                    >Abrir documento médico</Link
                >
                <Link
                    v-if="invoice.medical_document?.public_code"
                    class="button button--secondary"
                    :href="route('public.verify.lookup', { code: invoice.medical_document.public_code })"
                    >Verificar documento médico</Link
                >
            </nav>
            <div class="invoice-detail__grid">
                <article class="panel">
                    <h3>Resumen fiscal</h3>
                    <dl class="invoice-summary">
                        <div>
                            <dt>NCF</dt>
                            <dd>
                                {{ invoice.ncf || "Se asignará al emitir" }}
                            </dd>
                        </div>
                        <div>
                            <dt>RTN receptor</dt>
                            <dd>
                                {{ invoice.recipient_tax_id || "No indicado" }}
                            </dd>
                        </div>
                        <div>
                            <dt>Autorización</dt>
                            <dd>
                                {{ invoice.authorization?.cai || "Pendiente" }}
                            </dd>
                        </div>
                        <div>
                            <dt>Emitida</dt>
                            <dd>
                                {{
                                    invoice.issued_at ? dateTime(invoice.issued_at) : "Pendiente"
                                }}
                            </dd>
                        </div>
                        <div v-if="invoice.voided_at">
                            <dt>Anulada</dt>
                            <dd>
                                {{
                                    dateTime(invoice.voided_at)
                                }}
                            </dd>
                        </div>
                        <div v-if="invoice.void_reason">
                            <dt>Motivo</dt>
                            <dd>{{ invoice.void_reason }}</dd>
                        </div>
                    </dl>
                </article>
                <article class="panel invoice-amount">
                    <span>Total</span><strong>{{ money(invoice.total) }}</strong
                    ><small>Impuestos: {{ money(invoice.tax_total) }}</small>
                </article>
            </div>
            <article class="panel invoice-service-summary">
                <div>
                    <p class="kicker">Atención vinculada</p>
                    <h3>Fecha y hora del servicio</h3>
                </div>
                <dl>
                    <div><dt>Fecha de atención/servicio</dt><dd>{{ serviceDate(invoice.service_date) }}</dd></div>
                    <div><dt>Hora de atención</dt><dd>{{ serviceTime(invoice.service_time) }}</dd></div>
                    <div><dt>Emisión fiscal</dt><dd>{{ invoice.issued_at ? dateTime(invoice.issued_at) : "Pendiente" }}</dd></div>
                </dl>
            </article>
            <article v-if="invoice.medical_document" class="panel related-medical-document">
                <div class="panel-head">
                    <div><p class="kicker">Vinculación administrativa</p><h3>Documento médico relacionado</h3><p>Datos administrativos de trazabilidad. No incluye diagnóstico, síntomas ni contenido clínico.</p></div>
                    <StatusBadge :status="invoice.medical_document.status" />
                </div>
                <dl class="related-medical-document__details">
                    <div><dt>Código</dt><dd>{{ invoice.medical_document_code || invoice.medical_document.public_code || "No registrado" }}</dd></div>
                    <div><dt>Tipo</dt><dd>{{ documentType(invoice.medical_document_type) }}</dd></div>
                    <div><dt>Paciente</dt><dd>{{ invoice.patient ? `${invoice.patient.first_name} ${invoice.patient.last_name}` : invoice.recipient_name || "No registrado" }}</dd></div>
                    <div><dt>Identidad</dt><dd>{{ invoice.recipient_tax_id || "No registrada" }}</dd></div>
                    <div><dt>Edad</dt><dd>{{ invoice.medical_document.age_at_consultation ?? invoice.recipient_age ?? "No registrada" }}</dd></div>
                    <div><dt>Fecha de atención</dt><dd>{{ serviceDate(invoice.service_date) }}</dd></div>
                    <div><dt>Hora de atención</dt><dd>{{ serviceTime(invoice.service_time) }}</dd></div>
                    <div><dt>Profesional</dt><dd>{{ invoice.service_professional || "No registrado" }}</dd></div>
                    <div><dt>Período de incapacidad</dt><dd>{{ invoice.medical_document.leave_start_date ? `${serviceDate(invoice.medical_document.leave_start_date)} a ${serviceDate(invoice.medical_document.leave_end_date)}` : "No aplica o no registrado" }}</dd></div>
                    <div><dt>Estado</dt><dd>{{ invoice.medical_document.status || "No registrado" }}</dd></div>
                </dl>
                <div class="action-row"><Link class="button button--secondary" :href="route('admin.documents.review', invoice.medical_document.id)">Abrir documento médico</Link><Link v-if="invoice.medical_document.public_code" class="button button--secondary" :href="route('public.verify.lookup', { code: invoice.medical_document.public_code })">Verificar documento médico</Link></div>
            </article>
            <article class="panel table-scroll">
                <h3>Conceptos</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th>Descuento</th>
                            <th>Impuesto</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in invoice.items" :key="item.id">
                            <td>{{ item.description }}</td>
                            <td>{{ item.quantity }}</td>
                            <td>{{ money(item.unit_price) }}</td>
                            <td>{{ money(item.discount) }}</td>
                            <td>{{ item.tax_category }}</td>
                            <td>{{ money(item.total_amount || 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </article>
            <div
                v-if="invoice.status === 'DRAFT'"
                class="panel invoice-actions"
            >
                <div>
                    <h3>Emitir factura</h3>
                    <p>
                        Esta acción asigna y consume el siguiente NCF de la
                        autorización elegida. No puede revertirse.
                    </p>
                    <p>Puede editar este borrador antes de emitir. La relación con el documento médico se conserva.</p>
                    <Link v-if="canUpdate" class="row-action" :href="route('admin.invoices.create', { invoice_id: invoice.id })">Editar borrador</Link>
                </div>
                <template v-if="canIssue">
                    <button
                        class="button button--admin"
                        :disabled="!available.length"
                        @click="issueOpen = true"
                    >
                        Confirmar emisión</button
                    ><div v-if="!available.length" class="fiscal-guidance"><strong>Falta autorización fiscal activa</strong><p>Registre CAI, RTN y rango NCF vigente antes de emitir. El borrador permanece guardado y no consume NCF.</p><Link class="row-action" :href="route('admin.fiscal-authorizations.index')">Configurar autorización fiscal</Link></div>
                </template>
                <span v-else>No tiene permiso para emitir.</span>
            </div>
            <div
                v-if="invoice.status === 'ISSUED'"
                class="panel invoice-actions"
            >
                <div>
                    <h3>Anular factura</h3>
                    <p>
                        La anulación conserva el NCF y deja un registro de
                        auditoría.
                    </p>
                    <p v-if="invoice.medical_document">Para corregir el documento médico, use el documento relacionado. La corrección médica no modifica esta factura emitida.</p>
                </div>
                <button
                    v-if="canVoid"
                    class="button button--danger"
                    @click="voidOpen = true"
                >
                    Anular factura</button
                    ><span v-else>No tiene permiso para anular.</span>
                ><button
                    v-if="canCorrect"
                    class="button button--secondary"
                    @click="correctOpen = true"
                >Corregir factura</button>
            </div>
            <article class="panel">
                <h3>Historial</h3>
                <ol class="invoice-audit">
                    <li v-for="audit in invoice.audits" :key="audit.id">
                        <strong>{{ audit.action }}</strong> ·
                        {{ dateTime(audit.created_at) }}<span v-if="audit.user">
                            por {{ audit.user.name }}</span
                        >
                    </li>
                </ol>
            </article>
            <div v-if="issueOpen" class="modal-backdrop">
                <section class="panel modal-card">
                    <h3>Confirmar emisión</h3>
                    <p>
                        Seleccione la autorización que proveerá el NCF. Esta
                        operación es definitiva.
                    </p>
                    <select v-model="authorizationId">
                        <option value="">
                            Asignación automática (la próxima vigente)
                        </option>
                        <option
                            v-for="item in available"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.ncf_prefix }} · próximo
                            {{ item.next_number }} · vence
                            {{ item.valid_until }}
                        </option>
                    </select>
                    <div class="form-actions">
                        <button
                            class="button button--secondary"
                            @click="issueOpen = false"
                        >
                            Cancelar</button
                        ><button
                            class="button button--admin"
                            :disabled="busy"
                            @click="issue"
                        >
                            {{
                                busy ? "Emitiendo..." : "Emitir definitivamente"
                            }}
                        </button>
                    </div>
                </section>
            </div>
            <div v-if="voidOpen" class="modal-backdrop">
                <section class="panel modal-card">
                    <h3>Anular factura</h3>
                    <p>
                        Indique el motivo para conservar la trazabilidad fiscal.
                    </p>
                    <textarea
                        v-model="voidReason"
                        rows="4"
                        minlength="3"
                        maxlength="2000"
                        placeholder="Motivo de anulación"
                    ></textarea>
                    <div class="form-actions">
                        <button
                            class="button button--secondary"
                            @click="voidOpen = false"
                        >
                            Cancelar</button
                        ><button
                            class="button button--danger"
                            :disabled="busy || voidReason.trim().length < 3"
                            @click="voidInvoice"
                        >
                            {{ busy ? "Anulando..." : "Confirmar anulación" }}
                        </button>
                    </div>
                </section>
            </div>
            <div v-if="correctOpen" class="modal-backdrop">
                <section class="panel modal-card">
                    <h3>Corregir factura emitida</h3>
                    <p>La factura actual se anulará conservando su NCF y se creará un borrador de reemplazo con un NCF nuevo al emitirlo.</p>
                    <textarea v-model="correctionReason" rows="4" minlength="3" maxlength="2000" placeholder="Motivo de corrección"></textarea>
                    <div class="form-actions">
                        <button class="button button--secondary" @click="correctOpen = false">Cancelar</button>
                        <button class="button button--admin" :disabled="busy || correctionReason.trim().length < 3" @click="correctInvoice">{{ busy ? "Corrigiendo..." : "Anular y crear reemplazo" }}</button>
                    </div>
                </section>
            </div>
        </section></AdminLayout
    >
</template>
