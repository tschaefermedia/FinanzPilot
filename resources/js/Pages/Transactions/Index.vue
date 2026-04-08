<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import DatePicker from 'primevue/datepicker';
import TreeSelect from 'primevue/treeselect';
import ToggleSwitch from 'primevue/toggleswitch';

const { formatCurrency, formatDate, formatDateForSubmit } = useFormatters();
const confirm = useConfirm();
const toast = useToast();

const props = defineProps({
    transactions: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    categories: { type: Array, default: () => [] },
    categoryTree: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    accounts: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const categoryFilter = ref(props.filters.category_id ? Number(props.filters.category_id) : null);
const accountFilter = ref(props.filters.account_id || null);

const categoryOptions = props.categories.map(c => ({
    id: c.id,
    name: c.parent ? `${c.parent.name} → ${c.name}` : c.name,
})).sort((a, b) => a.name.localeCompare(b.name, 'de'));

const selectedIds = ref([]);
const bulkAccountId = ref(null);

const sortFieldMap = { date: 'date', amount: 'amount', description: 'description' };
const sortField = ref(props.filters.sort_field || 'date');
const sortOrder = ref(props.filters.sort_order === 'asc' ? 1 : -1);

// Recurring template dialog
const showRecurringDialog = ref(false);
const recurringSubmitting = ref(false);
const recurringForm = ref({
    description: '',
    amount: null,
    category_id: null,
    account_id: null,
    frequency: 'monthly',
    next_due_date: null,
    is_active: true,
    auto_generate: false,
});

const selectedRecurringCategory = computed({
    get: () => recurringForm.value.category_id ? { [recurringForm.value.category_id]: true } : null,
    set: (val) => {
        recurringForm.value.category_id = val ? Number(Object.keys(val)[0]) : null;
    },
});

const frequencyOptions = [
    { label: 'Wöchentlich', value: 'weekly' },
    { label: 'Monatlich', value: 'monthly' },
    { label: 'Vierteljährlich', value: 'quarterly' },
    { label: 'Jährlich', value: 'yearly' },
];

function openRecurringDialog(transaction) {
    recurringForm.value = {
        description: transaction.description,
        amount: parseFloat(transaction.amount),
        category_id: transaction.category?.id ?? null,
        account_id: transaction.account?.id ?? null,
        frequency: 'monthly',
        next_due_date: new Date(transaction.date),
        is_active: true,
        auto_generate: false,
    };
    showRecurringDialog.value = true;
}

function submitRecurring() {
    recurringSubmitting.value = true;
    const data = {
        ...recurringForm.value,
        next_due_date: formatDateForSubmit(recurringForm.value.next_due_date),
    };

    router.post('/recurring', data, {
        preserveScroll: true,
        onSuccess: () => {
            showRecurringDialog.value = false;
            toast.add({ severity: 'success', summary: 'Erfolg', detail: 'Dauerauftrag erstellt.', life: 3000 });
        },
        onFinish: () => {
            recurringSubmitting.value = false;
        },
    });
}

function buildParams(overrides = {}) {
    return {
        search: search.value || undefined,
        category_id: categoryFilter.value || undefined,
        account_id: accountFilter.value || undefined,
        sort_field: sortField.value,
        sort_order: sortOrder.value === 1 ? 'asc' : 'desc',
        ...overrides,
    };
}

function applySearch() {
    router.get('/transactions', buildParams({ page: undefined }), { preserveState: true, preserveScroll: true });
}

function onSort(event) {
    const field = sortFieldMap[event.sortField] || event.sortField;
    sortField.value = field;
    sortOrder.value = event.sortOrder;
    router.get('/transactions', buildParams({ page: undefined }), { preserveState: true, preserveScroll: true });
}

function onPage(event) {
    router.get('/transactions', buildParams({ page: event.page + 1 }), { preserveState: true, preserveScroll: true });
}

function sourceLabel(source) {
    const labels = { manual: 'Manuell', sparkasse: 'Sparkasse', paypal: 'PayPal', recurring: 'Dauerauftrag' };
    return labels[source] || source;
}

const accountFilterOptions = [
    { name: 'Ohne Konto', id: 'none' },
    ...props.accounts,
];

function toggleSelection(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function isSelected(id) {
    return selectedIds.value.includes(id);
}

function bulkUpdateAccount() {
    if (selectedIds.value.length === 0) return;

    router.post('/transactions/bulk-update-account', {
        transaction_ids: selectedIds.value,
        account_id: bulkAccountId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkAccountId.value = null;
        },
    });
}

function deleteTransaction(id) {
    confirm.require({
        message: 'Buchung wirklich löschen?',
        header: 'Buchung löschen',
        icon: 'pi pi-trash',
        acceptLabel: 'Löschen',
        rejectLabel: 'Abbrechen',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/transactions/${id}`),
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Buchungen">
            <Link href="/transactions/create">
                <Button label="Neue Buchung" icon="pi pi-plus" size="small" />
            </Link>
        </PageHeader>

        <!-- Bulk actions -->
        <div v-if="selectedIds.length > 0" class="bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700 p-4 mb-4 flex items-center gap-4">
            <span class="text-sm font-medium text-blue-700 dark:text-blue-400">{{ selectedIds.length }} ausgewählt</span>
            <Select
                v-model="bulkAccountId"
                :options="accounts"
                optionLabel="name"
                optionValue="id"
                placeholder="Konto wählen"
                class="w-64"
                showClear
            />
            <Button label="Zuweisen" size="small" @click="bulkUpdateAccount" :disabled="bulkAccountId === null" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex gap-3">
                    <InputText v-model="search" placeholder="Suchen..." class="w-full max-w-sm" @keyup.enter="applySearch" />
                    <Select
                        v-model="categoryFilter"
                        :options="categoryOptions"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Alle Kategorien"
                        class="w-52"
                        showClear
                        filter
                        @change="applySearch"
                    />
                    <Select
                        v-model="accountFilter"
                        :options="accountFilterOptions"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Alle Konten"
                        class="w-48"
                        showClear
                        @change="applySearch"
                    />
                    <Button label="Suchen" icon="pi pi-search" size="small" severity="secondary" @click="applySearch" />
                </div>
            </div>

            <DataTable
                v-if="transactions.data && transactions.data.length > 0"
                :value="transactions.data"
                stripedRows
                lazy
                paginator
                :rows="25"
                :totalRecords="transactions.meta?.total ?? transactions.total ?? 0"
                :first="((transactions.meta?.current_page ?? transactions.current_page ?? 1) - 1) * 25"
                :sortField="sortField"
                :sortOrder="sortOrder"
                @sort="onSort"
                @page="onPage"
                @row-click="(e) => router.visit(`/transactions/${e.data.id}/edit`)"
                class="text-sm cursor-pointer"
            >
                <Column style="width: 50px">
                    <template #body="{ data }">
                        <div @click.stop>
                            <Checkbox :binary="true" :modelValue="isSelected(data.id)" @update:modelValue="toggleSelection(data.id)" />
                        </div>
                    </template>
                </Column>
                <Column field="date" header="Datum" sortable style="width: 120px">
                    <template #body="{ data }">{{ formatDate(data.date) }}</template>
                </Column>
                <Column field="amount" header="Betrag" sortable style="width: 130px">
                    <template #body="{ data }">
                        <span :class="data.amount >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                            {{ formatCurrency(data.amount) }}
                        </span>
                    </template>
                </Column>
                <Column field="description" header="Beschreibung" />
                <Column field="counterparty" header="Empfänger" headerClass="hidden md:table-cell" bodyClass="hidden md:table-cell" />
                <Column field="category.name" header="Kategorie" style="width: 150px">
                    <template #body="{ data }">
                        <Tag v-if="data.category" :value="data.category.name" severity="info" />
                        <span v-else class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                    </template>
                </Column>
                <Column field="source" header="Quelle" style="width: 120px" headerClass="hidden lg:table-cell" bodyClass="hidden lg:table-cell">
                    <template #body="{ data }">
                        <span class="text-gray-500 dark:text-gray-400 text-xs">{{ sourceLabel(data.source) }}</span>
                    </template>
                </Column>
                <Column header="Konto" style="width: 130px" headerClass="hidden lg:table-cell" bodyClass="hidden lg:table-cell">
                    <template #body="{ data }">
                        <span v-if="data.account" class="text-xs text-gray-500 dark:text-gray-400">{{ data.account.name }}</span>
                        <span v-else class="text-gray-300 dark:text-gray-500 text-xs">—</span>
                    </template>
                </Column>
                <Column style="width: 90px">
                    <template #body="{ data }">
                        <div @click.stop class="flex gap-1">
                            <Button icon="pi pi-replay" text rounded size="small" severity="secondary" title="Als Dauerauftrag erstellen" @click="openRecurringDialog(data)" />
                            <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteTransaction(data.id)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <EmptyState v-else message="Keine Buchungen vorhanden." icon="pi-list">
                <Link href="/transactions/create">
                    <Button label="Erste Buchung erstellen" icon="pi pi-plus" size="small" />
                </Link>
            </EmptyState>
        </div>

        <!-- Create Recurring Template Dialog -->
        <Dialog v-model:visible="showRecurringDialog" header="Dauerauftrag erstellen" modal :style="{ width: '450px' }">
            <form @submit.prevent="submitRecurring" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Beschreibung</label>
                    <InputText v-model="recurringForm.description" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag</label>
                    <InputNumber v-model="recurringForm.amount" class="w-full" mode="currency" currency="EUR" locale="de-DE" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Kategorie</label>
                    <TreeSelect v-model="selectedRecurringCategory" :options="categoryTree" placeholder="Kategorie wählen" class="w-full" selectionMode="single" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Konto</label>
                    <Select v-model="recurringForm.account_id" :options="accounts" optionLabel="name" optionValue="id" placeholder="Kein Konto" class="w-full" showClear />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Frequenz</label>
                        <Select v-model="recurringForm.frequency" :options="frequencyOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Nächstes Datum</label>
                        <DatePicker v-model="recurringForm.next_due_date" dateFormat="dd.mm.yy" class="w-full" showIcon />
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <ToggleSwitch v-model="recurringForm.is_active" />
                        Aktiv
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <ToggleSwitch v-model="recurringForm.auto_generate" />
                        Automatisch erstellen
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showRecurringDialog = false" />
                    <Button type="submit" label="Dauerauftrag erstellen" icon="pi pi-replay" size="small" :loading="recurringSubmitting" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
