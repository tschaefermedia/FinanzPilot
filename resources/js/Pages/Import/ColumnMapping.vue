<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Checkbox from 'primevue/checkbox';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

const props = defineProps({
    filePath: { type: String, required: true },
    fileName: { type: String, required: true },
    delimiter: { type: String, default: ',' },
    preview: { type: Array, default: () => [] },
    mappings: { type: Array, default: () => [] },
});

const headers = computed(() => props.preview[0] || []);
const dataRows = computed(() => props.preview.slice(1));

const columnOptions = computed(() =>
    headers.value.map((h, i) => ({ label: `${i}: ${h}`, value: i }))
);

const form = useForm({
    file_path: props.filePath,
    delimiter: props.delimiter,
    has_header: true,
    columns: {
        date: null,
        amount: null,
        description: null,
        counterparty: null,
        reference: null,
    },
    date_format: 'd.m.Y',
    save_mapping: false,
    mapping_name: '',
});

function loadMapping(mapping) {
    const config = mapping.column_mapping;
    form.columns = config.columns || {};
    form.delimiter = config.delimiter || ',';
    form.has_header = config.has_header ?? true;
    form.date_format = config.date_format || 'd.m.Y';
}

function submit() {
    form.post('/imports/parse-generic');
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="`Spalten zuordnen: ${fileName}`" />

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 mb-6">
            <!-- Saved mappings -->
            <div v-if="mappings.length > 0" class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Gespeicherte Zuordnung laden</label>
                <div class="flex gap-2 flex-wrap">
                    <Button
                        v-for="m in mappings"
                        :key="m.id"
                        :label="m.name"
                        size="small"
                        severity="secondary"
                        @click="loadMapping(m)"
                    />
                </div>
            </div>

            <!-- Column mapping form -->
            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Datum *</label>
                        <Select v-model="form.columns.date" :options="columnOptions" optionLabel="label" optionValue="value" placeholder="Spalte wählen" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag *</label>
                        <Select v-model="form.columns.amount" :options="columnOptions" optionLabel="label" optionValue="value" placeholder="Spalte wählen" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Beschreibung *</label>
                        <Select v-model="form.columns.description" :options="columnOptions" optionLabel="label" optionValue="value" placeholder="Spalte wählen" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Empfänger</label>
                        <Select v-model="form.columns.counterparty" :options="columnOptions" optionLabel="label" optionValue="value" placeholder="Spalte wählen" class="w-full" showClear />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Referenz</label>
                        <Select v-model="form.columns.reference" :options="columnOptions" optionLabel="label" optionValue="value" placeholder="Spalte wählen" class="w-full" showClear />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Datumsformat</label>
                        <InputText v-model="form.date_format" class="w-full" placeholder="d.m.Y" />
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.has_header" :binary="true" inputId="hasHeader" />
                        <label for="hasHeader" class="text-sm text-gray-700 dark:text-gray-200">Erste Zeile ist Kopfzeile</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox v-model="form.save_mapping" :binary="true" inputId="saveMapping" />
                        <label for="saveMapping" class="text-sm text-gray-700 dark:text-gray-200">Zuordnung speichern</label>
                    </div>
                    <div v-if="form.save_mapping">
                        <InputText v-model="form.mapping_name" placeholder="Name der Zuordnung" class="w-48" />
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <Button type="submit" label="Weiter zur Vorschau" icon="pi pi-arrow-right" :loading="form.processing" />
                </div>
            </form>
        </div>

        <!-- Preview table -->
        <div v-if="preview.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Dateivorschau (erste {{ dataRows.length }} Zeilen)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th v-for="(h, i) in headers" :key="i" class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-400">
                                {{ i }}: {{ h }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, ri) in dataRows" :key="ri" class="border-t border-gray-100 dark:border-gray-700">
                            <td v-for="(cell, ci) in row" :key="ci" class="px-3 py-2 text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                {{ cell }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
