<script setup>
import { useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">FinanzPilot</h1>
                <p class="text-sm text-gray-500 mt-1">Anmelden</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">E-Mail</label>
                        <InputText
                            v-model="form.email"
                            type="email"
                            class="w-full"
                            placeholder="admin@finanzpilot.local"
                            autofocus
                        />
                        <small v-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
                        <Password
                            v-model="form.password"
                            class="w-full"
                            :feedback="false"
                            toggleMask
                            inputClass="w-full"
                        />
                        <small v-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.remember" :binary="true" inputId="remember" />
                        <label for="remember" class="text-sm text-gray-600">Angemeldet bleiben</label>
                    </div>

                    <Button
                        type="submit"
                        label="Anmelden"
                        icon="pi pi-sign-in"
                        class="w-full"
                        :loading="form.processing"
                    />
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                Standard: admin@finanzpilot.local / finanzpilot
            </p>
        </div>
    </div>
</template>
