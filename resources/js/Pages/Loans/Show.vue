<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useTheme } from '@/Composables/useTheme.js';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, defineAsyncComponent } from 'vue';

const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';

const { formatCurrency, formatDate, formatNumber, formatDateForSubmit } = useFormatters();
const { isDark } = useTheme();

const chartTextColor = computed(() => isDark.value ? '#9ca3af' : '#6b7280');
const chartGridColor = computed(() => isDark.value ? '#374151' : '#e5e7eb');

const props = defineProps({
    loan: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
});

const showPaymentDialog = ref(false);
const paymentForm = useForm({
    date: new Date(),
    amount: null,
    type: 'manual',
});

const paymentTypeOptions = [
    { label: 'Planmäßig', value: 'scheduled' },
    { label: 'Sondertilgung', value: 'extra' },
    { label: 'Manuell', value: 'manual' },
];

function submitPayment() {
    paymentForm.transform((data) => ({
        ...data,
        date: formatDateForSubmit(data.date),
    })).post(`/loans/${props.loan.id}/payments`, {
        onSuccess: () => {
            showPaymentDialog.value = false;
            paymentForm.reset();
            paymentForm.date = new Date();
        },
    });
}

function autoMatch() {
    router.post(`/loans/${props.loan.id}/auto-match`);
}

const showMatchDialog = ref(false);
const unmatchedTransactions = ref([]);
const loadingTransactions = ref(false);

async function openMatchDialog() {
    loadingTransactions.value = true;
    showMatchDialog.value = true;
    try {
        const response = await fetch(`/loans/${props.loan.id}/unmatched-transactions`);
        if (!response.ok) throw new Error('Failed to load transactions');
        unmatchedTransactions.value = await response.json();
    } finally {
        loadingTransactions.value = false;
    }
}

function matchTransaction(transactionId) {
    router.post(`/loans/${props.loan.id}/match-transaction`, { transaction_id: transactionId }, {
        onSuccess: () => {
            unmatchedTransactions.value = unmatchedTransactions.value.filter(t => t.id !== transactionId);
        },
    });
}

function paymentTypeLabel(type) {
    return { scheduled: 'Planmäßig', extra: 'Sondertilgung', manual: 'Manuell' }[type] || type;
}

// Chart for amortization schedule
const hasSchedule = computed(() => props.summary?.schedule?.length > 0);

const chartOptions = computed(() => ({
    chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Inter, sans-serif', background: 'transparent' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    colors: ['#3b82f6', '#ef4444'],
    xaxis: {
        categories: (props.summary?.schedule || []).filter((_, i) => i % 3 === 0).map(s => s.date.substring(0, 7)),
        labels: { style: { colors: chartTextColor.value } },
    },
    yaxis: {
        labels: { style: { colors: chartTextColor.value }, formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v) },
    },
    grid: { borderColor: chartGridColor.value },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    tooltip: { theme: isDark.value ? 'dark' : 'light', y: { formatter: (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(v) } },
    legend: { position: 'top', labels: { colors: chartTextColor.value } },
}));

const chartSeries = computed(() => [
    {
        name: 'Restschuld',
        data: (props.summary?.schedule || []).filter((_, i) => i % 3 === 0).map(s => s.balance),
    },
]);
</script>

<template>
    <AppLayout>
        <PageHeader :title="loan.name" backHref="/loans" backLabel="Alle Darlehen">
            <Button label="Zahlung erfassen" icon="pi pi-plus" size="small" @click="showPaymentDialog = true" />
            <Button label="Buchung zuordnen" icon="pi pi-link" size="small" severity="secondary" @click="openMatchDialog" />
            <Button v-if="loan.type === 'bank'" label="Auto-Match" icon="pi pi-sync" size="small" severity="secondary" @click="autoMatch" />
        </PageHeader>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <StatCard label="Darlehenssumme" :value="formatCurrency(loan.principal)" />
            <StatCard label="Restbetrag" :value="formatCurrency(summary.remainingBalance ?? 0)" />
            <StatCard v-if="loan.type === 'bank'" label="Gesamtzinsen" :value="formatCurrency(summary.totalInterest ?? 0)" />
            <StatCard label="Fortschritt" :value="`${formatNumber(summary.progressPercent ?? 0, 1)}%`" />
            <StatCard v-if="summary.monthlyPayment" label="Rate/Monat" :value="formatCurrency(summary.monthlyPayment)" />
            <StatCard v-if="summary.remainingMonths" label="Verbleibende Monate" :value="String(summary.remainingMonths)" />
            <StatCard v-if="summary.expectedPayoffDate" label="Voraussichtlich abbezahlt" :value="formatDate(summary.expectedPayoffDate)" />
        </div>

        <!-- Payoff chart -->
        <div v-if="hasSchedule" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Tilgungsverlauf</h3>
            <VueApexCharts type="area" :options="chartOptions" :series="chartSeries" height="300" />
        </div>

        <!-- Tabs: Payments + Schedule -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <TabView>
                <TabPanel header="Zahlungen">
                    <DataTable v-if="loan.payments && loan.payments.length > 0" :value="loan.payments" class="text-sm">
                        <Column field="date" header="Datum" style="width: 120px">
                            <template #body="{ data }">{{ formatDate(data.date) }}</template>
                        </Column>
                        <Column field="amount" header="Betrag" style="width: 130px">
                            <template #body="{ data }">{{ formatCurrency(data.amount) }}</template>
                        </Column>
                        <Column field="type" header="Typ" style="width: 130px">
                            <template #body="{ data }">
                                <Tag :value="paymentTypeLabel(data.type)" :severity="data.type === 'extra' ? 'success' : 'info'" />
                            </template>
                        </Column>
                        <Column header="Buchung" style="width: 200px">
                            <template #body="{ data }">
                                <span v-if="data.transaction" class="text-xs text-gray-500 dark:text-gray-400">{{ data.transaction.description }}</span>
                                <span v-else class="text-xs text-gray-400 dark:text-gray-400">Manuell</span>
                            </template>
                        </Column>
                    </DataTable>
                    <div v-else class="py-8 text-center text-gray-400 dark:text-gray-400 text-sm">Noch keine Zahlungen erfasst.</div>
                </TabPanel>

                <TabPanel v-if="hasSchedule" header="Tilgungsplan">
                    <DataTable :value="summary.schedule" class="text-sm" paginator :rows="12">
                        <Column field="month" header="#" style="width: 60px" />
                        <Column field="date" header="Datum" style="width: 110px">
                            <template #body="{ data }">{{ data.date.substring(0, 7) }}</template>
                        </Column>
                        <Column field="payment" header="Rate" style="width: 120px">
                            <template #body="{ data }">{{ formatCurrency(data.payment) }}</template>
                        </Column>
                        <Column field="principal" header="Tilgung" style="width: 120px">
                            <template #body="{ data }">{{ formatCurrency(data.principal) }}</template>
                        </Column>
                        <Column field="interest" header="Zinsen" style="width: 120px">
                            <template #body="{ data }">{{ formatCurrency(data.interest) }}</template>
                        </Column>
                        <Column field="balance" header="Restschuld" style="width: 130px">
                            <template #body="{ data }">{{ formatCurrency(data.balance) }}</template>
                        </Column>
                    </DataTable>
                </TabPanel>
            </TabView>
        </div>

        <!-- Payment Dialog -->
        <Dialog v-model:visible="showPaymentDialog" header="Zahlung erfassen" modal class="w-full max-w-md">
            <form @submit.prevent="submitPayment" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Datum</label>
                    <DatePicker v-model="paymentForm.date" dateFormat="dd.mm.yy" showIcon class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag</label>
                    <InputNumber v-model="paymentForm.amount" mode="currency" currency="EUR" locale="de-DE" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Typ</label>
                    <Select v-model="paymentForm.type" :options="paymentTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showPaymentDialog = false" />
                    <Button type="submit" label="Speichern" size="small" :loading="paymentForm.processing" />
                </div>
            </form>
        </Dialog>

        <!-- Match Transaction Dialog -->
        <Dialog v-model:visible="showMatchDialog" header="Buchung zuordnen" modal class="w-full max-w-2xl">
            <div v-if="loadingTransactions" class="py-8 text-center text-gray-500 text-sm">Buchungen werden geladen...</div>
            <div v-else-if="unmatchedTransactions.length === 0" class="py-8 text-center text-gray-400 dark:text-gray-400 text-sm">Keine passenden Buchungen gefunden.</div>
            <DataTable v-else :value="unmatchedTransactions" class="text-sm" :rows="10" paginator :rowClass="(data) => data.is_match ? 'bg-green-50 dark:bg-green-900/20' : ''">
                <Column field="date" header="Datum" style="width: 110px">
                    <template #body="{ data }">{{ formatDate(data.date) }}</template>
                </Column>
                <Column field="description" header="Beschreibung" />
                <Column field="counterparty" header="Empfänger" style="width: 150px" />
                <Column field="amount" header="Betrag" style="width: 120px">
                    <template #body="{ data }">{{ formatCurrency(Math.abs(data.amount)) }}</template>
                </Column>
                <Column header="" style="width: 80px">
                    <template #body="{ data }">
                        <Button icon="pi pi-link" text rounded size="small" @click="matchTransaction(data.id)" />
                    </template>
                </Column>
            </DataTable>
        </Dialog>
    </AppLayout>
</template>
