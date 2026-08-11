<script setup lang="ts">
import axios from "axios";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";
import StatusBadge from "@/Components/StatusBadge.vue";
const props = defineProps<{
    invoice: any;
    authorizations: any[];
    canIssue: boolean;
    canVoid: boolean;
}>();
const issueOpen = ref(false);
const voidOpen = ref(false);
const authorizationId = ref("");
const voidReason = ref("");
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
                            new Date(invoice.created_at).toLocaleString("es-HN")
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
                                    invoice.issued_at
                                        ? new Date(
                                              invoice.issued_at,
                                          ).toLocaleString("es-HN")
                                        : "Pendiente"
                                }}
                            </dd>
                        </div>
                        <div v-if="invoice.voided_at">
                            <dt>Anulada</dt>
                            <dd>
                                {{
                                    new Date(invoice.voided_at).toLocaleString(
                                        "es-HN",
                                    )
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
                </div>
                <button
                    v-if="canIssue"
                    class="button button--admin"
                    :disabled="!available.length"
                    @click="issueOpen = true"
                >
                    Confirmar emisión</button
                ><span v-else>No tiene permiso para emitir.</span>
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
                </div>
                <button
                    v-if="canVoid"
                    class="button button--danger"
                    @click="voidOpen = true"
                >
                    Anular factura</button
                ><span v-else>No tiene permiso para anular.</span>
            </div>
            <article class="panel">
                <h3>Historial</h3>
                <ol class="invoice-audit">
                    <li v-for="audit in invoice.audits" :key="audit.id">
                        <strong>{{ audit.action }}</strong> ·
                        {{ new Date(audit.created_at).toLocaleString("es-HN")
                        }}<span v-if="audit.user">
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
        </section></AdminLayout
    >
</template>
