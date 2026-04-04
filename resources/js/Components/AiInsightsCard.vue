<script setup>
import { ref, onMounted } from 'vue';
import Button from 'primevue/button';
import { useCsrfFetch } from '@/Composables/useCsrfFetch.js';

const { csrfFetch } = useCsrfFetch();

const insights = ref(null);
const loading = ref(true);
const error = ref(null);
const enabled = ref(false);

async function fetchInsights(refresh = false) {
    loading.value = true;
    error.value = null;

    try {
        const url = refresh ? '/api/insights/refresh' : '/api/insights';
        const method = refresh ? 'POST' : 'GET';

        const response = await csrfFetch(url, { method });
        const data = await response.json();

        enabled.value = data.enabled;

        if (data.enabled && data.insights) {
            insights.value = data.insights;
        } else if (data.error) {
            error.value = data.error;
        } else if (!data.enabled) {
            error.value = data.message || 'KI nicht konfiguriert.';
        }
    } catch (e) {
        error.value = 'Fehler beim Laden der KI-Analyse.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchInsights());

function formatInsights(text) {
    return text
        .replace(/\*\*(.*?)\*\*/g, '$1')
        .replace(/^- /gm, '• ');
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                <i class="pi pi-sparkles text-purple-500 mr-1"></i>
                KI-Analyse
            </h3>
            <Button
                v-if="enabled && !loading"
                icon="pi pi-refresh"
                text
                rounded
                size="small"
                @click="fetchInsights(true)"
                title="Aktualisieren"
            />
        </div>

        <div v-if="loading" class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 py-4">
            <i class="pi pi-spin pi-spinner"></i>
            <span>Analyse wird geladen...</span>
        </div>

        <div v-else-if="error" class="text-sm text-gray-400 dark:text-gray-500 py-4">
            <p>{{ error }}</p>
            <a v-if="!enabled" href="/settings" class="text-blue-500 hover:underline text-xs mt-2 inline-block">
                Zu den Einstellungen →
            </a>
        </div>

        <div v-else-if="insights" class="prose prose-sm max-w-none text-gray-700 dark:text-gray-200">
            <pre class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200 font-sans leading-relaxed">{{ formatInsights(insights) }}</pre>
        </div>
    </div>
</template>
