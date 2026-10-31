<script setup>
import { useTheme } from '@/Composables/useTheme.js';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});

useTheme();
</script>

<template>
    <main class="min-h-screen bg-gray-50 dark:bg-gray-900 flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <p class="text-center text-xl font-bold text-gray-900 dark:text-white tracking-tight mb-6">FinanzPilot</p>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">{{ title }}</h1>
                <p v-if="subtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ subtitle }}</p>
                <div class="mt-6">
                    <slot />
                </div>
            </div>
        </div>
    </main>
</template>
