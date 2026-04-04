<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import FileUpload from 'primevue/fileupload';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';

const { formatDate } = useFormatters();

const props = defineProps({
    batches: { type: Array, default: () => [] },
    mappings: { type: Array, default: () => [] },
});

const form = useForm({
    file: null,
});

const fileInput = ref(null);

function onFileSelect(event) {
    const file = event.target?.files?.[0] || event.files?.[0];
    if (file) {
        form.file = file;
        form.post('/imports/upload', {
            forceFormData: true,
        });
    }
}

function statusSeverity(status) {
    return { pending: 'warn', reviewed: 'info', committed: 'success' }[status] || 'secondary';
}

function statusLabel(status) {
    return { pending: 'Ausstehend', reviewed: 'Geprüft', committed: 'Importiert' }[status] || status;
}

function sourceLabel(type) {
    return { sparkasse: 'Sparkasse', paypal: 'PayPal', generic: 'Manuell' }[type] || type;
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Import">
            <Link href="/imports/review">
                <Button label="Prüfwarteschlange" icon="pi pi-check-square" size="small" severity="secondary" />
            </Link>
        </PageHeader>

        <!-- Upload area -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-8 mb-6">
            <div class="text-center">
                <i class="pi pi-upload text-4xl text-gray-300 dark:text-gray-500 mb-4"></i>
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">CSV-Datei importieren</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Sparkasse CSV-CAMT oder andere CSV-Dateien</p>

                <label class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700 transition-colors">
                    <i class="pi pi-upload"></i>
                    <span>Datei auswählen</span>
                    <input type="file" accept=".csv,.txt" class="hidden" @change="onFileSelect" ref="fileInput" />
                </label>

                <div v-if="form.processing" class="mt-4">
                    <i class="pi pi-spin pi-spinner text-blue-600 dark:text-blue-400"></i>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">Datei wird verarbeitet...</span>
                </div>

                <div v-if="form.errors.file" class="mt-2 text-red-500 text-sm">{{ form.errors.file }}</div>
            </div>
        </div>

        <!-- Recent imports -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Letzte Importe</h3>
            </div>

            <DataTable v-if="batches.length > 0" :value="batches" class="text-sm">
                <Column field="filename" header="Datei" />
                <Column field="source_type" header="Quelle" style="width: 120px">
                    <template #body="{ data }">{{ sourceLabel(data.source_type) }}</template>
                </Column>
                <Column field="row_count" header="Zeilen" style="width: 80px" />
                <Column field="status" header="Status" style="width: 120px">
                    <template #body="{ data }">
                        <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
                    </template>
                </Column>
                <Column field="created_at" header="Datum" style="width: 120px">
                    <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
                </Column>
            </DataTable>
            <EmptyState v-else message="Noch keine Importe vorhanden." icon="pi-upload" />
        </div>
    </AppLayout>
</template>
