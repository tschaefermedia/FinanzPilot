<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import TreeSelect from 'primevue/treeselect';
import Checkbox from 'primevue/checkbox';

const { formatCurrency, formatDate } = useFormatters();

const props = defineProps({
    transactions: { type: Object, default: () => ({ data: [] }) },
    categories: { type: Array, default: () => [] },
});

const selectedIds = ref([]);
const bulkCategorySelection = ref(null);

function categorize(transactionId, val) {
    const categoryId = val ? Number(Object.keys(val)[0]) : null;
    if (!categoryId) return;

    router.put(`/imports/categorize/${transactionId}`, {
        category_id: categoryId,
    }, {
        preserveScroll: true,
    });
}

function bulkCategorize() {
    if (!bulkCategorySelection.value || selectedIds.value.length === 0) return;

    const categoryId = Number(Object.keys(bulkCategorySelection.value)[0]);

    router.post('/imports/bulk-categorize', {
        transaction_ids: selectedIds.value,
        category_id: categoryId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkCategorySelection.value = null;
        },
    });
}

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
</script>

<template>
    <AppLayout>
        <PageHeader title="Prüfwarteschlange">
            <span class="text-sm text-gray-500 dark:text-gray-400 mr-4">{{ transactions.total ?? transactions.data?.length ?? 0 }} unkategorisiert</span>
        </PageHeader>

        <!-- Bulk actions -->
        <div v-if="selectedIds.length > 0" class="bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700 p-4 mb-4 flex items-center gap-4">
            <span class="text-sm font-medium text-blue-700 dark:text-blue-400">{{ selectedIds.length }} ausgewählt</span>
            <TreeSelect
                v-model="bulkCategorySelection"
                :options="categories"
                placeholder="Kategorie wählen"
                class="w-64"
                selectionMode="single"
                filter
                filterPlaceholder="Suchen..."
            />
            <Button label="Zuweisen" size="small" @click="bulkCategorize" :disabled="!bulkCategorySelection" />
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <DataTable
                v-if="transactions.data && transactions.data.length > 0"
                :value="transactions.data"
                class="text-sm"
            >
                <Column style="width: 50px">
                    <template #body="{ data }">
                        <Checkbox :binary="true" :modelValue="isSelected(data.id)" @update:modelValue="toggleSelection(data.id)" />
                    </template>
                </Column>
                <Column field="date" header="Datum" style="width: 110px">
                    <template #body="{ data }">{{ formatDate(data.date) }}</template>
                </Column>
                <Column field="amount" header="Betrag" style="width: 120px">
                    <template #body="{ data }">
                        <span :class="data.amount >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                            {{ formatCurrency(data.amount) }}
                        </span>
                    </template>
                </Column>
                <Column field="description" header="Beschreibung" />
                <Column field="counterparty" header="Empfänger" style="width: 180px" headerClass="hidden md:table-cell" bodyClass="hidden md:table-cell" />
                <Column header="Kategorie" style="width: 220px">
                    <template #body="{ data }">
                        <TreeSelect
                            :key="data.id"
                            :modelValue="null"
                            @update:modelValue="(val) => categorize(data.id, val)"
                            :options="categories"
                            placeholder="Kategorie wählen"
                            class="w-full"
                            selectionMode="single"
                            filter
                            filterPlaceholder="Suchen..."
                        />
                    </template>
                </Column>
            </DataTable>
            <EmptyState v-else message="Alle Buchungen sind kategorisiert!" icon="pi-check-circle" />
        </div>
    </AppLayout>
</template>
