<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AiInsightsCard from '@/Components/AiInsightsCard.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useTheme } from '@/Composables/useTheme.js';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';

const { formatCurrency, formatPercent } = useFormatters();
const { isDark } = useTheme();

const chartTextColor = computed(() => isDark.value ? '#9ca3af' : '#6b7280');
const chartGridColor = computed(() => isDark.value ? '#374151' : '#e5e7eb');

const props = defineProps({
    selectedMonth: { type: String, default: '' },
    prevMonth: { type: String, default: null },
    nextMonth: { type: String, default: null },
    stats: {
        type: Object,
        default: () => ({ income: 0, expenses: 0, balance: 0, savingsRate: 0 }),
    },
    monthlyData: { type: Array, default: () => [] },
    categoryData: { type: Array, default: () => [] },
    balanceData: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    totalBalance: { type: Number, default: 0 },
});

const monthLabel = computed(() => {
    if (!props.selectedMonth) return '';
    const [year, month] = props.selectedMonth.split('-');
    return new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric' }).format(new Date(year, month - 1));
});

function navigateMonth(month) {
    router.get('/', { month }, { preserveState: true });
}

const hasData = computed(() => props.monthlyData.length > 0);

const monthlyChartOptions = computed(() => ({
    chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
    colors: ['#22c55e', '#ef4444'],
    xaxis: { categories: props.monthlyData.map(d => d.month), labels: { style: { colors: chartTextColor.value } } },
    yaxis: { labels: { style: { colors: chartTextColor.value }, formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v) } },
    grid: { borderColor: chartGridColor.value },
    dataLabels: { enabled: false },
    legend: { position: 'top', labels: { colors: chartTextColor.value } },
    tooltip: { theme: isDark.value ? 'dark' : 'light', y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) } },
}));

const monthlySeries = computed(() => [
    { name: 'Einnahmen', data: props.monthlyData.map(d => d.income) },
    { name: 'Ausgaben', data: props.monthlyData.map(d => d.expenses) },
]);

const categoryChartOptions = computed(() => ({
    chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif', background: 'transparent' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    labels: props.categoryData.map(d => d.name),
    colors: ['#3b82f6', '#ef4444', '#f59e0b', '#22c55e', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'],
    legend: { position: 'bottom', labels: { colors: chartTextColor.value } },
    tooltip: { theme: isDark.value ? 'dark' : 'light', y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) } },
    dataLabels: { enabled: false },
}));

const categorySeries = computed(() => props.categoryData.map(d => d.total));

const balanceChartOptions = computed(() => ({
    chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    colors: ['#3b82f6'],
    xaxis: { categories: props.balanceData.map(d => d.month), labels: { style: { colors: chartTextColor.value } } },
    yaxis: { labels: { style: { colors: chartTextColor.value }, formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v) } },
    grid: { borderColor: chartGridColor.value },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    tooltip: { theme: isDark.value ? 'dark' : 'light', y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) } },
}));

const balanceSeries = computed(() => [
    { name: 'Kontostand', data: props.balanceData.map(d => d.balance) },
]);
</script>

<template>
    <AppLayout>
        <PageHeader title="Übersicht" />

        <!-- Account Overview -->
        <div v-if="accounts.length > 0" class="mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gesamtvermögen</p>
                        <p :class="['text-3xl font-bold', totalBalance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600']">
                            {{ formatCurrency(totalBalance) }}
                        </p>
                    </div>
                    <a href="/accounts" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Alle Konten →</a>
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4 relative overflow-hidden"
                >
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 truncate">{{ account.name }}</p>
                    <p :class="['text-lg font-bold mt-1', account.current_balance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600']">
                        {{ formatCurrency(account.current_balance) }}
                    </p>
                    <div
                        v-if="account.color"
                        class="absolute top-0 left-0 w-1 h-full"
                        :style="{ backgroundColor: account.color }"
                    />
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center gap-3 mb-6">
            <Button icon="pi pi-chevron-left" text rounded size="small" :disabled="!prevMonth" @click="prevMonth && navigateMonth(prevMonth)" />
            <span class="text-lg font-semibold text-gray-900 dark:text-white min-w-[10rem] text-center">{{ monthLabel }}</span>
            <Button icon="pi pi-chevron-right" text rounded size="small" :disabled="!nextMonth" @click="nextMonth && navigateMonth(nextMonth)" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <StatCard label="Einnahmen" :value="formatCurrency(stats.income)" />
            <StatCard label="Ausgaben" :value="formatCurrency(stats.expenses)" />
            <StatCard label="Differenz" :value="formatCurrency(stats.balance)" />
            <StatCard label="Sparquote" :value="formatPercent(stats.savingsRate)" />
        </div>

        <template v-if="hasData">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Einnahmen vs. Ausgaben</h3>
                    <apexchart type="bar" :options="monthlyChartOptions" :series="monthlySeries" height="300" />
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Ausgaben nach Kategorie</h3>
                    <apexchart type="donut" :options="categoryChartOptions" :series="categorySeries" height="300" />
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Kontoverlauf</h3>
                <apexchart type="area" :options="balanceChartOptions" :series="balanceSeries" height="300" />
            </div>
            <div class="mt-6">
                <AiInsightsCard />
            </div>
        </template>
        <template v-else>
            <EmptyState message="Keine Daten vorhanden. Erstelle deine erste Buchung." icon="pi-chart-bar" />
        </template>
    </AppLayout>
</template>
