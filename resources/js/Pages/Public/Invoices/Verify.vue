<script setup lang="ts">
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import PageMeta from "@/Components/PageMeta.vue";
import PublicLayout from "@/Layouts/PublicLayout.vue";

type Invoice = {
    ncf: string | null;
    status: string;
    issued_at: string | null;
    subtotal: string;
    tax_total: string;
    total: string;
    currency: string;
    cai: string | null;
    issuer_rtn: string | null;
    emission_deadline: string | null;
    authorized_range: [number, number] | null;
    medical_document_code: string | null;
    service_date: string | null;
    service_time: string | null;
    hash: string | null;
    verified_at: string;
    method: string;
};

const props = defineProps<{ invoice: Invoice }>();
const valid = computed(() => props.invoice.status === "ISSUED");
const title = computed(() =>
    valid.value ? "Factura institucional verificada" : "Factura anulada",
);
const statusLabel: Record<string, string> = {
    ISSUED: "Emitida",
    VOID: "Anulada",
};
const money = (value: string) =>
    new Intl.NumberFormat("es-HN", {
        style: "currency",
        currency: props.invoice.currency || "HNL",
    }).format(Number(value));
const dateTime = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", {
              dateStyle: "long",
              timeStyle: "medium",
              timeZone: "America/Tegucigalpa",
          }).format(new Date(value))
        : "No disponible";
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", {
              dateStyle: "long",
              timeZone: "UTC",
          }).format(new Date(`${value}T12:00:00Z`))
        : "No disponible";
const time = (value: string | null) => {
    if (!value) return "No disponible";
    const [hours, minutes] = value.split(":").map(Number);
    return `${hours % 12 || 12}:${String(minutes).padStart(2, "0")} ${hours < 12 ? "a. m." : "p. m."}`;
};
</script>

<template>
    <PublicLayout>
        <PageMeta :title="title" noindex />
        <section class="authentic-result">
            <div class="container">
                <header :class="['authentic-result__hero', { invalid: !valid }]">
                    <span aria-hidden="true">{{ valid ? "✓" : "!" }}</span>
                    <div>
                        <p>Verificación fiscal institucional</p>
                        <h1>{{ title }}</h1>
                        <strong>Estado actual: {{ statusLabel[invoice.status] || invoice.status }}</strong>
                    </div>
                </header>

                <div class="verification-dossier invoice-verification">
                    <section class="verification-section verification-status-grid">
                        <div><small>NCF</small><strong>{{ invoice.ncf || "No disponible" }}</strong></div>
                        <div><small>Emisión fiscal</small><strong>{{ dateTime(invoice.issued_at) }}</strong></div>
                        <div><small>Fecha de servicio</small><strong>{{ date(invoice.service_date) }}</strong></div>
                        <div><small>Hora de servicio</small><strong>{{ time(invoice.service_time) }}</strong></div>
                        <div><small>Validación</small><strong>{{ dateTime(invoice.verified_at) }}</strong></div>
                        <div><small>Método</small><strong>Enlace QR</strong></div>
                    </section>

                    <div class="verification-dossier__grid">
                        <main>
                            <section class="verification-section">
                                <p class="kicker">Importes registrados</p>
                                <dl>
                                    <div><dt>Subtotal</dt><dd>{{ money(invoice.subtotal) }}</dd></div>
                                    <div><dt>Impuestos</dt><dd>{{ money(invoice.tax_total) }}</dd></div>
                                    <div class="invoice-verification__total"><dt>Total</dt><dd>{{ money(invoice.total) }}</dd></div>
                                </dl>
                            </section>
                            <section class="verification-section">
                                <p class="kicker">Autorización fiscal</p>
                                <dl>
                                    <div><dt>CAI</dt><dd class="code-wrap">{{ invoice.cai || "No disponible" }}</dd></div>
                                    <div><dt>RTN emisor</dt><dd>{{ invoice.issuer_rtn || "No disponible" }}</dd></div>
                                    <div><dt>Fecha límite de emisión</dt><dd>{{ date(invoice.emission_deadline) }}</dd></div>
                                    <div><dt>Rango autorizado</dt><dd>{{ invoice.authorized_range ? `${invoice.authorized_range[0]} - ${invoice.authorized_range[1]}` : "No disponible" }}</dd></div>
                                </dl>
                            </section>
                            <section class="verification-section">
                                <p class="kicker">Integridad del archivo</p>
                                <div class="document-fingerprint">
                                    <small>Huella SHA-256 del PDF emitido</small>
                                    <code>{{ invoice.hash || "No disponible" }}</code>
                                </div>
                            </section>
                        </main>

                        <aside>
                            <section class="verification-section clinic-verification-card">
                                <p class="kicker">Alcance</p>
                                <h2>Registro institucional</h2>
                                <p>Esta consulta confirma los datos registrados por Clínica Médica Santa Ana. No sustituye el validador fiscal oficial.</p>
                            </section>
                            <section v-if="invoice.medical_document_code" class="verification-section">
                                <p class="kicker">Documento relacionado</p>
                                <h2>Verificación médica</h2>
                                <p>Código público: <strong>{{ invoice.medical_document_code }}</strong></p>
                                <p>El documento clínico conserva su propio proceso de verificación y protección de datos.</p>
                                <Link
                                    class="button button--outline button--full"
                                    :href="route('public.verify.lookup', { code: invoice.medical_document_code })"
                                >Verificar documento médico</Link>
                            </section>
                            <Link class="button button--outline button--full" :href="route('public.home')">Ir al sitio institucional</Link>
                        </aside>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
