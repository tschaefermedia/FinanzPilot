<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Password from 'primevue/password';
import Dialog from 'primevue/dialog';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});

const form = useForm({
    ai_provider: props.settings.ai_provider || 'none',
    ai_api_key: '',
    ai_model: props.settings.ai_model || '',
    ai_base_url: props.settings.ai_base_url || '',
});

const providerOptions = [
    { label: 'Deaktiviert', value: 'none' },
    { label: 'Claude (Anthropic)', value: 'claude' },
    { label: 'OpenAI / Kompatibel', value: 'openai' },
    { label: 'Ollama (Lokal)', value: 'ollama' },
];

const defaultModels = {
    claude: 'claude-sonnet-4-5-20250514',
    openai: 'gpt-4o',
    ollama: 'llama3',
};

function onProviderChange() {
    if (defaultModels[form.ai_provider]) {
        form.ai_model = defaultModels[form.ai_provider];
    }
    if (form.ai_provider === 'ollama') {
        form.ai_base_url = 'http://localhost:11434';
    }
}

function submit() {
    form.put('/settings/ai');
}

const showClearDialog = ref(false);
const clearConfirmation = ref('');
const clearProcessing = ref(false);

function clearAll() {
    clearProcessing.value = true;
    router.delete('/settings/clear-all', {
        onFinish: () => {
            clearProcessing.value = false;
            showClearDialog.value = false;
            clearConfirmation.value = '';
        },
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Einstellungen" />

        <div class="max-w-2xl">
            <!-- AI Configuration -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">KI-Konfiguration</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Konfiguriere die KI für automatische Finanzanalysen auf dem Dashboard.</p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Anbieter</label>
                        <Select
                            v-model="form.ai_provider"
                            :options="providerOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            @change="onProviderChange"
                        />
                    </div>

                    <template v-if="form.ai_provider !== 'none'">
                        <div v-if="form.ai_provider !== 'ollama'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">API-Schlüssel</label>
                            <Password
                                v-model="form.ai_api_key"
                                class="w-full"
                                :feedback="false"
                                toggleMask
                                :placeholder="settings.ai_api_key_set ? '••••••••• (gesetzt)' : 'API-Schlüssel eingeben'"
                            />
                            <small class="text-gray-400 dark:text-gray-500">Leer lassen, um den vorhandenen Schlüssel beizubehalten.</small>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Modell</label>
                            <InputText v-model="form.ai_model" class="w-full" :placeholder="defaultModels[form.ai_provider] || 'Modellname'" />
                        </div>

                        <div v-if="form.ai_provider !== 'claude'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">API-URL</label>
                            <InputText v-model="form.ai_base_url" class="w-full" placeholder="http://localhost:11434" />
                        </div>
                    </template>

                    <div class="pt-2">
                        <Button type="submit" label="Speichern" icon="pi pi-check" :loading="form.processing" />
                    </div>
                </form>
            </div>
            <!-- Danger Zone -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-red-200 dark:border-red-800 p-6 mt-6">
                <h3 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-1">Gefahrenzone</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Alle Finanzdaten unwiderruflich löschen: Buchungen, Konten, Kategorien, Darlehen, Importe und Daueraufträge.</p>
                <Button label="Alle Daten löschen" icon="pi pi-trash" severity="danger" outlined @click="showClearDialog = true" />
            </div>

            <Dialog v-model:visible="showClearDialog" header="Alle Daten löschen?" modal :style="{ width: '28rem' }">
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Diese Aktion löscht <strong>alle Finanzdaten unwiderruflich</strong>. Einstellungen und Import-Mappings bleiben erhalten.
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">Tippe <strong>LÖSCHEN</strong> zur Bestätigung:</p>
                <InputText v-model="clearConfirmation" class="w-full" placeholder="LÖSCHEN" />
                <template #footer>
                    <Button label="Abbrechen" text @click="showClearDialog = false; clearConfirmation = ''" />
                    <Button label="Endgültig löschen" icon="pi pi-trash" severity="danger" :disabled="clearConfirmation !== 'LÖSCHEN'" :loading="clearProcessing" @click="clearAll" />
                </template>
            </Dialog>
        </div>
    </AppLayout>
</template>
