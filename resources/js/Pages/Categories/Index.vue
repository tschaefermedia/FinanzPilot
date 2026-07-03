<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useConfirm } from 'primevue/useconfirm';
import TreeTable from 'primevue/treetable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const confirm = useConfirm();
const { formatCurrency } = useFormatters();

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const showDialog = ref(false);
const editingCategory = ref(null);

const form = useForm({
    name: '',
    type: 'expense',
    parent_id: null,
    budget_monthly: null,
});

const typeOptions = [
    { label: 'Einnahme', value: 'income' },
    { label: 'Ausgabe', value: 'expense' },
    { label: 'Übertragung', value: 'transfer' },
];

function typeSeverity(type) {
    return { income: 'success', expense: 'danger', transfer: 'info' }[type] || 'secondary';
}

function typeLabel(type) {
    return { income: 'Einnahme', expense: 'Ausgabe', transfer: 'Übertragung' }[type] || type;
}

function openCreate(parentId = null) {
    editingCategory.value = null;
    form.reset();
    form.parent_id = parentId;
    showDialog.value = true;
}

function openEdit(category) {
    editingCategory.value = category;
    form.name = category.name;
    form.type = category.type;
    form.parent_id = category.parent_id || null;
    form.budget_monthly = category.budget_monthly ?? null;
    showDialog.value = true;
}

function submit() {
    if (editingCategory.value) {
        form.put(`/categories/${editingCategory.value.id}`, {
            onSuccess: () => { showDialog.value = false; form.reset(); form.clearErrors(); },
        });
    } else {
        form.post('/categories', {
            onSuccess: () => { showDialog.value = false; form.reset(); form.clearErrors(); },
        });
    }
}

function deleteCategory(id) {
    confirm.require({
        message: 'Kategorie wirklich löschen?',
        header: 'Kategorie löschen',
        icon: 'pi pi-trash',
        acceptLabel: 'Löschen',
        rejectLabel: 'Abbrechen',
        acceptClass: 'p-button-danger',
        accept: () => router.delete(`/categories/${id}`),
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Kategorien">
            <a href="/categories/analysis" class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:underline mr-3">
                <i class="pi pi-chart-pie text-xs"></i> Analyse
            </a>
            <Button label="Neue Kategorie" icon="pi pi-plus" size="small" @click="openCreate()" />
        </PageHeader>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <TreeTable v-if="categories.length > 0" :value="categories" class="text-sm">
                <Column field="name" header="Name" expander>
                    <template #body="{ node }">
                        <span class="font-medium">{{ node.data.name }}</span>
                    </template>
                </Column>
                <Column field="type" header="Typ" style="width: 120px">
                    <template #body="{ node }">
                        <Tag :value="typeLabel(node.data.type)" :severity="typeSeverity(node.data.type)" />
                    </template>
                </Column>
                <Column field="transactionsCount" header="Buchungen" style="width: 120px">
                    <template #body="{ node }">
                        <span class="text-gray-500 dark:text-gray-400">{{ node.data.transactionsCount ?? 0 }}</span>
                    </template>
                </Column>
                <Column field="monthlyAverage" header="Mtl. Durchschnitt" style="width: 150px">
                    <template #body="{ node }">
                        <span v-if="node.data.monthlyAverage" class="text-gray-500 dark:text-gray-400">{{ formatCurrency(node.data.monthlyAverage) }}</span>
                        <span v-else class="text-gray-400">—</span>
                    </template>
                </Column>
                <Column header="Aktionen" style="width: 150px">
                    <template #body="{ node }">
                        <div class="flex gap-1">
                            <Button icon="pi pi-plus" text rounded size="small" severity="success" @click="openCreate(node.data.id)" title="Unterkategorie erstellen" />
                            <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(node.data)" />
                            <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteCategory(node.data.id)" />
                        </div>
                    </template>
                </Column>
            </TreeTable>
            <EmptyState v-else message="Keine Kategorien vorhanden." icon="pi-tags">
                <Button label="Erste Kategorie erstellen" icon="pi pi-plus" size="small" @click="openCreate(null)" />
            </EmptyState>
        </div>

        <Dialog v-model:visible="showDialog" :header="editingCategory ? 'Kategorie bearbeiten' : 'Neue Kategorie'" modal class="w-full max-w-md">
            <form @submit.prevent="submit" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="Kategoriename" />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Typ</label>
                    <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>

                <div v-if="form.type === 'expense'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Monatliches Budget</label>
                    <InputNumber v-model="form.budget_monthly" mode="currency" currency="EUR" locale="de-DE" :min="0" class="w-full" inputClass="w-full" placeholder="Optional" />
                    <small v-if="form.errors.budget_monthly" class="text-red-500">{{ form.errors.budget_monthly }}</small>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showDialog = false" />
                    <Button type="submit" label="Speichern" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
