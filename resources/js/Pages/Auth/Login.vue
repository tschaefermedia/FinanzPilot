<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <GuestLayout title="Anmelden">
        <Head title="Anmelden" />
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">E-Mail</label>
                <InputText id="email" v-model="form.email" type="email" autocomplete="username" autofocus class="w-full" />
                <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Passwort</label>
                <Password inputId="password" v-model="form.password" :feedback="false" toggleMask autocomplete="current-password" class="w-full" inputClass="w-full" />
                <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
            </div>
            <div class="flex items-center gap-2">
                <Checkbox inputId="remember" v-model="form.remember" :binary="true" />
                <label for="remember" class="text-sm text-gray-600 dark:text-gray-300">Angemeldet bleiben</label>
            </div>
            <Button type="submit" label="Anmelden" icon="pi pi-sign-in" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
