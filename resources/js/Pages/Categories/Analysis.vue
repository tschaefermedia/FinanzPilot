<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useTheme } from '@/Composables/useTheme.js';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import TreeTable from 'primevue/treetable';
import Column from 'primevue/column';
import SelectButton from 'primevue/selectbutton';

const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));

const { formatCurrency } = useFormatters();
const { isDark } = useTheme();

const chartTextColor = computed(() => isDark.value ? '#9ca3af' : '#6b7280');

const props = defineProps({
    selectedMonth: { type: String, default: '' },
    prevMonth: { type: String, default: null },
    nextMonth: { type: String, default: null },
    hierarchy: { type: Array, default: () => [] },
    totalExpenses: { type: Number, default: 0 },
    totalIncome: { type: Number, default: 0 },
});

function monthToDate(month) {
    if (!month) {
        return new Date();
    }
    const [y, m] = month.split('-');
    return new Date(y, m - 1);
}

const selectedDate = ref(monthToDate(props.selectedMonth));

// navigateMonth uses preserveState, so keep the picker in sync with the loaded month.
watch(() => props.selectedMonth, (month) => {
    selectedDate.value = monthToDate(month);
});

const viewOptions = [
    { label: 'Ausgaben', value: 'expense' },
    { label: 'Einnahmen', value: 'income' },
];
const activeView = ref('expense');

const amountKey = computed(() => activeView.value === 'expense' ? 'expense' : 'income');

function getAmount(node) {
    return node[amountKey.value];
}

function getPercent(node) {
    return activeView.value === 'expense' ? node.expensePercent : node.incomePercent;
}

// Recursively turn the category tree into PrimeVue TreeTable nodes, keeping only
// branches that have a value for the active view, largest first — any depth.
function toTreeNodes(nodes) {
    const key = amountKey.value;
    return nodes
        .filter((n) => n[key] > 0)
        .map((n) => ({
            key: String(n.id),
            data: n,
            children: toTreeNodes(n.children || []),
        }))
        .sort((a, b) => b.data[key] - a.data[key]);
}

const treeNodes = computed(() => toTreeNodes(props.hierarchy));

// For the charts, descend past single "wrapper" roots (e.g. an Ausgaben /
// Einnahmen umbrella) to the first level that actually has a breakdown.
const chartNodes = computed(() => {
    const key = amountKey.value;
    let nodes = props.hierarchy.filter((n) => n[key] > 0);
    while (nodes.length === 1) {
        const children = (nodes[0].children || []).filter((n) => n[key] > 0);
        if (children.length === 0) {
            break;
        }
        nodes = children;
    }
    return [...nodes].sort((a, b) => b[key] - a[key]);
});

const currentTotal = computed(() =>
    activeView.value === 'expense' ? props.totalExpenses : props.totalIncome
);

const hasData = computed(() => props.hierarchy.length > 0);

// Expand the top level by default so the breakdown is visible immediately.
const expandedKeys = ref({});
watch(treeNodes, (nodes) => {
    const keys = {};
    nodes.forEach((n) => { keys[n.key] = true; });
    expandedKeys.value = keys;
}, { immediate: true });

function navigateMonth(month) {
    router.get('/categories/analysis', { month }, { preserveState: true });
}

function onMonthSelect(date) {
    const month = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    navigateMonth(month);
}

const donutColors = ['#3b82f6', '#ef4444', '#f59e0b', '#22c55e', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];

const donutOptions = computed(() => ({
    chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif', background: 'transparent' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    labels: chartNodes.value.map((n) => n.name),
    colors: donutColors,
    legend: { position: 'bottom', labels: { colors: chartTextColor.value } },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) },
    },
    dataLabels: { enabled: false },
}));

const donutSeries = computed(() => chartNodes.value.map((n) => getAmount(n)));

const treemapOptions = computed(() => ({
    chart: { type: 'treemap', height: 300, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    colors: donutColors,
    plotOptions: { treemap: { distributed: true } },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) },
    },
    dataLabels: {
        enabled: true,
        formatter: (text, op) => [text, new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(op.value)],
    },
}));

const treemapSeries = computed(() => [{
    data: chartNodes.value.map((n) => ({ x: n.name, y: getAmount(n) })),
}]);
</script>

<template>
    <AppLayout>
        <PageHeader title="Kategorien-Analyse">
            <template #actions>
                <a href="/categories" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Kategorien verwalten →</a>
            </template>
        </PageHeader>

        <div class="flex items-center justify-center gap-3 mb-6">
            <Button icon="pi pi-chevron-left" text rounded size="small" :disabled="!prevMonth" @click="prevMonth && navigateMonth(prevMonth)" />
            <DatePicker v-model="selectedDate" view="month" dateFormat="MM yy" :manualInput="false" inputClass="text-center text-lg font-semibold border-none bg-transparent cursor-pointer w-48" @date-select="onMonthSelect" />
            <Button icon="pi pi-chevron-right" text rounded size="small" :disabled="!nextMonth" @click="nextMonth && navigateMonth(nextMonth)" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <StatCard label="Ausgaben gesamt" :value="formatCurrency(totalExpenses)" />
            <StatCard label="Einnahmen gesamt" :value="formatCurrency(totalIncome)" />
        </div>

        <div class="flex justify-center mb-6">
            <SelectButton v-model="activeView" :options="viewOptions" optionLabel="label" optionValue="value" />
        </div>

        <template v-if="hasData">
            <div v-if="chartNodes.length" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Verteilung</h3>
                    <VueApexCharts type="donut" :options="donutOptions" :series="donutSeries" height="300" />
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Treemap</h3>
                    <VueApexCharts type="treemap" :options="treemapOptions" :series="treemapSeries" height="300" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Detailansicht</h3>
                <TreeTable :value="treeNodes" v-model:expandedKeys="expandedKeys" size="small">
                    <Column field="name" header="Kategorie" expander>
                        <template #body="{ node }">
                            <span class="font-medium">{{ node.data.name }}</span>
                            <span class="text-xs text-gray-400 ml-2">({{ node.data.transactionCount }})</span>
                        </template>
                    </Column>
                    <Column header="Betrag">
                        <template #body="{ node }">
                            {{ formatCurrency(getAmount(node.data)) }}
                        </template>
                    </Column>
                    <Column header="Anteil">
                        <template #body="{ node }">
                            {{ getPercent(node.data) }}%
                        </template>
                    </Column>
                    <Column header="Budget">
                        <template #body="{ node }">
                            <template v-if="node.data.budget">
                                <span :class="node.data.expense > node.data.budget ? 'text-red-600 font-semibold' : 'text-green-600'">
                                    {{ formatCurrency(getAmount(node.data)) }} / {{ formatCurrency(node.data.budget) }}
                                </span>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </Column>
                </TreeTable>
            </div>
        </template>
        <template v-else>
            <EmptyState message="Keine Kategorie-Daten für diesen Monat vorhanden." icon="pi-tags" />
        </template>
    </AppLayout>
</template>
