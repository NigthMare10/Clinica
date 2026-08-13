<script setup lang="ts">
import { computed } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";

type History = { event: string; at?: string; method?: string; result?: string };
type PublicDoc = {
    code: string;
    type: string;
    status: string;
    issued_at?: string;
    consultation_date?: string;
    consultation_time?: string;
    patient?: {
        name?: string | null;
        identity?: string | null;
        age?: number | null;
    } | null;
    diagnosis?: string | null;
    reason?: string | null;
    observations?: string | null;
    provider: {
        name: string;
        credential_type: string;
        credential_number: string;
    };
    leave: {
        start?: string | null;
        end?: string | null;
        days?: number | null;
        return_date?: string | null;
    };
    clinic: { name: string; address: string; phone: string; hours: string };
    verification: {
        method: string;
        verified_at: string;
        hash?: string | null;
        details_verified: boolean;
    };
    security: {
        pdf_encrypted: boolean;
        qr_verified: boolean;
        institutional_registry: boolean;
        active_audit: boolean;
    };
    history: History[];
    replacement_code?: string | null;
};

const props = defineProps<{
    status:
        | "VALID"
        | "REVOKED"
        | "REPLACED"
        | "NOT_FOUND"
        | "NOT_ISSUED"
        | string;
    document: PublicDoc | null;
    challenge?: { method: "token" | "code"; code?: string; source?: string };
}>();
const unlock = useForm({
    code: props.challenge?.code || props.document?.code || "",
    identity_last4: "",
    source: props.challenge?.source || "MANUAL_CODE",
});
const valid = computed(() => props.status === "VALID");
const title = computed(
    () =>
        ({
            VALID: "Documento auténtico",
            REVOKED: "Documento anulado",
            REPLACED: "Documento reemplazado",
            NOT_ISSUED: "Documento no emitido",
            NOT_FOUND: "Documento no encontrado",
        })[props.status] || "Documento no encontrado",
);
const statusLabel: Record<string, string> = {
    VALID: "Válido",
    REVOKED: "Anulado",
    REPLACED: "Reemplazado",
    NOT_ISSUED: "No emitido",
};
const methodName: Record<string, string> = {
    QR_CAMERA: "Cámara QR",
    QR_LINK: "Enlace QR",
    MANUAL_CODE: "Código manual",
    PDF_HASH: "Huella del PDF",
    ADMIN: "Administración",
};
const eventName: Record<string, string> = {
    ISSUED: "Emitido",
    VERIFIED: "Verificado",
    REVALIDATED: "Revalidado",
    REVOKED: "Anulado",
    REPLACED: "Reemplazado",
};
const dateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", {
              dateStyle: "long",
              timeStyle: "medium",
              timeZone: "America/Tegucigalpa",
          }).format(new Date(value))
        : "No disponible";
const date = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", {
              dateStyle: "long",
              timeZone: "UTC",
          }).format(new Date(`${value}T12:00:00Z`))
        : "No disponible";
const consultationTime = (value?: string | null) =>
    (() => {
        if (!value) return "No especificada";
        const [hours, minutes] = value.split(":").map(Number);
        const period = hours < 12 ? "a. m." : "p. m.";

        return `${hours % 12 || 12}:${String(minutes).padStart(2, "0")} ${period}`;
    })();
const verifyDetails = () => {
    if (props.challenge?.method === "token")
        unlock.get(window.location.pathname, { preserveScroll: true });
    else unlock.post(route("public.verify.code"), { preserveScroll: true });
};
</script>

<template>
    <PublicLayout>
        <PageMeta :title="title" noindex />
        <section class="authentic-result">
            <div class="container">
                <header
                    :class="['authentic-result__hero', { invalid: !valid }]"
                >
                    <span aria-hidden="true">{{ valid ? "✓" : "!" }}</span>
                    <div>
                        <p>Verificación institucional</p>
                        <h1>{{ title }}</h1>
                        <strong
                            >Estado actual:
                            {{ statusLabel[status] || status }}</strong
                        >
                    </div>
                </header>

                <div v-if="document" class="verification-dossier">
                    <section
                        class="verification-section verification-status-grid"
                    >
                        <div>
                            <small>Código</small
                            ><strong>{{ document.code }}</strong>
                        </div>
                        <div>
                            <small>Tipo</small
                            ><strong>{{ document.type }}</strong>
                        </div>
                        <div><small>Fecha de emisión</small><strong>{{ date(document.issued_at?.slice(0, 10)) }}</strong></div>
                        <div>
                            <small>Validación exacta</small
                            ><strong>{{
                                dateTime(document.verification.verified_at)
                            }}</strong>
                        </div>
                    </section>

                    <section
                        v-if="!document.verification.details_verified"
                        class="verification-section identity-gate"
                    >
                        <div>
                            <p class="kicker">Protección de datos clínicos</p>
                            <h2>Autorice los detalles del paciente</h2>
                            <p>
                                Ingrese los últimos cuatro dígitos de la
                                identidad para consultar nombre, identidad,
                                diagnóstico y observaciones.
                            </p>
                        </div>
                        <form @submit.prevent="verifyDetails">
                            <label for="identity_last4">Últimos 4 dígitos</label
                            ><input
                                id="identity_last4"
                                v-model="unlock.identity_last4"
                                required
                                inputmode="numeric"
                                pattern="[0-9]{4}"
                                maxlength="4"
                                autocomplete="off"
                                placeholder="0000"
                            /><button
                                class="button button--primary"
                                :disabled="unlock.processing"
                            >
                                {{
                                    unlock.processing
                                        ? "Validando…"
                                        : "Autorizar detalles"
                                }}
                            </button>
                        </form>
                    </section>

                    <div class="verification-dossier__grid">
                        <main>
                            <section class="verification-section">
                                <p class="kicker">Paciente</p>
                                <dl>
                                    <div>
                                        <dt>Nombre completo</dt>
                                        <dd>
                                            {{
                                                document.patient?.name ||
                                                "Protegido por segundo factor"
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Identidad</dt>
                                        <dd>
                                            {{
                                                document.patient?.identity ||
                                                "No registrada"
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Edad al consultar</dt>
                                        <dd>
                                            {{
                                                document.patient?.age ??
                                                "No registrada"
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="verification-section">
                                <p class="kicker">Atención médica</p>
                                <dl>
                                    <div>
                                        <dt>Fecha de consulta</dt>
                                        <dd>
                                            {{
                                                date(document.consultation_date)
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Hora de consulta</dt>
                                        <dd>
                                            {{
                                                consultationTime(document.consultation_time)
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Profesional</dt>
                                        <dd>{{ document.provider.name }}</dd>
                                    </div>
                                    <div>
                                        <dt>
                                            {{
                                                document.provider
                                                    .credential_type
                                            }}
                                        </dt>
                                        <dd>
                                            {{
                                                document.provider
                                                    .credential_number
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Clínica emisora</dt>
                                        <dd>{{ document.clinic.name }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="verification-section">
                                <p class="kicker">Diagnóstico y motivo</p>
                                <template
                                    v-if="
                                        document.verification.details_verified
                                    "
                                    ><dl>
                                        <div>
                                            <dt>Diagnóstico autorizado</dt>
                                            <dd>
                                                {{
                                                    document.diagnosis ||
                                                    "No consignado"
                                                }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt>Motivo o constancia</dt>
                                            <dd>
                                                {{
                                                    document.reason ||
                                                    document.type
                                                }}
                                            </dd>
                                        </div>
                                        <div v-if="document.observations">
                                            <dt>Observaciones relevantes</dt>
                                            <dd>{{ document.observations }}</dd>
                                        </div>
                                    </dl></template
                                >
                                <p v-else class="protected-copy">
                                    Información clínica protegida. Autorice los
                                    detalles para consultarla.
                                </p>
                            </section>

                            <section
                                v-if="document.leave?.start"
                                class="verification-section"
                            >
                                <p class="kicker">Incapacidad</p>
                                <dl>
                                    <div>
                                        <dt>Inicio</dt>
                                        <dd>
                                            {{ date(document.leave.start) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Final</dt>
                                        <dd>{{ date(document.leave.end) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Número de días</dt>
                                        <dd>{{ document.leave.days }}</dd>
                                    </div>
                                    <div>
                                        <dt>Reincorporación</dt>
                                        <dd>
                                            {{
                                                date(document.leave.return_date)
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="verification-section">
                                <p class="kicker">Trazabilidad</p>
                                <dl>
                                    <div>
                                        <dt>Método de esta validación</dt>
                                        <dd>
                                            {{
                                                methodName[
                                                    document.verification.method
                                                ] ||
                                                document.verification.method
                                            }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Estado actual</dt>
                                        <dd>
                                            {{ statusLabel[status] || status }}
                                        </dd>
                                    </div>
                                </dl>
                                <div class="document-fingerprint">
                                    <small>Huella SHA-256 del documento</small
                                    ><code>{{
                                        document.verification.hash ||
                                        "No disponible"
                                    }}</code>
                                </div>
                                <ol class="traceability-list">
                                    <li
                                        v-for="entry in document.history"
                                        :key="`${entry.event}-${entry.at}`"
                                    >
                                        <span></span>
                                        <div>
                                            <strong>{{
                                                eventName[entry.event] ||
                                                entry.event
                                            }}</strong
                                            ><small
                                                >{{
                                                    entry.method
                                                        ? `${methodName[entry.method] || entry.method} · `
                                                        : ""
                                                }}{{
                                                    dateTime(entry.at)
                                                }}</small
                                            >
                                        </div>
                                    </li>
                                </ol>
                            </section>
                        </main>

                        <aside>
                            <section
                                class="verification-section clinic-verification-card"
                            >
                                <p class="kicker">Clínica emisora</p>
                                <h2>{{ document.clinic.name }}</h2>
                                <p class="institution-address">
                                    {{ document.clinic.address }}
                                </p>
                                <a
                                    :href="`tel:${document.clinic.phone.replace(/[^+\d]/g, '')}`"
                                    >{{ document.clinic.phone }}</a
                                ><strong>{{ document.clinic.hours }}</strong>
                            </section>
                            <section class="verification-section">
                                <p class="kicker">Seguridad documental</p>
                                <div class="security-grid">
                                    <div
                                        :class="{
                                            active: document.security
                                                .pdf_encrypted,
                                        }"
                                    >
                                        <span>✓</span>
                                        <p>
                                            <strong>PDF cifrado</strong
                                            ><small
                                                >Protección con contraseña
                                                institucional</small
                                            >
                                        </p>
                                    </div>
                                    <div
                                        :class="{
                                            active: document.security
                                                .qr_verified,
                                        }"
                                    >
                                        <span>✓</span>
                                        <p>
                                            <strong>QR verificable</strong
                                            ><small
                                                >Enlace institucional
                                                comprobado</small
                                            >
                                        </p>
                                    </div>
                                    <div
                                        :class="{
                                            active: document.security
                                                .institutional_registry,
                                        }"
                                    >
                                        <span>✓</span>
                                        <p>
                                            <strong
                                                >Registro institucional</strong
                                            ><small
                                                >Emisión vinculada al
                                                sistema</small
                                            >
                                        </p>
                                    </div>
                                    <div
                                        :class="{
                                            active: document.security
                                                .active_audit,
                                        }"
                                    >
                                        <span>✓</span>
                                        <p>
                                            <strong>Auditoría activa</strong
                                            ><small
                                                >Eventos con fecha y
                                                contexto</small
                                            >
                                        </p>
                                    </div>
                                </div>
                            </section>
                            <section
                                v-if="document.replacement_code"
                                class="verification-section replacement-notice"
                            >
                                <p class="kicker">Documento sustituto</p>
                                <strong>{{ document.replacement_code }}</strong>
                            </section>
                            <Link
                                class="button button--outline button--full"
                                :href="route('public.verify.lookup')"
                                >Realizar otra verificación</Link
                            >
                        </aside>
                    </div>
                </div>

                <section v-else class="verification-not-found">
                    <h2>No existe una emisión coincidente</h2>
                    <p>Revise el código o utilice el archivo PDF original.</p>
                    <Link
                        class="button button--primary"
                        :href="route('public.verify.lookup')"
                        >Intentar de nuevo</Link
                    >
                </section>
            </div>
        </section>
    </PublicLayout>
</template>
