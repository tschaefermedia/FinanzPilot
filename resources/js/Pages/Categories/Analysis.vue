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
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';

const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));

const { formatCurrency } = useFormatters();
const { isDark } = useTheme();

const chartTextColor = computed(() => isDark.value ? '#9ca3af' : '#6b7280');

const props = defineProps({
    selectedMonth: { type: String, default: '' },
    prevMonth: { type: String, default: null },
    nextMonth: { type: String, default: null },
    hierarchy: { type: Array, default: () => [] },
    treemapData: { type: Array, default: () => [] },
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

const expandedRows = ref({});

const amountKey = computed(() => activeView.value === 'expense' ? 'expense' : 'income');

const currentData = computed(() => {
    const key = amountKey.value;
    return props.hierarchy
        .filter(row => row[key] > 0)
        .sort((a, b) => b[key] - a[key]);
});

const currentTotal = computed(() =>
    activeView.value === 'expense' ? props.totalExpenses : props.totalIncome
);

const hasData = computed(() => props.hierarchy.length > 0);

function visibleChildren(row) {
    const key = amountKey.value;
    return (row.children || []).filter(child => child[key] > 0);
}

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
    labels: currentData.value.map(d => d.name),
    colors: donutColors,
    legend: { position: 'bottom', labels: { colors: chartTextColor.value } },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) },
    },
    dataLabels: { enabled: false },
}));

const donutSeries = computed(() =>
    currentData.value.map(d => activeView.value === 'expense' ? d.expense : d.income)
);

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
    data: props.treemapData,
}]);

function getAmount(row) {
    return activeView.value === 'expense' ? row.expense : row.income;
}

function getPercent(row) {
    return activeView.value === 'expense' ? row.expensePercent : row.incomePercent;
}
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
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Verteilung</h3>
                    <VueApexCharts type="donut" :options="donutOptions" :series="donutSeries" height="300" />
                </div>
                <div v-if="activeView === 'expense' && treemapData.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Treemap</h3>
                    <VueApexCharts type="treemap" :options="treemapOptions" :series="treemapSeries" height="300" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Detailansicht</h3>
                <DataTable
                    :value="currentData"
                    v-model:expandedRows="expandedRows"
                    dataKey="id"
                    stripedRows
                    size="small"
                >
                    <Column expander style="width: 3rem" />
                    <Column field="name" header="Kategorie" sortable>
                        <template #body="{ data }">
                            <span class="font-medium">{{ data.name }}</span>
                            <span class="text-xs text-gray-400 ml-2">({{ data.transactionCount }})</span>
                        </template>
                    </Column>
                    <Column header="Betrag" sortable :sortField="activeView === 'expense' ? 'expense' : 'income'">
                        <template #body="{ data }">
                            {{ formatCurrency(getAmount(data)) }}
                        </template>
                    </Column>
                    <Column header="Anteil">
                        <template #body="{ data }">
                            {{ getPercent(data) }}%
                        </template>
                    </Column>
                    <Column header="Budget">
                        <template #body="{ data }">
                            <template v-if="data.budget">
                                <span :class="data.expense > data.budget ? 'text-red-600 font-semibold' : 'text-green-600'">
                                    {{ formatCurrency(getAmount(data)) }} / {{ formatCurrency(data.budget) }}
                                </span>
                            </template>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </Column>
                    <template #expansion="{ data }">
                        <div v-if="visibleChildren(data).length > 0" class="pl-8">
                            <DataTable :value="visibleChildren(data)" size="small">
                                <Column field="name" header="Unterkategorie">
                                    <template #body="{ data: child }">
                                        <span class="text-sm">{{ child.name }}</span>
                                        <span class="text-xs text-gray-400 ml-2">({{ child.transactionCount }})</span>
                                    </template>
                                </Column>
                                <Column header="Betrag">
                                    <template #body="{ data: child }">
                                        {{ formatCurrency(getAmount(child)) }}
                                    </template>
                                </Column>
                                <Column header="Anteil">
                                    <template #body="{ data: child }">
                                        {{ getPercent(child) }}%
                                    </template>
                                </Column>
                                <Column header="Budget">
                                    <template #body="{ data: child }">
                                        <template v-if="child.budget">
                                            <span :class="child.expense > child.budget ? 'text-red-600 font-semibold' : 'text-green-600'">
                                                {{ formatCurrency(getAmount(child)) }} / {{ formatCurrency(child.budget) }}
                                            </span>
                                        </template>
                                        <span v-else class="text-gray-400">—</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="pl-8 py-2 text-sm text-gray-500">Keine Unterkategorien</div>
                    </template>
                </DataTable>
            </div>
        </template>
        <template v-else>
            <EmptyState message="Keine Kategorie-Daten für diesen Monat vorhanden." icon="pi-tags" />
        </template>
    </AppLayout>
</template>

<style scoped>
/* PrimeVue's striped-row rule is a descendant selector, so the outer striped
   table bleeds zebra striping onto the nested expansion tables' odd rows.
   Reset it (extra .p-datatable-striped in the selector outranks the vendor rule). */
:deep(.p-datatable-striped .p-datatable-row-expansion .p-datatable-tbody > tr.p-row-odd) {
    background: transparent;
}
</style>
