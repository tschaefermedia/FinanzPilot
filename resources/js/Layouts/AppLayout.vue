<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme.js';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { onUnmounted } from 'vue';

const { isDark, toggleTheme } = useTheme();
const toast = useToast();
const sidebarOpen = ref(false);
const page = usePage();
const currentUrl = computed(() => page.url);

function showFlashMessages() {
    const flash = page.props.flash;
    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Erfolg', detail: flash.success, life: 3000 });
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Fehler', detail: flash.error, life: 5000 });
    }
    if (flash?.info) {
        toast.add({ severity: 'info', summary: 'Info', detail: flash.info, life: 3000 });
    }
}

const removeFinishListener = router.on('finish', () => {
    showFlashMessages();
});

onUnmounted(() => {
    removeFinishListener();
});

const navItems = [
    { label: 'Übersicht', icon: 'pi pi-home', href: '/', active: true },
    { label: 'Konten', icon: 'pi pi-wallet', href: '/accounts', active: true },
    { label: 'Buchungen', icon: 'pi pi-list', href: '/transactions', active: true },
    { label: 'Kategorien', icon: 'pi pi-tags', href: '/categories', active: true },
    { label: 'Import', icon: 'pi pi-upload', href: '/imports', active: true },
    { label: 'Darlehen', icon: 'pi pi-building-columns', href: '/loans', active: true },
    { label: 'Daueraufträge', icon: 'pi pi-replay', href: '/recurring', active: true },
    { label: 'Kalender', icon: 'pi pi-calendar', href: '/calendar', active: true },
    { label: 'Trends', icon: 'pi pi-chart-line', href: '/trends', active: true },
    { label: 'KI-Analyse', icon: 'pi pi-sparkles', href: '/ai', active: true },
    { label: 'Export', icon: 'pi pi-download', href: '/export', active: true },
    { label: 'Einstellungen', icon: 'pi pi-cog', href: '/settings', active: true },
];

function isActive(href) {
    if (href === '/') return currentUrl.value === '/';
    return currentUrl.value.startsWith(href);
}

</script>

<template>
    <div class="md:hidden fixed top-0 left-0 right-0 h-14 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center px-4 z-50">
        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
            <i class="pi pi-bars text-xl"></i>
        </button>
        <span class="ml-3 text-lg font-semibold text-gray-900 dark:text-white">FinanzPilot</span>
    </div>

    <div v-if="sidebarOpen" class="md:hidden fixed inset-0 bg-black/30 z-40" @click="sidebarOpen = false"></div>

    <aside
        :class="[
            'fixed top-0 left-0 h-full w-[250px] bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 z-50 flex flex-col transition-transform duration-200',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        ]"
    >
        <div class="h-14 flex items-center px-5 border-b border-gray-100 dark:border-gray-700">
            <span class="text-lg font-bold text-gray-900 dark:text-white tracking-tight">FinanzPilot</span>
        </div>

        <nav class="flex-1 py-4 px-3 space-y-1">
            <template v-for="item in navItems" :key="item.href">
                <Link
                    v-if="item.active"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.href)
                            ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'
                            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                    ]"
                    @click="sidebarOpen = false"
                >
                    <i :class="[item.icon, 'text-base']"></i>
                    {{ item.label }}
                </Link>
                <span
                    v-else
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-300 dark:text-gray-600 cursor-not-allowed"
                >
                    <i :class="[item.icon, 'text-base']"></i>
                    {{ item.label }}
                </span>
            </template>
        </nav>

        <div class="px-3 py-4 border-t border-gray-100 dark:border-gray-700">
            <button
                @click="toggleTheme"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white w-full transition-colors"
            >
                <i :class="[isDark ? 'pi pi-sun' : 'pi pi-moon', 'text-base']"></i>
                {{ isDark ? 'Hell' : 'Dunkel' }}
            </button>
        </div>
    </aside>

    <main class="md:ml-[250px] min-h-screen bg-gray-50 dark:bg-gray-900 pt-14 md:pt-0">
        <div class="p-6 max-w-7xl mx-auto">
            <slot />
        </div>
    </main>

    <Toast />
    <ConfirmDialog />
</template>
