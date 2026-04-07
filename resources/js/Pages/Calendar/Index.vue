<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import TreeSelect from 'primevue/treeselect';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';

const { formatCurrency, formatDateForSubmit } = useFormatters();
const confirm = useConfirm();

const props = defineProps({
    events: { type: Array, default: () => [] },
    months: { type: Array, default: () => [] },
    selectedMonth: { type: String, default: '' },
    summary: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const selectedDate = ref(props.selectedMonth ? (() => {
    const [y, m] = props.selectedMonth.split('-');
    return new Date(y, m - 1);
})() : new Date());

const showCreateDialog = ref(false);
const showDetailDialog = ref(false);
const selectedEvent = ref(null);
const editingPayment = ref(null);

const form = useForm({
    description: '',
    amount: null,
    date: null,
    category_id: null,
    account_id: null,
    notes: '',
});

const selectedCategory = computed({
    get: () => form.category_id ? { [form.category_id]: true } : null,
    set: (val) => {
        form.category_id = val ? Number(Object.keys(val)[0]) : null;
    },
});

function navigateMonth(direction) {
    const d = new Date(selectedDate.value);
    d.setMonth(d.getMonth() + direction);
    const month = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    router.get('/calendar', { month }, { preserveState: true });
}

function onMonthSelect(date) {
    const month = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    router.get('/calendar', { month }, { preserveState: true });
}

// Calendar grid helpers
const dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

function getCalendarDays(monthStr) {
    const [y, m] = monthStr.split('-').map(Number);
    const firstDay = new Date(y, m - 1, 1);
    const lastDay = new Date(y, m, 0);
    const startOffset = (firstDay.getDay() + 6) % 7; // Monday = 0

    const days = [];
    // Empty cells before first day
    for (let i = 0; i < startOffset; i++) {
        days.push({ day: null, date: null });
    }
    // Days of month
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        days.push({ day: d, date: dateStr });
    }
    return days;
}

function getEventsForDate(dateStr) {
    return props.events.filter(e => e.date === dateStr);
}

function getMonthName(monthStr) {
    const [y, m] = monthStr.split('-').map(Number);
    return new Date(y, m - 1).toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
}

function openCreate() {
    editingPayment.value = null;
    form.reset();
    form.clearErrors();
    showCreateDialog.value = true;
}

function openDetail(event) {
    selectedEvent.value = event;
    showDetailDialog.value = true;
}

function editPayment(event) {
    showDetailDialog.value = false;
    editingPayment.value = event.paymentId;
    form.description = event.description;
    form.amount = event.amount;
    form.date = new Date(event.date);
    form.category_id = null; // TreeSelect needs the category key
    form.account_id = null;
    form.notes = event.notes || '';
    showCreateDialog.value = true;
}

function submit() {
    const data = {
        ...form.data(),
        date: formatDateForSubmit(form.date),
    };

    if (editingPayment.value) {
        router.put(`/calendar/payments/${editingPayment.value}`, data, {
            onSuccess: () => { showCreateDialog.value = false; },
        });
    } else {
        form.transform(() => data).post('/calendar/payments', {
            onSuccess: () => { showCreateDialog.value = false; form.reset(); },
        });
    }
}

function completePayment(paymentId) {
    router.post(`/calendar/payments/${paymentId}/complete`);
    showDetailDialog.value = false;
}

function deletePayment(paymentId) {
    confirm.require({
        message: 'Geplante Zahlung wirklich löschen?',
        header: 'Löschen',
        icon: 'pi pi-trash',
        acceptLabel: 'Löschen',
        rejectLabel: 'Abbrechen',
        acceptClass: 'p-button-danger',
        accept: () => {
            router.delete(`/calendar/payments/${paymentId}`);
            showDetailDialog.value = false;
        },
    });
}

const currentSummary = computed(() => props.summary[props.selectedMonth] || { income: 0, expenses: 0, net: 0, count: 0 });
</script>

<template>
    <AppLayout>
        <PageHeader title="Kalender">
            <Button label="Neue Zahlung" icon="pi pi-plus" size="small" @click="openCreate()" />
        </PageHeader>

        <div class="flex items-center justify-center gap-3 mb-6">
            <Button icon="pi pi-chevron-left" text rounded size="small" @click="navigateMonth(-1)" />
            <DatePicker v-model="selectedDate" view="month" dateFormat="MM yy" :manualInput="false" inputClass="text-center text-lg font-semibold border-none bg-transparent cursor-pointer w-48" @date-select="onMonthSelect" />
            <Button icon="pi pi-chevron-right" text rounded size="small" @click="navigateMonth(1)" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <StatCard label="Erwartete Einnahmen" :value="formatCurrency(currentSummary.income)" />
            <StatCard label="Erwartete Ausgaben" :value="formatCurrency(currentSummary.expenses)" />
            <StatCard label="Erwartetes Netto" :value="formatCurrency(currentSummary.net)" />
        </div>

        <div v-for="month in months" :key="month" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">{{ getMonthName(month) }}</h3>

            <!-- Day headers -->
            <div class="grid grid-cols-7 gap-1 mb-1">
                <div v-for="day in dayNames" :key="day" class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">
                    {{ day }}
                </div>
            </div>

            <!-- Calendar grid -->
            <div class="grid grid-cols-7 gap-1">
                <div
                    v-for="(cell, idx) in getCalendarDays(month)"
                    :key="idx"
                    :class="[
                        'min-h-16 p-1 rounded text-xs',
                        cell.day ? 'bg-gray-50 dark:bg-gray-700/50' : '',
                    ]"
                >
                    <template v-if="cell.day">
                        <div class="font-medium text-gray-600 dark:text-gray-300 mb-0.5">{{ cell.day }}</div>
                        <div class="space-y-0.5">
                            <div
                                v-for="event in getEventsForDate(cell.date)"
                                :key="event.id"
                                :class="[
                                    'flex items-center gap-1 px-1 py-0.5 rounded cursor-pointer truncate text-[10px] leading-tight',
                                    event.amount > 0
                                        ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                                        : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                ]"
                                @click="openDetail(event)"
                                :title="event.description + ' ' + formatCurrency(event.amount)"
                            >
                                <i :class="event.type === 'recurring' ? 'pi pi-replay' : 'pi pi-circle-fill'" class="text-[8px]"></i>
                                <span class="truncate">{{ event.description }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <EmptyState v-if="events.length === 0" message="Keine geplanten Zahlungen. Erstelle Daueraufträge oder plane einzelne Zahlungen." icon="pi-calendar" />

        <!-- Event Detail Dialog -->
        <Dialog v-model:visible="showDetailDialog" :header="selectedEvent?.description" modal :style="{ width: '400px' }">
            <template v-if="selectedEvent">
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Betrag</span>
                        <span :class="['font-semibold', selectedEvent.amount > 0 ? 'text-green-600' : 'text-red-600']">
                            {{ formatCurrency(selectedEvent.amount) }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Datum</span>
                        <span>{{ new Date(selectedEvent.date).toLocaleDateString('de-DE') }}</span>
                    </div>
                    <div v-if="selectedEvent.category" class="flex justify-between">
                        <span class="text-sm text-gray-500">Kategorie</span>
                        <span>{{ selectedEvent.category }}</span>
                    </div>
                    <div v-if="selectedEvent.account" class="flex justify-between">
                        <span class="text-sm text-gray-500">Konto</span>
                        <span>{{ selectedEvent.account }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500">Typ</span>
                        <Tag :value="selectedEvent.type === 'recurring' ? 'Dauerauftrag' : 'Einmalig'" :severity="selectedEvent.type === 'recurring' ? 'info' : 'warn'" />
                    </div>
                    <div v-if="selectedEvent.notes" class="pt-2 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ selectedEvent.notes }}</p>
                    </div>
                </div>
                <div v-if="selectedEvent.type === 'scheduled'" class="flex gap-2 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <Button label="Erledigt" icon="pi pi-check" size="small" severity="success" @click="completePayment(selectedEvent.paymentId)" />
                    <Button label="Bearbeiten" icon="pi pi-pencil" size="small" severity="secondary" @click="editPayment(selectedEvent)" />
                    <Button icon="pi pi-trash" size="small" severity="danger" text @click="deletePayment(selectedEvent.paymentId)" />
                </div>
            </template>
        </Dialog>

        <!-- Create/Edit Dialog -->
        <Dialog v-model:visible="showCreateDialog" :header="editingPayment ? 'Zahlung bearbeiten' : 'Neue geplante Zahlung'" modal :style="{ width: '450px' }">
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Beschreibung *</label>
                    <InputText v-model="form.description" class="w-full" placeholder="z.B. Versicherung" />
                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag *</label>
                    <InputNumber v-model="form.amount" class="w-full" mode="currency" currency="EUR" locale="de-DE" placeholder="Negativ = Ausgabe" />
                    <small v-if="form.errors.amount" class="text-red-500">{{ form.errors.amount }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Datum *</label>
                    <DatePicker v-model="form.date" dateFormat="dd.mm.yy" class="w-full" showIcon />
                    <small v-if="form.errors.date" class="text-red-500">{{ form.errors.date }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Kategorie</label>
                    <TreeSelect v-model="selectedCategory" :options="categories" placeholder="Kategorie wählen" class="w-full" selectionMode="single" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Konto</label>
                    <Select v-model="form.account_id" :options="accounts" optionLabel="name" optionValue="id" placeholder="Konto wählen" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Notizen</label>
                    <Textarea v-model="form.notes" class="w-full" rows="2" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showCreateDialog = false" />
                    <Button type="submit" :label="editingPayment ? 'Speichern' : 'Erstellen'" icon="pi pi-check" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
