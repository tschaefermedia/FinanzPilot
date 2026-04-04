<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Checkbox from 'primevue/checkbox';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';

const props = defineProps({
    availableMonths: { type: Array, default: () => [] },
});

const germanMonths = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

const monthOptions = computed(() =>
    props.availableMonths.map(m => {
        const [year, month] = m.split('-');
        return { label: `${germanMonths[parseInt(month) - 1]} ${year}`, value: m };
    })
);

// Single month export
const selectedMonth = ref(props.availableMonths[0] || null);
const exportingMonth = ref(false);

function exportMonth() {
    if (!selectedMonth.value) return;
    exportingMonth.value = true;
    downloadPost('/export/month', { month: selectedMonth.value }, () => {
        exportingMonth.value = false;
    });
}

// Date range export
const dateFrom = ref(null);
const dateTo = ref(null);
const exportingRange = ref(false);

function formatDateForSubmit(date) {
    if (!date) return null;
    const d = new Date(date);
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function exportRange() {
    if (!dateFrom.value || !dateTo.value) return;
    exportingRange.value = true;
    downloadPost('/export/range', {
        date_from: formatDateForSubmit(dateFrom.value),
        date_to: formatDateForSubmit(dateTo.value),
    }, () => {
        exportingRange.value = false;
    });
}

// Batch export
const selectedBatchMonths = ref([]);
const exportingBatch = ref(false);

function toggleBatchMonth(month) {
    const idx = selectedBatchMonths.value.indexOf(month);
    if (idx >= 0) {
        selectedBatchMonths.value.splice(idx, 1);
    } else {
        selectedBatchMonths.value.push(month);
    }
}

function exportBatch() {
    if (selectedBatchMonths.value.length === 0) return;
    exportingBatch.value = true;
    downloadPost('/export/batch', { months: selectedBatchMonths.value }, () => {
        exportingBatch.value = false;
    });
}

/**
 * POST a JSON body and download the response as a file.
 * Can't use Inertia for file downloads.
 */
function downloadPost(url, data, onDone) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/octet-stream',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(data),
        credentials: 'same-origin',
    })
    .then(response => {
        if (!response.ok) throw new Error('Export fehlgeschlagen');
        const disposition = response.headers.get('Content-Disposition');
        let filename = 'export.xlsx';
        if (disposition) {
            const match = disposition.match(/filename="?([^";\n]+)"?/);
            if (match) filename = match[1];
        }
        return response.blob().then(blob => ({ blob, filename }));
    })
    .then(({ blob, filename }) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
    })
    .catch(err => {
        console.error(err);
        alert('Export fehlgeschlagen. Bitte erneut versuchen.');
    })
    .finally(() => {
        if (onDone) onDone();
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Export" />

        <div v-if="availableMonths.length === 0">
            <EmptyState message="Keine Buchungen vorhanden. Erstelle zuerst Buchungen, um sie zu exportieren." icon="pi-download" />
        </div>

        <template v-else>
            <TabView>
                <!-- Single month -->
                <TabPanel header="Einzelner Monat">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">Wähle einen Monat für den Export als Excel-Datei.</p>
                        <div class="flex items-end gap-4">
                            <div class="w-64">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Monat</label>
                                <Select v-model="selectedMonth" :options="monthOptions" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <Button
                                label="Exportieren"
                                icon="pi pi-download"
                                :loading="exportingMonth"
                                :disabled="!selectedMonth"
                                @click="exportMonth"
                            />
                        </div>
                    </div>
                </TabPanel>

                <!-- Date range -->
                <TabPanel header="Zeitraum">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">Wähle einen beliebigen Zeitraum für den Export.</p>
                        <div class="flex items-end gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Von</label>
                                <DatePicker v-model="dateFrom" dateFormat="dd.mm.yy" showIcon class="w-48" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Bis</label>
                                <DatePicker v-model="dateTo" dateFormat="dd.mm.yy" showIcon class="w-48" />
                            </div>
                            <Button
                                label="Exportieren"
                                icon="pi pi-download"
                                :loading="exportingRange"
                                :disabled="!dateFrom || !dateTo"
                                @click="exportRange"
                            />
                        </div>
                    </div>
                </TabPanel>

                <!-- Batch export -->
                <TabPanel header="Batch-Export">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">Wähle mehrere Monate. Jeder Monat wird als eigenes Blatt in einer Datei exportiert.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mb-6">
                            <div
                                v-for="opt in monthOptions"
                                :key="opt.value"
                                :class="[
                                    'flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors',
                                    selectedBatchMonths.includes(opt.value)
                                        ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                                ]"
                                @click="toggleBatchMonth(opt.value)"
                            >
                                <Checkbox
                                    :binary="true"
                                    :modelValue="selectedBatchMonths.includes(opt.value)"
                                    @update:modelValue="toggleBatchMonth(opt.value)"
                                />
                                <span class="text-sm">{{ opt.label }}</span>
                            </div>
                        </div>
                        <Button
                            :label="`${selectedBatchMonths.length} Monat(e) exportieren`"
                            icon="pi pi-download"
                            :loading="exportingBatch"
                            :disabled="selectedBatchMonths.length === 0"
                            @click="exportBatch"
                        />
                    </div>
                </TabPanel>
            </TabView>
        </template>
    </AppLayout>
</template>
