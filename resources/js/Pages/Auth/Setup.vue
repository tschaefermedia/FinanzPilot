<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/setup', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <GuestLayout title="Einrichtung" subtitle="Lege das Benutzerkonto an, mit dem du dich künftig anmeldest.">
        <Head title="Einrichtung" />
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Name</label>
                <InputText id="name" v-model="form.name" autocomplete="name" autofocus class="w-full" />
                <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">E-Mail</label>
                <InputText id="email" v-model="form.email" type="email" autocomplete="username" class="w-full" />
                <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Passwort</label>
                <Password inputId="password" v-model="form.password" :feedback="false" toggleMask autocomplete="new-password" class="w-full" inputClass="w-full" />
                <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                <small v-else class="text-gray-400 dark:text-gray-500">Mindestens 8 Zeichen.</small>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Passwort bestätigen</label>
                <Password inputId="password_confirmation" v-model="form.password_confirmation" :feedback="false" toggleMask autocomplete="new-password" class="w-full" inputClass="w-full" />
            </div>
            <Button type="submit" label="Konto anlegen" icon="pi pi-check" class="w-full" :loading="form.processing" />
        </form>
    </GuestLayout>
</template>
