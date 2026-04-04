<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Select from 'primevue/select';

const { formatCurrency, formatDate } = useFormatters();

const props = defineProps({
    transactions: { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    accounts: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const accountFilter = ref(props.filters.account_id || null);

function applySearch() {
    router.get('/transactions', {
        search: search.value,
        account_id: accountFilter.value,
    }, { preserveState: true, preserveScroll: true });
}

function sourceLabel(source) {
    const labels = { manual: 'Manuell', sparkasse: 'Sparkasse', paypal: 'PayPal', recurring: 'Dauerauftrag' };
    return labels[source] || source;
}

function deleteTransaction(id) {
    if (confirm('Buchung wirklich löschen?')) {
        router.delete(`/transactions/${id}`);
    }
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Buchungen">
            <Link href="/transactions/create">
                <Button label="Neue Buchung" icon="pi pi-plus" size="small" />
            </Link>
        </PageHeader>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex gap-3">
                    <InputText v-model="search" placeholder="Suchen..." class="w-full max-w-sm" @keyup.enter="applySearch" />
                    <Select
                        v-model="accountFilter"
                        :options="accounts"
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
                class="text-sm"
            >
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
                <Column field="counterparty" header="Empfänger" />
                <Column field="category.name" header="Kategorie" style="width: 150px">
                    <template #body="{ data }">
                        <Tag v-if="data.category" :value="data.category.name" severity="info" />
                        <span v-else class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                    </template>
                </Column>
                <Column field="source" header="Quelle" style="width: 120px">
                    <template #body="{ data }">
                        <span class="text-gray-500 dark:text-gray-400 text-xs">{{ sourceLabel(data.source) }}</span>
                    </template>
                </Column>
                <Column header="Konto" style="width: 130px">
                    <template #body="{ data }">
                        <span v-if="data.account" class="text-xs text-gray-500 dark:text-gray-400">{{ data.account.name }}</span>
                        <span v-else class="text-gray-300 dark:text-gray-500 text-xs">—</span>
                    </template>
                </Column>
                <Column style="width: 100px">
                    <template #body="{ data }">
                        <div class="flex gap-1">
                            <Link :href="`/transactions/${data.id}/edit`">
                                <Button icon="pi pi-pencil" text rounded size="small" />
                            </Link>
                            <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteTransaction(data.id)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <EmptyState v-else message="Keine Buchungen vorhanden." icon="pi-list" />
        </div>
    </AppLayout>
</template>
