<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import ProgressBar from 'primevue/progressbar';

const { formatCurrency, formatDate, formatNumber, formatDateForSubmit } = useFormatters();
const confirm = useConfirm();

const props = defineProps({
    loans: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const showDialog = ref(false);
const editingLoan = ref(null);

const form = useForm({
    name: '',
    type: 'bank',
    principal: null,
    interest_rate: null,
    start_date: new Date(),
    term_months: null,
    payment_day: null,
    monthly_rate: null,
    initial_balance: null,
    match_description: '',
    account_id: null,
    direction: 'owed_by_me',
    notes: '',
});

const typeOptions = [
    { label: 'Bankdarlehen', value: 'bank' },
    { label: 'Informell', value: 'informal' },
];

const directionOptions = [
    { label: 'Ich schulde', value: 'owed_by_me' },
    { label: 'Mir wird geschuldet', value: 'owed_to_me' },
];

function openCreate() {
    editingLoan.value = null;
    form.reset();
    form.start_date = new Date();
    form.type = 'bank';
    form.direction = 'owed_by_me';
    showDialog.value = true;
}

function openEdit(loan) {
    editingLoan.value = loan;
    form.name = loan.name;
    form.type = loan.type;
    form.principal = parseFloat(loan.principal);
    form.interest_rate = loan.interest_rate ? parseFloat(loan.interest_rate) : null;
    form.start_date = new Date(loan.start_date + 'T00:00:00');
    form.term_months = loan.term_months;
    form.payment_day = loan.payment_day;
    form.monthly_rate = loan.monthly_rate ? parseFloat(loan.monthly_rate) : null;
    form.initial_balance = loan.initial_balance ? parseFloat(loan.initial_balance) : null;
    form.match_description = loan.match_description || '';
    form.account_id = loan.account_id;
    form.direction = loan.direction;
    form.notes = loan.notes || '';
    showDialog.value = true;
}

function submit() {
    const data = { ...form.data(), start_date: formatDateForSubmit(form.start_date) };

    if (editingLoan.value) {
        form.transform(() => data).put(`/loans/${editingLoan.value.id}`, {
            onSuccess: () => { showDialog.value = false; },
        });
    } else {
        form.transform(() => data).post('/loans', {
            onSuccess: () => { showDialog.value = false; },
        });
    }
}

function deleteLoan(id) {
    confirm.require({
        message: 'Darlehen wirklich löschen?',
        header: 'Darlehen löschen',
        icon: 'pi pi-trash',
        acceptLabel: 'Löschen',
        rejectLabel: 'Abbrechen',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/loans/${id}`),
    });
}

function directionLabel(dir) {
    return { owed_by_me: 'Ich schulde', owed_to_me: 'Mir geschuldet' }[dir] || dir;
}

function typeLabel(type) {
    return { bank: 'Bank', informal: 'Informell' }[type] || type;
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Darlehen">
            <Button label="Neues Darlehen" icon="pi pi-plus" size="small" @click="openCreate" />
        </PageHeader>

        <div v-if="loans.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-for="loan in loans" :key="loan.id" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 cursor-pointer hover:border-blue-300 dark:hover:border-blue-600 transition-colors" @click="router.visit(`/loans/${loan.id}`)">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ loan.name }}</h3>
                        <div class="flex gap-2 mt-1">
                            <Tag :value="typeLabel(loan.type)" :severity="loan.type === 'bank' ? 'info' : 'secondary'" />
                            <Tag :value="directionLabel(loan.direction)" :severity="loan.direction === 'owed_by_me' ? 'danger' : 'success'" />
                        </div>
                    </div>
                    <div class="flex gap-1" @click.stop>
                        <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(loan)" />
                        <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteLoan(loan.id)" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Summe</span>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(loan.principal) }}</p>
                    </div>
                    <div v-if="loan.type === 'bank'">
                        <span class="text-gray-500 dark:text-gray-400">Zinssatz</span>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatNumber(loan.interest_rate, 2) }} %</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Restbetrag</span>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(loan.summary?.remainingBalance ?? loan.principal) }}</p>
                    </div>
                    <div v-if="loan.summary?.monthlyPayment">
                        <span class="text-gray-500 dark:text-gray-400">Rate/Monat</span>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ formatCurrency(loan.summary.monthlyPayment) }}</p>
                    </div>
                </div>

                <div v-if="loan.summary?.progressPercent !== undefined">
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <span>Fortschritt</span>
                        <span>{{ formatNumber(loan.summary.progressPercent, 1) }}%</span>
                    </div>
                    <ProgressBar :value="loan.summary.progressPercent" :showValue="false" style="height: 6px" />
                    <p v-if="loan.summary.expectedPayoffDate" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Voraussichtlich abbezahlt: {{ formatDate(loan.summary.expectedPayoffDate) }}
                    </p>
                </div>
            </div>
        </div>
        <EmptyState v-else message="Keine Darlehen vorhanden." icon="pi-building-columns">
            <Button label="Erstes Darlehen erstellen" icon="pi pi-plus" size="small" @click="openCreate" />
        </EmptyState>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="showDialog" :header="editingLoan ? 'Darlehen bearbeiten' : 'Neues Darlehen'" modal class="w-full max-w-lg">
            <form @submit.prevent="submit" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="z.B. Autokredit, Schulden bei Max" />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Typ</label>
                        <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Richtung</label>
                        <Select v-model="form.direction" :options="directionOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag</label>
                    <InputNumber v-model="form.principal" mode="currency" currency="EUR" locale="de-DE" class="w-full" />
                </div>

                <div v-if="form.type === 'bank'" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Zinssatz (%)</label>
                        <InputNumber v-model="form.interest_rate" :minFractionDigits="2" :maxFractionDigits="2" suffix=" %" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Laufzeit (Monate)</label>
                        <InputNumber v-model="form.term_months" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Abbuchungstag</label>
                        <InputNumber v-model="form.payment_day" :min="1" :max="31" class="w-full" />
                        <small class="text-xs text-gray-500 dark:text-gray-400">Wird zum automatischen Zuordnen von Buchungen verwendet.</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Buchungstext</label>
                        <InputText v-model="form.match_description" class="w-full" placeholder="z.B. Kreditnr. oder Bankname" />
                        <small class="text-xs text-gray-500 dark:text-gray-400">Stichwort im Buchungstext zum Zuordnen.</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Konto</label>
                        <Select v-model="form.account_id" :options="accounts" optionLabel="name" optionValue="id" class="w-full" placeholder="Alle Konten" showClear />
                        <small class="text-xs text-gray-500 dark:text-gray-400">Konto für automatische Zuordnung einschränken.</small>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Rate/Monat</label>
                    <InputNumber v-model="form.monthly_rate" mode="currency" currency="EUR" locale="de-DE" class="w-full" />
                    <small v-if="form.type === 'bank'" class="text-xs text-gray-500 dark:text-gray-400">Optional — überschreibt die berechnete Rate.</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Startdatum</label>
                    <DatePicker v-model="form.start_date" dateFormat="dd.mm.yy" showIcon class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Anfangssaldo</label>
                    <InputNumber v-model="form.initial_balance" mode="currency" currency="EUR" locale="de-DE" class="w-full" />
                    <small class="text-xs text-gray-500 dark:text-gray-400">Restschuld bei Beginn der Erfassung. Leer lassen wenn das Darlehen neu ist.</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Notizen</label>
                    <Textarea v-model="form.notes" rows="2" class="w-full" />
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showDialog = false" />
                    <Button type="submit" label="Speichern" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
