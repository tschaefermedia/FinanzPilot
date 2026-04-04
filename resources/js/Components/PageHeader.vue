<script setup>
defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
        <div class="flex items-center gap-2">
            <slot />
        </div>
    </div>
</template>
