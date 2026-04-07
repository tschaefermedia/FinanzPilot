<script setup>
import { ref, onMounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import { useCsrfFetch } from '@/Composables/useCsrfFetch.js';

const { csrfFetch } = useCsrfFetch();

const loading = ref(true);
const error = ref(null);
const enabled = ref(false);
const structured = ref(null);
const legacyInsights = ref(null);

async function fetchInsights() {
    loading.value = true;
    error.value = null;

    try {
        // Try structured endpoint first
        const response = await csrfFetch('/api/ai/insights', { method: 'GET' });
        const data = await response.json();

        enabled.value = data.enabled;

        if (data.enabled && data.structured) {
            structured.value = data.structured;
        } else if (data.enabled && data.raw) {
            legacyInsights.value = data.raw;
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

const healthColor = computed(() => {
    if (!structured.value) return '#9ca3af';
    const score = structured.value.healthScore;
    if (score >= 80) return '#22c55e';
    if (score >= 60) return '#3b82f6';
    if (score >= 40) return '#f59e0b';
    return '#ef4444';
});

const topRecommendation = computed(() => {
    if (!structured.value?.recommendations?.length) return null;
    return structured.value.recommendations[0];
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                <i class="pi pi-sparkles text-purple-500 mr-1"></i>
                KI-Analyse
            </h3>
            <Link href="/ai" class="text-xs text-blue-500 hover:underline">
                Vollständige Analyse →
            </Link>
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

        <template v-else-if="structured">
            <!-- Compact: Health score + top recommendation -->
            <div class="flex items-center gap-4 mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-2xl font-bold" :style="{ color: healthColor }">{{ structured.healthScore }}</span>
                    <span class="text-xs text-gray-400">/100</span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 flex-1">{{ structured.summary }}</p>
            </div>

            <div v-if="topRecommendation" class="bg-purple-50 dark:bg-purple-900/20 rounded-lg px-3 py-2">
                <p class="text-xs font-medium text-purple-700 dark:text-purple-400">
                    <i class="pi pi-lightbulb mr-1"></i>{{ topRecommendation.title }}
                </p>
                <p class="text-xs text-purple-600 dark:text-purple-300 mt-0.5">{{ topRecommendation.detail }}</p>
            </div>
        </template>

        <template v-else-if="legacyInsights">
            <p class="text-sm text-gray-600 dark:text-gray-300 line-clamp-3">{{ legacyInsights }}</p>
        </template>
    </div>
</template>
