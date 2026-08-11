<script setup lang="ts">
import { onUnmounted, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import PublicLayout from "@/Layouts/PublicLayout.vue";
import PageMeta from "@/Components/PageMeta.vue";

type Tab = "code" | "file" | "qr";
type Barcode = { rawValue: string };
type Reader = { detect: (source: HTMLVideoElement) => Promise<Barcode[]> };
type ReaderConstructor = new (options: { formats: string[] }) => Reader;
const props = defineProps<{ initialCode?: string }>();
const tab = ref<Tab>("code");
const video = ref<HTMLVideoElement | null>(null);
const scanning = ref(false);
const scannerError = ref("");
const code = useForm({ code: props.initialCode || "", identity_last4: "" });
const file = useForm<{ document: File | null }>({ document: null });
let stream: MediaStream | undefined;
let scanTimer = 0;
let scannerGeneration = 0;
const stopScanner = () => {
    scannerGeneration += 1;
    window.clearTimeout(scanTimer);
    if (video.value) {
        video.value.pause();
        video.value.srcObject = null;
    }
    stream?.getTracks().forEach((track) => track.stop());
    stream = undefined;
    scanning.value = false;
};
const selectTab = (next: Tab) => {
    if (next !== "qr") stopScanner();
    tab.value = next;
};
const pick = (event: Event) => {
    file.document = (event.target as HTMLInputElement).files?.[0] || null;
};
const acceptQr = (value: string) => {
    try {
        const url = new URL(value);
        if (
            url.origin === window.location.origin &&
            /^\/verificar\/[A-Za-z0-9_-]{43}$/.test(url.pathname)
        ) {
            stopScanner();
            url.searchParams.set("source", "camera");
            window.location.assign(url.href);
            return true;
        }
    } catch {
        if (/^[A-Za-z0-9-]{4,40}$/.test(value)) {
            stopScanner();
            code.code = value;
            tab.value = "code";
            return true;
        }
    }
    return false;
};
const startScanner = async () => {
    scannerError.value = "";
    const Constructor = (
        window as unknown as { BarcodeDetector?: ReaderConstructor }
    ).BarcodeDetector;
    if (!Constructor || !navigator.mediaDevices?.getUserMedia) {
        scannerError.value =
            "Este navegador no ofrece lectura QR por cámara. Use el código o el archivo PDF.";
        return;
    }
    const generation = ++scannerGeneration;
    try {
        const cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: "environment" } },
            audio: false,
        });
        if (generation !== scannerGeneration || !video.value) {
            cameraStream.getTracks().forEach((track) => track.stop());
            return;
        }
        stream = cameraStream;
        video.value.srcObject = cameraStream;
        await video.value.play();
        scanning.value = true;
        const reader = new Constructor({ formats: ["qr_code"] });
        const scan = async () => {
            if (!scanning.value || !video.value || generation !== scannerGeneration) return;
            try {
                const values = await reader.detect(video.value);
                if (values.some((value) => acceptQr(value.rawValue))) return;
            } catch {
                /* Frames desenfocados pueden ser ilegibles. */
            }
            scanTimer = window.setTimeout(scan, 250);
        };
        scanTimer = window.setTimeout(scan, 250);
    } catch {
        scannerError.value =
            "No fue posible acceder a la cámara. Revise el permiso o use otro método.";
        stopScanner();
    }
};
onUnmounted(stopScanner);
</script>

<template>
    <PublicLayout
        ><PageMeta
            title="Verificar documento"
            description="Compruebe el estado de un documento médico en segundos."
            noindex
        />
        <section class="verify-premium">
            <div class="verify-premium__backdrop">
                <img
                    src="/images/photography/document-review-1280.webp"
                    srcset="/images/photography/document-review-640.webp 640w, /images/photography/document-review-1280.webp 1280w"
                    sizes="100vw"
                    width="1280"
                    height="853"
                    alt="Profesional revisando documentación con una paciente"
                    loading="eager"
                    decoding="async"
                    fetchpriority="high"
                />
            </div>
            <div class="container verify-premium__grid">
                <div class="verify-premium__intro">
                    <p class="eyebrow">Confianza digital</p>
                    <h1>Verifique un documento médico.</h1>
                    <p>
                        Confirme su autenticidad por código, QR o huella del
                        PDF.
                    </p>
                    <div class="verify-confidence">
                        <div>
                            <span>✓</span><strong>Consulta segura</strong
                            ><small
                                >Los detalles sensibles requieren segundo
                                factor.</small
                            >
                        </div>
                        <div>
                            <span>24/7</span
                            ><strong>Disponibilidad continua</strong
                            ><small>Registro con fecha y hora exactas.</small>
                        </div>
                    </div>
                </div>
                <div class="verify-premium__card">
                    <div class="verify-card__head">
                        <span>+</span>
                        <div>
                            <strong>Verificación institucional</strong
                            ><small>Elija cómo desea comprobarlo</small>
                        </div>
                        <i class="secure-dot">Seguro</i>
                    </div>
                    <div class="verify-methods" role="tablist">
                        <button
                            :class="{ active: tab === 'code' }"
                            type="button"
                            @click="selectTab('code')"
                        >
                            <b>ABC</b><span>Código</span></button
                        ><button
                            :class="{ active: tab === 'qr' }"
                            type="button"
                            @click="selectTab('qr')"
                        >
                            <b>QR</b><span>Escanear</span></button
                        ><button
                            :class="{ active: tab === 'file' }"
                            type="button"
                            @click="selectTab('file')"
                        >
                            <b>PDF</b><span>Subir archivo</span>
                        </button>
                    </div>
                    <form
                        v-if="tab === 'code'"
                        class="verify-form"
                        @submit.prevent="code.post(route('public.verify.code'))"
                    >
                        <div class="field">
                            <label for="code">Código del documento</label
                            ><input
                                id="code"
                                v-model="code.code"
                                required
                                maxlength="40"
                                autocomplete="off"
                                placeholder="CSA-2026-XXXX"
                            /><span
                                v-if="code.errors.code"
                                class="field-error"
                                >{{ code.errors.code }}</span
                            >
                        </div>
                        <div class="field">
                            <label for="last4"
                                >Últimos 4 dígitos de identidad
                                <i>para detalles sensibles</i></label
                            ><input
                                id="last4"
                                v-model="code.identity_last4"
                                inputmode="numeric"
                                pattern="[0-9]{4}"
                                maxlength="4"
                                autocomplete="off"
                                placeholder="0000"
                            />
                        </div>
                        <button
                            class="button button--primary button--full"
                            :disabled="code.processing"
                        >
                            {{
                                code.processing
                                    ? "Verificando…"
                                    : "Verificar documento →"
                            }}
                        </button>
                    </form>
                    <div v-else-if="tab === 'qr'" class="qr-scanner">
                        <p>
                            La cámara solo acepta enlaces de verificación de
                            este sitio.
                        </p>
                        <div :class="['qr-camera', { active: scanning }]">
                            <video ref="video" playsinline muted></video
                            ><span v-if="!scanning">QR</span><i></i>
                        </div>
                        <p v-if="scannerError" class="field-error">
                            {{ scannerError }}
                        </p>
                        <button
                            v-if="!scanning"
                            class="button button--primary button--full"
                            type="button"
                            @click="startScanner"
                        >
                            Activar cámara</button
                        ><button
                            v-else
                            class="button button--outline button--full"
                            type="button"
                            @click="stopScanner"
                        >
                            Detener cámara
                        </button>
                    </div>
                    <form
                        v-else
                        class="verify-form"
                        @submit.prevent="
                            file.post(route('public.verify.file'), {
                                forceFormData: true,
                            })
                        "
                    >
                        <label class="upload-zone" for="verification-pdf"
                            ><span>PDF</span
                            ><strong>{{
                                file.document?.name || "Seleccionar documento"
                            }}</strong
                            ><small>Se comparará su huella SHA-256.</small
                            ><input
                                id="verification-pdf"
                                type="file"
                                accept="application/pdf,.pdf"
                                required
                                @change="pick" /></label
                        ><span
                            v-if="file.errors.document"
                            class="field-error"
                            >{{ file.errors.document }}</span
                        ><button
                            class="button button--primary button--full"
                            :disabled="file.processing || !file.document"
                        >
                            {{
                                file.processing
                                    ? "Comparando…"
                                    : "Comparar PDF →"
                            }}
                        </button>
                    </form>
                    <small class="verify-privacy"
                        >La consulta queda registrada con fecha, hora y contexto
                        seguro.</small
                    >
                </div>
            </div>
        </section></PublicLayout
    >
</template>
