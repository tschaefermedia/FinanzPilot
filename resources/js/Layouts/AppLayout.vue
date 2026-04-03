<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false);
const page = usePage();
const currentUrl = computed(() => page.url);

const navItems = [
    { label: 'Übersicht', icon: 'pi pi-home', href: '/', active: true },
    { label: 'Konten', icon: 'pi pi-wallet', href: '/accounts', active: true },
    { label: 'Buchungen', icon: 'pi pi-list', href: '/transactions', active: true },
    { label: 'Kategorien', icon: 'pi pi-tags', href: '/categories', active: true },
    { label: 'Import', icon: 'pi pi-upload', href: '/imports', active: true },
    { label: 'Darlehen', icon: 'pi pi-building-columns', href: '/loans', active: true },
    { label: 'Daueraufträge', icon: 'pi pi-replay', href: '/recurring', active: true },
    { label: 'Export', icon: 'pi pi-download', href: '/export', active: true },
    { label: 'Einstellungen', icon: 'pi pi-cog', href: '/settings', active: true },
];

function isActive(href) {
    if (href === '/') return currentUrl.value === '/';
    return currentUrl.value.startsWith(href);
}

</script>

<template>
    <div class="md:hidden fixed top-0 left-0 right-0 h-14 bg-white border-b border-gray-200 flex items-center px-4 z-50">
        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-600 hover:text-gray-900">
            <i class="pi pi-bars text-xl"></i>
        </button>
        <span class="ml-3 text-lg font-semibold text-gray-900">FinanzPilot</span>
    </div>

    <div v-if="sidebarOpen" class="md:hidden fixed inset-0 bg-black/30 z-40" @click="sidebarOpen = false"></div>

    <aside
        :class="[
            'fixed top-0 left-0 h-full w-[250px] bg-white border-r border-gray-200 z-50 flex flex-col transition-transform duration-200',
            sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        ]"
    >
        <div class="h-14 flex items-center px-5 border-b border-gray-100">
            <span class="text-lg font-bold text-gray-900 tracking-tight">FinanzPilot</span>
        </div>

        <nav class="flex-1 py-4 px-3 space-y-1">
            <template v-for="item in navItems" :key="item.href">
                <Link
                    v-if="item.active"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.href)
                            ? 'bg-blue-50 text-blue-700'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                    ]"
                    @click="sidebarOpen = false"
                >
                    <i :class="[item.icon, 'text-base']"></i>
                    {{ item.label }}
                </Link>
                <span
                    v-else
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-300 cursor-not-allowed"
                >
                    <i :class="[item.icon, 'text-base']"></i>
                    {{ item.label }}
                </span>
            </template>
        </nav>
    </aside>

    <main class="md:ml-[250px] min-h-screen bg-gray-50 pt-14 md:pt-0">
        <div class="p-6 max-w-7xl mx-auto">
            <slot />
        </div>
    </main>
</template>
