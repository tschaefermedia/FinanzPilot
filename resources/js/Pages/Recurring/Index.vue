<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import TreeSelect from 'primevue/treeselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';

const { formatCurrency, formatDate } = useFormatters();

const props = defineProps({
    templates: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const showDialog = ref(false);
const editingTemplate = ref(null);

const form = useForm({
    description: '',
    amount: null,
    category_id: null,
    frequency: 'monthly',
    next_due_date: new Date(),
    is_active: true,
    auto_generate: false,
    account_id: null,
});

const frequencyOptions = [
    { label: 'Wöchentlich', value: 'weekly' },
    { label: 'Monatlich', value: 'monthly' },
    { label: 'Vierteljährlich', value: 'quarterly' },
    { label: 'Jährlich', value: 'yearly' },
];

function frequencyLabel(freq) {
    return { weekly: 'Wöchentlich', monthly: 'Monatlich', quarterly: 'Vierteljährlich', yearly: 'Jährlich' }[freq] || freq;
}

const selectedCategory = computed({
    get: () => form.category_id ? { [form.category_id]: true } : null,
    set: (val) => {
        form.category_id = val ? Number(Object.keys(val)[0]) : null;
    },
});

function openCreate() {
    editingTemplate.value = null;
    form.reset();
    form.next_due_date = new Date();
    form.is_active = true;
    form.frequency = 'monthly';
    showDialog.value = true;
}

function openEdit(template) {
    editingTemplate.value = template;
    form.description = template.description;
    form.amount = parseFloat(template.amount);
    form.category_id = template.category_id;
    form.frequency = template.frequency;
    form.next_due_date = new Date(template.next_due_date);
    form.is_active = template.is_active;
    form.auto_generate = template.auto_generate;
    form.account_id = template.account_id;
    showDialog.value = true;
}

function formatDateForSubmit(date) {
    if (!date) return null;
    const d = new Date(date);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function submit() {
    const data = {
        ...form.data(),
        next_due_date: formatDateForSubmit(form.next_due_date),
    };

    if (editingTemplate.value) {
        form.transform(() => data).put(`/recurring/${editingTemplate.value.id}`, {
            onSuccess: () => { showDialog.value = false; },
        });
    } else {
        form.transform(() => data).post('/recurring', {
            onSuccess: () => { showDialog.value = false; },
        });
    }
}

function deleteTemplate(id) {
    if (confirm('Dauerauftrag wirklich löschen?')) {
        router.delete(`/recurring/${id}`);
    }
}

function generateNow(id) {
    router.post(`/recurring/${id}/generate`);
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Daueraufträge">
            <Button label="Neuer Dauerauftrag" icon="pi pi-plus" size="small" @click="openCreate" />
        </PageHeader>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <DataTable v-if="templates.length > 0" :value="templates" class="text-sm">
                <Column field="description" header="Beschreibung">
                    <template #body="{ data }">
                        <div>
                            <span class="font-medium">{{ data.description }}</span>
                            <span v-if="!data.is_active" class="ml-2 text-xs text-gray-400 dark:text-gray-500">(inaktiv)</span>
                        </div>
                    </template>
                </Column>
                <Column field="amount" header="Betrag" style="width: 130px">
                    <template #body="{ data }">
                        <span :class="data.amount >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                            {{ formatCurrency(data.amount) }}
                        </span>
                    </template>
                </Column>
                <Column header="Kategorie" style="width: 140px">
                    <template #body="{ data }">
                        <Tag v-if="data.category" :value="data.category.name" severity="info" />
                        <span v-else class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                    </template>
                </Column>
                <Column field="frequency" header="Frequenz" style="width: 130px">
                    <template #body="{ data }">{{ frequencyLabel(data.frequency) }}</template>
                </Column>
                <Column field="next_due_date" header="Nächste Fälligkeit" style="width: 140px">
                    <template #body="{ data }">{{ formatDate(data.next_due_date) }}</template>
                </Column>
                <Column header="Auto" style="width: 60px">
                    <template #body="{ data }">
                        <i :class="data.auto_generate ? 'pi pi-check-circle text-green-500' : 'pi pi-circle text-gray-300 dark:text-gray-500'" />
                    </template>
                </Column>
                <Column header="Konto" style="width: 130px">
                    <template #body="{ data }">
                        <span v-if="data.account" class="text-xs text-gray-500 dark:text-gray-400">{{ data.account.name }}</span>
                        <span v-else class="text-gray-300 dark:text-gray-500 text-xs">—</span>
                    </template>
                </Column>
                <Column header="Aktionen" style="width: 150px">
                    <template #body="{ data }">
                        <div class="flex gap-1">
                            <Button icon="pi pi-play" text rounded size="small" severity="success" @click="generateNow(data.id)" title="Jetzt ausführen" />
                            <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteTemplate(data.id)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <EmptyState v-else message="Keine Daueraufträge vorhanden." icon="pi-replay" />
        </div>

        <Dialog v-model:visible="showDialog" :header="editingTemplate ? 'Dauerauftrag bearbeiten' : 'Neuer Dauerauftrag'" modal class="w-full max-w-lg">
            <form @submit.prevent="submit" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Beschreibung</label>
                    <InputText v-model="form.description" class="w-full" placeholder="z.B. Miete, Netflix, Gehalt" />
                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag</label>
                    <InputNumber v-model="form.amount" mode="currency" currency="EUR" locale="de-DE" class="w-full" />
                    <small class="text-gray-400 dark:text-gray-500">Positiv = Einnahme, Negativ = Ausgabe</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Kategorie</label>
                    <TreeSelect v-model="selectedCategory" :options="categories" placeholder="Kategorie wählen" class="w-full" selectionMode="single" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Konto</label>
                    <Select
                        v-model="form.account_id"
                        :options="accounts"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Kein Konto"
                        class="w-full"
                        showClear
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Frequenz</label>
                        <Select v-model="form.frequency" :options="frequencyOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Nächste Fälligkeit</label>
                        <DatePicker v-model="form.next_due_date" dateFormat="dd.mm.yy" showIcon class="w-full" />
                    </div>
                </div>

                <div class="flex items-center gap-6 pt-2">
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.is_active" />
                        <label class="text-sm text-gray-700 dark:text-gray-200">Aktiv</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.auto_generate" />
                        <label class="text-sm text-gray-700 dark:text-gray-200">Automatisch erstellen</label>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showDialog = false" />
                    <Button type="submit" label="Speichern" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
