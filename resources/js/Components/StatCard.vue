<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    trend: { type: String, default: null },
    trendUp: { type: Boolean, default: null },
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-100 dark:border-gray-700">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ label }}</p>
        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ value }}</p>
        <div v-if="trend" class="mt-2 flex items-center gap-1 text-sm">
            <i :class="[trendUp ? 'pi pi-arrow-up text-green-600' : 'pi pi-arrow-down text-red-600', 'text-xs']"></i>
            <span :class="trendUp ? 'text-green-600' : 'text-red-600'">{{ trend }}</span>
        </div>
    </div>
</template>
