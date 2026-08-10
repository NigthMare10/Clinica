<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{ canResetPassword?: boolean; status?: string }>();
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <GuestLayout>
        <Head title="Acceso profesional"><meta name="robots" content="noindex,nofollow"></Head>
        <div class="login-heading"><p class="kicker">Portal administrativo</p><h2>Bienvenido</h2><p class="auth-subtitle">Ingresa para gestionar la operación clínica.</p></div>
        <div v-if="status" class="flash" role="status">{{ status }}</div>
        <form class="auth-form" @submit.prevent="submit"><div class="field"><label for="email">Correo electrónico</label><input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" placeholder="nombre@clinicasantaana.hn"><InputError :message="form.errors.email" /></div><div class="field"><div class="label-row"><label for="password">Contraseña</label><Link v-if="canResetPassword" :href="route('password.request')">¿Olvidaste tu contraseña?</Link></div><input id="password" v-model="form.password" type="password" required autocomplete="current-password" placeholder="Ingresa tu contraseña"><InputError :message="form.errors.password" /></div><label class="remember"><Checkbox name="remember" v-model:checked="form.remember" /><span>Mantener sesión iniciada</span></label><button class="button button--admin button--full login-submit" :disabled="form.processing">{{ form.processing ? 'Ingresando...' : 'Entrar al panel' }} <span>→</span></button></form><p class="auth-help"><span>●</span> Acceso auditado y protegido</p>
    </GuestLayout>
</template>
