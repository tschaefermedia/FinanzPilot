<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import Checkbox from 'primevue/checkbox';

const { formatCurrency, formatDate } = useFormatters();
const confirm = useConfirm();

const props = defineProps({
    transactions: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    accounts: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const accountFilter = ref(props.filters.account_id || null);

const selectedIds = ref([]);
const bulkAccountId = ref(null);

const sortFieldMap = { date: 'date', amount: 'amount', description: 'description' };
const sortField = ref(props.filters.sort_field || 'date');
const sortOrder = ref(props.filters.sort_order === 'asc' ? 1 : -1);

function buildParams(overrides = {}) {
    return {
        search: search.value || undefined,
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
                <Column style="width: 60px">
                    <template #body="{ data }">
                        <div @click.stop>
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
    </AppLayout>
</template>
