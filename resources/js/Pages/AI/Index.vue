<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ChatInterface from '@/Components/AI/ChatInterface.vue';
import { useCsrfFetch } from '@/Composables/useCsrfFetch.js';
import { useTheme } from '@/Composables/useTheme.js';
import { ref, computed, onMounted, defineAsyncComponent } from 'vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));

const { csrfFetch } = useCsrfFetch();
const { isDark } = useTheme();

const props = defineProps({
    aiEnabled: { type: Boolean, default: false },
    anomalies: { type: Array, default: () => [] },
    budgetUtilization: { type: Array, default: () => [] },
    currentMonthComplete: { type: Boolean, default: true },
    history: { type: Array, default: () => [] },
});

const loading = ref(false);
const error = ref(null);
const data = ref(null);
const structured = ref(null);
const snapshot = ref(null);
const historyExpanded = ref(false);
const expandedHistoryId = ref(null);

async function fetchInsights(refresh = false) {
    loading.value = true;
    error.value = null;

    try {
        const url = refresh ? '/api/ai/refresh' : '/api/ai/insights';
        const method = refresh ? 'POST' : 'GET';
        const response = await csrfFetch(url, { method });
        const json = await response.json();

        if (!json.enabled) {
            error.value = json.message || 'KI nicht konfiguriert.';
            return;
        }

        if (json.error) {
            error.value = json.error;
            return;
        }

        data.value = json;
        structured.value = json.structured;
        snapshot.value = json.snapshot;
    } catch (e) {
        error.value = 'Fehler beim Laden der KI-Analyse.';
    } finally {
        loading.value = false;
    }
}

// Fetch cached insights on mount (no AI call — cache-only GET)
onMounted(() => fetchInsights());

// Health score color
const healthColor = computed(() => {
    if (!structured.value) return '#9ca3af';
    const score = structured.value.healthScore;
    if (score >= 80) return '#22c55e';
    if (score >= 60) return '#3b82f6';
    if (score >= 40) return '#f59e0b';
    return '#ef4444';
});

const healthLabel = computed(() => {
    if (!structured.value) return '';
    const score = structured.value.healthScore;
    if (score >= 80) return 'Exzellent';
    if (score >= 60) return 'Gut';
    if (score >= 40) return 'Verbesserungswürdig';
    return 'Kritisch';
});

const trendIcon = computed(() => {
    if (!structured.value) return 'pi pi-minus';
    return {
        improving: 'pi pi-arrow-up',
        stable: 'pi pi-minus',
        declining: 'pi pi-arrow-down',
    }[structured.value.healthTrend] || 'pi pi-minus';
});

const trendColor = computed(() => {
    if (!structured.value) return 'text-gray-400';
    return {
        improving: 'text-green-500',
        stable: 'text-blue-500',
        declining: 'text-red-500',
    }[structured.value.healthTrend] || 'text-gray-400';
});

const trendLabel = computed(() => {
    if (!structured.value) return '';
    return {
        improving: 'Aufwärtstrend',
        stable: 'Stabil',
        declining: 'Abwärtstrend',
    }[structured.value.healthTrend] || '';
});

// Health score radial chart
const healthChartOptions = computed(() => ({
    chart: { type: 'radialBar', height: 200, sparkline: { enabled: true } },
    plotOptions: {
        radialBar: {
            startAngle: -135,
            endAngle: 135,
            hollow: { size: '65%' },
            track: { background: isDark.value ? '#374151' : '#e5e7eb' },
            dataLabels: {
                name: { show: false },
                value: {
                    fontSize: '28px',
                    fontWeight: 700,
                    color: healthColor.value,
                    offsetY: 10,
                    formatter: () => structured.value?.healthScore ?? '—',
                },
            },
        },
    },
    colors: [healthColor.value],
    stroke: { lineCap: 'round' },
}));

const healthChartSeries = computed(() => [structured.value?.healthScore ?? 0]);

// Savings rate trend sparkline
const savingsSparkOptions = computed(() => ({
    chart: { type: 'area', height: 80, sparkline: { enabled: true } },
    colors: ['#8b5cf6'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: { formatter: (v) => v + '%' },
        x: { show: true },
    },
    xaxis: {
        categories: snapshot.value?.monthlyRatios?.map(m => m.month) ?? [],
    },
}));

const savingsSparkSeries = computed(() => [{
    name: 'Sparquote',
    data: snapshot.value?.monthlyRatios?.map(m => m.savings) ?? [],
}]);

// Expenses trend sparkline
const expensesSparkOptions = computed(() => ({
    chart: { type: 'area', height: 80, sparkline: { enabled: true } },
    colors: ['#ef4444'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    tooltip: {
        theme: isDark.value ? 'dark' : 'light',
        y: { formatter: (v) => v + '% vom Einkommen' },
        x: { show: true },
    },
    xaxis: {
        categories: snapshot.value?.monthlyRatios?.map(m => m.month) ?? [],
    },
}));

const expensesSparkSeries = computed(() => [{
    name: 'Ausgaben',
    data: snapshot.value?.monthlyRatios?.map(m => m.expenses) ?? [],
}]);

// Highlight styling
function highlightIcon(type) {
    return {
        positive: 'pi pi-check-circle',
        warning: 'pi pi-exclamation-triangle',
        critical: 'pi pi-times-circle',
    }[type] || 'pi pi-info-circle';
}

function highlightBg(type) {
    return {
        positive: 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800',
        warning: 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800',
        critical: 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800',
    }[type] || 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700';
}

function highlightTextColor(type) {
    return {
        positive: 'text-green-700 dark:text-green-400',
        warning: 'text-amber-700 dark:text-amber-400',
        critical: 'text-red-700 dark:text-red-400',
    }[type] || 'text-gray-700 dark:text-gray-400';
}

// Category trend icon
function categoryTrendIcon(trend) {
    return {
        rising: 'pi pi-arrow-up text-red-500',
        stable: 'pi pi-minus text-blue-500',
        falling: 'pi pi-arrow-down text-green-500',
    }[trend] || 'pi pi-minus text-gray-400';
}

// Priority styling
function priorityColor(priority) {
    return {
        high: 'danger',
        medium: 'warn',
        low: 'info',
    }[priority] || 'info';
}

// Budget status
function budgetStatusColor(status) {
    return {
        on_track: 'text-green-600 dark:text-green-400',
        warning: 'text-amber-600 dark:text-amber-400',
        over: 'text-red-600 dark:text-red-400',
    }[status] || 'text-gray-500';
}

function budgetProgressColor(status) {
    return {
        on_track: '#22c55e',
        warning: '#f59e0b',
        over: '#ef4444',
    }[status] || '#9ca3af';
}

// Anomaly helpers
function anomalyIcon(type) {
    return {
        large_transaction: 'pi pi-exclamation-circle',
        category_spike: 'pi pi-chart-bar',
        new_category: 'pi pi-plus-circle',
    }[type] || 'pi pi-info-circle';
}

function anomalyLabel(a) {
    if (a.type === 'large_transaction') return `Große Buchung in "${a.category}" — ${a.factor}x über Durchschnitt`;
    if (a.type === 'category_spike') return `"${a.category}" — ${a.aboveAverage}% über 3-Monats-Durchschnitt`;
    if (a.type === 'new_category') return `Neue Kategorie: "${a.category}"`;
    return '';
}

// Hide categories section when all comments are empty
const hasCategoryComments = computed(() => {
    return structured.value?.categoryInsights?.some(c => c.comment?.trim());
});

// Monthly comparison: last two complete months
const monthlyComparison = computed(() => {
    const ratios = snapshot.value?.monthlyRatios?.filter(m => !m.incomplete) ?? [];
    if (ratios.length < 2) return null;
    const current = ratios[ratios.length - 1];
    const previous = ratios[ratios.length - 2];
    return {
        currentMonth: current.month,
        previousMonth: previous.month,
        savingsRate: current.savings,
        prevSavingsRate: previous.savings,
        savingsChange: +(current.savings - previous.savings).toFixed(1),
        expenses: current.expenses,
        prevExpenses: previous.expenses,
        expensesChange: +(current.expenses - previous.expenses).toFixed(1),
        growing: snapshot.value?.topGrowingCategories ?? [],
        shrinking: snapshot.value?.topShrinkingCategories ?? [],
    };
});

// Check if title is just a prefix/duplicate of detail
function isTitleRedundant(title, detail) {
    if (!title || !detail) return true;
    return detail.startsWith(title.replace(/\.{3}$|…$/, ''));
}

// History helpers
function healthScoreColor(score) {
    if (score >= 80) return '#22c55e';
    if (score >= 60) return '#3b82f6';
    if (score >= 40) return '#f59e0b';
    return '#ef4444';
}

function formatHistoryDate(iso) {
    return new Date(iso).toLocaleDateString('de-DE', { day: '2-digit', month: 'short', year: 'numeric' });
}

function toggleHistoryItem(id) {
    expandedHistoryId.value = expandedHistoryId.value === id ? null : id;
}

// Render markdown bold + newlines as HTML
function renderMarkdown(text) {
    if (!text) return '';
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}
</script>

<template>
    <AppLayout>
        <div class="flex items-center justify-between mb-6">
            <PageHeader title="KI-Analyse" />
            <Button
                v-if="!loading && structured"
                icon="pi pi-refresh"
                label="Aktualisieren"
                text
                size="small"
                @click="fetchInsights(true)"
            />
        </div>

        <!-- Incomplete month warning -->
        <div v-if="!props.currentMonthComplete" class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg px-4 py-3">
            <div class="flex items-center gap-2">
                <i class="pi pi-info-circle text-blue-500"></i>
                <p class="text-sm text-blue-700 dark:text-blue-400">
                    Der aktuelle Monat ist noch unvollständig — Gehaltseingänge am Monatsende sind noch nicht erfasst. Die Analyse basiert auf den abgeschlossenen Monaten.
                </p>
            </div>
        </div>

        <!-- Standalone Anomalies (instant, no AI needed) -->
        <div v-if="props.anomalies.length" class="mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                <i class="pi pi-exclamation-triangle text-amber-500 mr-1"></i>
                Auffälligkeiten
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                <div
                    v-for="(a, i) in props.anomalies"
                    :key="i"
                    class="flex items-center gap-3 px-4 py-3"
                >
                    <i :class="[anomalyIcon(a.type), 'text-amber-500']"></i>
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ anomalyLabel(a) }}</span>
                </div>
            </div>
        </div>

        <!-- Standalone Budget Utilization (instant, no AI needed) -->
        <div v-if="props.budgetUtilization.length && !structured" class="mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                <i class="pi pi-gauge text-purple-500 mr-1"></i>
                Budget-Auslastung
            </h3>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                <div class="space-y-4">
                    <div v-for="(b, i) in props.budgetUtilization" :key="i">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ b.category }}</span>
                            <span :class="['text-xs font-medium', budgetStatusColor(b.status)]">
                                {{ b.spentPercent }}% verbraucht
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                            <div
                                class="h-2 rounded-full transition-all"
                                :style="{
                                    width: Math.min(b.spentPercent, 100) + '%',
                                    backgroundColor: budgetProgressColor(b.status),
                                }"
                            ></div>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-xs text-gray-400">
                                Prognose: {{ b.projectedPercent }}% bei {{ b.monthProgress }}% des Monats
                            </p>
                            <div v-if="b.history?.length" class="flex gap-1">
                                <span
                                    v-for="(h, j) in b.history"
                                    :key="j"
                                    :class="['inline-block w-2 h-2 rounded-full', {
                                        'bg-green-500': h.status === 'on_track',
                                        'bg-amber-500': h.status === 'warning',
                                        'bg-red-500': h.status === 'over',
                                    }]"
                                    :title="`${h.month}: ${h.percent}%`"
                                ></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex flex-col items-center justify-center py-24 gap-4">
            <i class="pi pi-spin pi-spinner text-3xl text-purple-500"></i>
            <p class="text-sm text-gray-500 dark:text-gray-400">Analyse wird erstellt...</p>
        </div>

        <!-- Error / Not configured -->
        <div v-else-if="error" class="max-w-lg mx-auto">
            <EmptyState :message="error" icon="pi-sparkles">
                <a v-if="!props.aiEnabled" href="/settings" class="text-blue-500 hover:underline text-sm">
                    Zu den Einstellungen →
                </a>
                <Button v-else label="Erneut versuchen" icon="pi pi-refresh" text size="small" @click="fetchInsights()" />
            </EmptyState>
        </div>

        <!-- No analysis yet — show start button -->
        <div v-else-if="!structured && !loading && props.aiEnabled && !error" class="max-w-lg mx-auto py-12">
            <EmptyState message="Noch keine Analyse vorhanden." icon="pi-sparkles">
                <Button label="Analyse starten" icon="pi pi-sparkles" @click="fetchInsights(true)" :loading="loading" />
            </EmptyState>
        </div>

        <!-- Structured analysis -->
        <template v-else-if="structured">
            <!-- Health Score + Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col items-center">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">Finanzgesundheit</h3>
                    <VueApexCharts type="radialBar" :options="healthChartOptions" :series="healthChartSeries" height="200" width="200" />
                    <p class="text-sm font-medium mt-1" :style="{ color: healthColor }">{{ healthLabel }}</p>
                    <div class="flex items-center gap-1 mt-2">
                        <i :class="[trendIcon, trendColor, 'text-xs']"></i>
                        <span :class="[trendColor, 'text-xs']">{{ trendLabel }}</span>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-3">Zusammenfassung</h3>
                    <p class="text-gray-700 dark:text-gray-200 leading-relaxed mb-4" v-html="renderMarkdown(structured.summary)"></p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Sparquote</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ snapshot?.savingsRate ?? '—' }}%</p>
                            <span :class="[snapshot?.savingsRateTrend >= 0 ? 'text-green-500' : 'text-red-500', 'text-xs']">
                                {{ snapshot?.savingsRateTrend >= 0 ? '+' : '' }}{{ snapshot?.savingsRateTrend ?? 0 }}%
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Fixkosten-Anteil</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ snapshot?.recurringCoveragePercent ?? '—' }}%</p>
                            <span class="text-xs text-gray-400">der Ausgaben</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Einkommensstabilität</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ snapshot?.incomeStability === 0 ? '—' : (snapshot?.incomeStability < 10 ? 'Hoch' : snapshot?.incomeStability < 25 ? 'Mittel' : 'Niedrig') }}
                            </p>
                            <span v-if="snapshot?.incomeStability > 0" class="text-xs text-gray-400">CV {{ snapshot.incomeStability }}%</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Datenspanne</p>
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ snapshot?.monthlyRatios?.length ?? 0 }}</p>
                            <span class="text-xs text-gray-400">Monate</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend Sparklines -->
            <div v-if="snapshot?.monthlyRatios?.length > 1" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">Sparquote (12 Monate)</h3>
                    <VueApexCharts type="area" :options="savingsSparkOptions" :series="savingsSparkSeries" height="80" />
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">Ausgabenquote (12 Monate)</h3>
                    <VueApexCharts type="area" :options="expensesSparkOptions" :series="expensesSparkSeries" height="80" />
                </div>
            </div>

            <!-- Highlights -->
            <div v-if="structured.highlights?.length" class="mb-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    <i class="pi pi-bolt text-purple-500 mr-1"></i>
                    Highlights
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div
                        v-for="(h, i) in structured.highlights.filter(h => h.detail?.replace(/\*+/g, '').trim())"
                        :key="i"
                        :class="['rounded-lg border p-4', highlightBg(h.type)]"
                    >
                        <div class="flex items-start gap-3">
                            <i :class="[highlightIcon(h.type), highlightTextColor(h.type), 'text-lg mt-0.5']"></i>
                            <div>
                                <p v-if="!isTitleRedundant(h.title, h.detail)" :class="['font-medium text-sm', highlightTextColor(h.type)]" v-html="renderMarkdown(h.title)"></p>
                                <p class="text-sm text-gray-600 dark:text-gray-300" :class="{ 'mt-1': !isTitleRedundant(h.title, h.detail) }" v-html="renderMarkdown(h.detail)"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Insights + Budget Utilization -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div v-if="hasCategoryComments" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                        <i class="pi pi-tags text-blue-500 mr-1"></i>
                        Kategorien
                    </h3>
                    <div class="space-y-3">
                        <div v-for="(c, i) in structured.categoryInsights" :key="i" class="flex items-start gap-3">
                            <i :class="[categoryTrendIcon(c.trend), 'mt-0.5']"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ c.category }}</p>
                                <p v-if="c.comment" class="text-xs text-gray-500 dark:text-gray-400" v-html="renderMarkdown(c.comment)"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="snapshot?.budgetUtilization?.length" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                        <i class="pi pi-gauge text-purple-500 mr-1"></i>
                        Budget-Auslastung
                    </h3>
                    <div class="space-y-4">
                        <div v-for="(b, i) in snapshot.budgetUtilization" :key="i">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-gray-700 dark:text-gray-200">{{ b.category }}</span>
                                <span :class="['text-xs font-medium', budgetStatusColor(b.status)]">
                                    {{ b.spentPercent }}% verbraucht
                                </span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                                <div
                                    class="h-2 rounded-full transition-all"
                                    :style="{
                                        width: Math.min(b.spentPercent, 100) + '%',
                                        backgroundColor: budgetProgressColor(b.status),
                                    }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <p class="text-xs text-gray-400">
                                    Prognose: {{ b.projectedPercent }}% bei {{ b.monthProgress }}% des Monats
                                </p>
                                <div v-if="b.history?.length" class="flex gap-1">
                                    <span
                                        v-for="(h, j) in b.history"
                                        :key="j"
                                        :class="['inline-block w-2 h-2 rounded-full', {
                                            'bg-green-500': h.status === 'on_track',
                                            'bg-amber-500': h.status === 'warning',
                                            'bg-red-500': h.status === 'over',
                                        }]"
                                        :title="`${h.month}: ${h.percent}%`"
                                    ></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Comparison (shown when no budgets exist) -->
                <div v-else-if="monthlyComparison" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">
                        <i class="pi pi-arrow-right-arrow-left text-cyan-500 mr-1"></i>
                        Monatsvergleich
                        <span class="font-normal text-xs text-gray-400 ml-1">{{ monthlyComparison.previousMonth }} → {{ monthlyComparison.currentMonth }}</span>
                    </h3>

                    <div class="space-y-4">
                        <!-- Savings rate -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Sparquote</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ monthlyComparison.savingsRate }}%</span>
                                <span :class="['text-xs font-medium', monthlyComparison.savingsChange >= 0 ? 'text-green-500' : 'text-red-500']">
                                    <i :class="monthlyComparison.savingsChange >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" class="text-[10px]"></i>
                                    {{ Math.abs(monthlyComparison.savingsChange) }}%
                                </span>
                            </div>
                        </div>

                        <!-- Expenses -->
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Ausgaben</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ monthlyComparison.expenses }}%</span>
                                <span :class="['text-xs font-medium', monthlyComparison.expensesChange <= 0 ? 'text-green-500' : 'text-red-500']">
                                    <i :class="monthlyComparison.expensesChange <= 0 ? 'pi pi-arrow-down' : 'pi pi-arrow-up'" class="text-[10px]"></i>
                                    {{ Math.abs(monthlyComparison.expensesChange) }}%
                                </span>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="border-t border-gray-100 dark:border-gray-700"></div>

                        <!-- Growing categories -->
                        <div v-if="monthlyComparison.growing.length">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Gestiegen</p>
                            <div class="space-y-1.5">
                                <div v-for="c in monthlyComparison.growing" :key="c.category" class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ c.category }}</span>
                                    <span class="text-xs font-medium text-red-500">+{{ c.change }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Shrinking categories -->
                        <div v-if="monthlyComparison.shrinking.length">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Gesunken</p>
                            <div class="space-y-1.5">
                                <div v-for="c in monthlyComparison.shrinking" :key="c.category" class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ c.category }}</span>
                                    <span class="text-xs font-medium text-green-500">{{ c.change }}%</span>
                                </div>
                            </div>
                        </div>

                        <p v-if="!monthlyComparison.growing.length && !monthlyComparison.shrinking.length" class="text-xs text-gray-400">Keine auffälligen Kategorie-Veränderungen.</p>
                    </div>
                </div>
            </div>

            <!-- Loan Insights -->
            <div v-if="structured.loanInsights?.length" class="mb-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    <i class="pi pi-building-columns text-indigo-500 mr-1"></i>
                    Darlehen
                </h3>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                    <div v-for="(l, i) in structured.loanInsights" :key="i" class="px-4 py-3">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ structured.loanNameMap?.[l.loan] ?? l.loan }}</p>
                        <p v-if="l.comment?.trim()" class="text-xs text-gray-500 dark:text-gray-400 mt-1" v-html="renderMarkdown(l.comment)"></p>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div v-if="structured.recommendations?.length" class="mb-6">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                    <i class="pi pi-lightbulb text-amber-500 mr-1"></i>
                    Empfehlungen
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div
                        v-for="(r, i) in structured.recommendations.filter(r => r.detail?.replace(/\*+/g, '').trim())"
                        :key="i"
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4"
                    >
                        <div class="flex items-center gap-2 mb-2">
                            <Tag :value="r.priority === 'high' ? 'Hoch' : r.priority === 'medium' ? 'Mittel' : 'Niedrig'" :severity="priorityColor(r.priority)" />
                            <span v-if="!isTitleRedundant(r.title, r.detail)" class="text-sm font-medium text-gray-900 dark:text-white" v-html="renderMarkdown(r.title)"></span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300" v-html="renderMarkdown(r.detail)"></p>
                        <p v-if="r.impact" class="text-xs text-purple-600 dark:text-purple-400 mt-2">
                            <i class="pi pi-arrow-right text-xs mr-1"></i><span v-html="renderMarkdown(r.impact)"></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Provider info -->
            <div v-if="data?.provider" class="text-xs text-gray-400 dark:text-gray-500 text-right mb-6">
                Analyse von {{ data.provider }} · {{ data.generatedAt ? new Date(data.generatedAt).toLocaleString('de-DE') : '' }}
            </div>

            <!-- Chat -->
            <ChatInterface />
        </template>

        <!-- Raw text fallback -->
        <template v-else-if="data?.raw">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
                <div class="text-sm text-gray-700 dark:text-gray-200 leading-relaxed" v-html="renderMarkdown(data.raw)"></div>
            </div>
            <ChatInterface />
        </template>

        <!-- Analysis History Timeline -->
        <div v-if="props.history.length > 0" class="mt-8">
            <button
                class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3 hover:text-gray-900 dark:hover:text-white transition-colors"
                @click="historyExpanded = !historyExpanded"
            >
                <i :class="['pi text-xs', historyExpanded ? 'pi-chevron-down' : 'pi pi-chevron-right']"></i>
                <i class="pi pi-history text-gray-400 mr-1"></i>
                Frühere Analysen ({{ props.history.length }})
            </button>

            <div v-if="historyExpanded" class="space-y-2">
                <div
                    v-for="entry in props.history"
                    :key="entry.id"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700"
                >
                    <button
                        class="w-full flex items-center gap-4 px-4 py-3 text-left"
                        @click="toggleHistoryItem(entry.id)"
                    >
                        <span
                            class="text-lg font-bold w-10 text-center"
                            :style="{ color: healthScoreColor(entry.healthScore) }"
                        >
                            {{ entry.healthScore }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700 dark:text-gray-200 truncate" v-html="renderMarkdown(entry.summary)"></p>
                            <p class="text-xs text-gray-400">{{ formatHistoryDate(entry.createdAt) }} · {{ entry.provider }}</p>
                        </div>
                        <i :class="['pi text-gray-400 text-xs', expandedHistoryId === entry.id ? 'pi-chevron-up' : 'pi-chevron-down']"></i>
                    </button>

                    <div v-if="expandedHistoryId === entry.id" class="px-4 pb-4 border-t border-gray-100 dark:border-gray-700 pt-3">
                        <div v-if="entry.highlights?.length" class="space-y-2">
                            <div
                                v-for="(h, i) in entry.highlights"
                                :key="i"
                                class="flex items-start gap-2"
                            >
                                <i :class="[highlightIcon(h.type), highlightTextColor(h.type), 'text-sm mt-0.5']"></i>
                                <div>
                                    <span :class="['text-xs font-medium', highlightTextColor(h.type)]" v-html="renderMarkdown(h.title)"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1" v-html="renderMarkdown(h.detail)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
