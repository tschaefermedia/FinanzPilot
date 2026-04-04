<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import TreeSelect from 'primevue/treeselect';
import Checkbox from 'primevue/checkbox';
import Select from 'primevue/select';

const { formatCurrency, formatDate } = useFormatters();

const props = defineProps({
    transactions: { type: Array, default: () => [] },
    filename: { type: String, required: true },
    sourceType: { type: String, required: true },
    storagePath: { type: String, required: true },
    totalCount: { type: Number, default: 0 },
    duplicateCount: { type: Number, default: 0 },
    categorizedCount: { type: Number, default: 0 },
    categories: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const localTransactions = ref(props.transactions.map(t => ({ ...t })));

const selectedCount = computed(() => localTransactions.value.filter(t => t.selected).length);

function toggleAll(checked) {
    localTransactions.value.forEach(t => {
        if (!t.is_duplicate) t.selected = checked;
    });
}

function setCategory(index, val) {
    const catId = val ? Number(Object.keys(val)[0]) : null;
    localTransactions.value[index].category_id = catId;
}

function getCategorySelection(index) {
    const catId = localTransactions.value[index].category_id;
    return catId ? { [catId]: true } : null;
}

function confidenceSeverity(confidence) {
    if (confidence >= 0.8) return 'success';
    if (confidence >= 0.5) return 'warn';
    return 'danger';
}

function confidenceLabel(confidence, matchType) {
    if (matchType === 'none') return 'Keine Regel';
    const pct = Math.round(confidence * 100);
    return `${pct}%`;
}

const selectedAccountId = ref(null);

const submitting = ref(false);

function commitImport() {
    const selected = localTransactions.value
        .filter(t => t.selected)
        .map(t => ({
            date: t.date,
            amount: t.amount,
            description: t.description,
            counterparty: t.counterparty,
            reference: t.reference,
            hash: t.hash,
            category_id: t.category_id,
        }));

    if (selected.length === 0) return;

    submitting.value = true;

    router.post('/imports/commit', {
        filename: props.filename,
        source_type: props.sourceType,
        storage_path: props.storagePath,
        transactions: selected,
        account_id: selectedAccountId.value,
    }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="`Vorschau: ${filename}`">
            <Button
                :label="`${selectedCount} Buchungen importieren`"
                icon="pi pi-check"
                :loading="submitting"
                :disabled="selectedCount === 0"
                @click="commitImport"
            />
        </PageHeader>

        <!-- Stats bar -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalCount }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Gesamt</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4 text-center">
                <p class="text-2xl font-bold text-orange-500">{{ duplicateCount }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Duplikate</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ categorizedCount }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Kategorisiert</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-4 mb-6 flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Importieren in Konto:</label>
            <Select
                v-model="selectedAccountId"
                :options="accounts"
                optionLabel="name"
                optionValue="id"
                placeholder="Kein Konto"
                class="w-64"
                showClear
            />
        </div>

        <!-- Transaction table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <DataTable :value="localTransactions" class="text-sm" scrollable scrollHeight="600px">
                <Column style="width: 50px" header="">
                    <template #header>
                        <Checkbox :binary="true" @update:modelValue="toggleAll" />
                    </template>
                    <template #body="{ data }">
                        <Checkbox v-model="data.selected" :binary="true" :disabled="data.is_duplicate" />
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
                <Column field="description" header="Beschreibung" style="min-width: 200px" />
                <Column field="counterparty" header="Empfänger" style="min-width: 150px" />
                <Column header="Kategorie" style="width: 200px">
                    <template #body="{ data, index }">
                        <TreeSelect
                            :modelValue="getCategorySelection(index)"
                            @update:modelValue="(val) => setCategory(index, val)"
                            :options="categories"
                            placeholder="—"
                            class="w-full text-xs"
                            selectionMode="single"
                        />
                    </template>
                </Column>
                <Column header="Konfidenz" style="width: 100px">
                    <template #body="{ data }">
                        <Tag
                            :value="confidenceLabel(data.confidence, data.match_type)"
                            :severity="confidenceSeverity(data.confidence)"
                            v-if="data.match_type !== 'none'"
                        />
                    </template>
                </Column>
                <Column header="Status" style="width: 100px">
                    <template #body="{ data }">
                        <Tag v-if="data.is_duplicate" value="Duplikat" severity="warn" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AppLayout>
</template>
