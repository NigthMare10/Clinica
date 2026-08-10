<script setup lang="ts">
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";
import Pagination from "@/Components/Pagination.vue";
import { router } from "@inertiajs/vue3";
import { reactive } from "vue";

type Log = {
    id: string;
    method: string;
    result: string;
    successful: boolean;
    verified_at?: string;
    created_at: string;
    ip_address?: string;
    user_agent?: string;
    context?: Record<string, string>;
    document?: {
        public_code?: string;
        certificate_kind?: string;
        patient?: { first_name: string; last_name: string };
    };
};
type Page<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};
const props = defineProps<{
    logs: Page<Log>;
    stats: {
        today: number;
        qr: number;
        code: number;
        pdf: number;
        valid: number;
        failed: number;
        latest?: string | null;
    };
    trend: Array<{ label: string; count: number }>;
    filters: { period: string; method: string; result: string };
    timezone: string;
}>();
const filterState = reactive({ ...props.filters });
const applyFilters = () => router.get(route('admin.verifications.index'), filterState, { preserveState: true, replace: true });
const clearFilters = () => { filterState.period = 'all'; filterState.method = ''; filterState.result = ''; applyFilters(); };
const dateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat("es-HN", {
              dateStyle: "long",
              timeStyle: "medium",
              timeZone: props.timezone,
          }).format(new Date(value))
        : "Sin registros";
const method: Record<string, string> = {
    QR_CAMERA: "QR cámara",
    QR_LINK: "Enlace QR",
    MANUAL_CODE: "Código",
    PDF_HASH: "PDF",
    ADMIN: "Admin",
};
const max = Math.max(1, ...props.trend.map((item) => item.count));
</script>

<template>
    <AdminLayout title="Verificaciones" eyebrow="Seguridad documental"
        ><PageMeta title="Verificaciones" noindex />
        <section class="admin-content verification-admin">
            <div class="content-toolbar">
                <div>
                    <p class="kicker">Trazabilidad exacta</p>
                    <h2>Historial de verificaciones</h2>
                    <p>Zona horaria: {{ timezone }}</p>
                </div>
                <span class="privacy-chip">IP pseudonimizada</span>
            </div>
            <div class="verification-metrics">
                <article>
                    <span>Hoy</span><strong>{{ stats.today }}</strong>
                </article>
                <article>
                    <span>Por QR</span><strong>{{ stats.qr }}</strong>
                </article>
                <article>
                    <span>Por código</span><strong>{{ stats.code }}</strong>
                </article>
                <article>
                    <span>Por PDF</span><strong>{{ stats.pdf }}</strong>
                </article>
                <article>
                    <span>Válidas</span><strong>{{ stats.valid }}</strong>
                </article>
                <article>
                    <span>Fallidas</span><strong>{{ stats.failed }}</strong>
                </article>
            </div>
            <form class="panel verification-filters" @submit.prevent="applyFilters">
                <label><span>Periodo</span><select v-model="filterState.period"><option value="all">Todo el historial</option><option value="today">Hoy</option><option value="7days">Últimos 7 días</option></select></label>
                <label><span>Método</span><select v-model="filterState.method"><option value="">Todos</option><option value="QR_CAMERA">QR cámara</option><option value="QR_LINK">Enlace QR</option><option value="MANUAL_CODE">Código</option><option value="PDF_HASH">PDF</option></select></label>
                <label><span>Resultado</span><select v-model="filterState.result"><option value="">Todos</option><option value="VALID">Válido</option><option value="REVOKED">Anulado</option><option value="REPLACED">Reemplazado</option><option value="NOT_FOUND">No encontrado</option><option value="NOT_ISSUED">No emitido</option></select></label>
                <button class="button button--primary" type="submit">Aplicar filtros</button>
                <button class="button button--outline" type="button" @click="clearFilters">Limpiar</button>
            </form>
            <div class="panel verification-chart">
                <div>
                    <p class="kicker">Últimos 7 días</p>
                    <h3>Actividad de validación</h3>
                    <small>Última: {{ dateTime(stats.latest) }}</small>
                </div>
                <div class="verification-bars">
                    <div v-for="item in trend" :key="item.label">
                        <span
                            :style="{
                                height: `${Math.max(6, (item.count / max) * 100)}%`,
                            }"
                        ></span
                        ><b>{{ item.count }}</b
                        ><small>{{ item.label }}</small>
                    </div>
                </div>
            </div>
            <div class="panel table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Documento</th>
                            <th>Paciente</th>
                            <th>Código</th>
                            <th>Método</th>
                            <th>Resultado</th>
                            <th>Contexto seguro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id">
                            <td>
                                {{
                                    dateTime(log.verified_at || log.created_at)
                                }}
                            </td>
                            <td>
                                {{
                                    log.document?.certificate_kind ||
                                    "Consulta sin coincidencia"
                                }}
                            </td>
                            <td>
                                {{
                                    log.document?.patient
                                        ? `${log.document.patient.first_name} ${log.document.patient.last_name}`
                                        : "No identificado"
                                }}
                            </td>
                            <td>
                                <code>{{
                                    log.document?.public_code || "—"
                                }}</code>
                            </td>
                            <td>{{ method[log.method] || log.method }}</td>
                            <td>
                                <span
                                    :class="[
                                        'verification-result',
                                        { 'is-valid': log.result === 'VALID' },
                                    ]"
                                    >{{ log.result }}</span
                                >
                            </td>
                            <td>
                                <details class="verification-context"><summary>Ver detalle</summary><small>{{ log.ip_address ? `Huella IP ${log.ip_address.slice(0, 16)}…` : "Sin IP" }}</small><small>{{ log.context?.timezone || timezone }}</small><small>{{ log.user_agent || 'Sin agente registrado' }}</small></details>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination :links="logs.links" />
            </div></section
    ></AdminLayout>
</template>
