<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';

const { formatCurrency } = useFormatters();

const props = defineProps({
    month: { type: String, required: true },
    budgets: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ budget: 0, spent: 0, remaining: 0 }) },
    unbudgeted: { type: Array, default: () => [] },
});

const monthLabel = computed(() =>
    new Date(props.month + '-01').toLocaleDateString('de-DE', { month: 'long', year: 'numeric' })
);

function shiftMonth(delta) {
    const d = new Date(props.month + '-01');
    d.setMonth(d.getMonth() + delta);
    router.get('/budgets', { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, { preserveScroll: true });
}

function barColor(percent) {
    if (percent > 100) return 'bg-red-500';
    if (percent >= 80) return 'bg-amber-500';
    return 'bg-green-500';
}

const showDialog = ref(false);
const editing = ref(null);
const form = useForm({ category_id: null, budget_monthly: null });

function openAdd() {
    editing.value = null;
    form.reset();
    showDialog.value = true;
}

function openEdit(b) {
    editing.value = b;
    form.category_id = b.id;
    form.budget_monthly = b.budget;
    showDialog.value = true;
}

function save() {
    if (!form.category_id) return;
    form.put(`/budgets/${form.category_id}`, {
        preserveScroll: true,
        onSuccess: () => { showDialog.value = false; },
    });
}

function removeBudget(b) {
    router.put(`/budgets/${b.id}`, { budget_monthly: null }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Budgets">
            <div class="flex items-center gap-2">
                <Button icon="pi pi-chevron-left" text rounded size="small" @click="shiftMonth(-1)" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200 w-32 text-center capitalize">{{ monthLabel }}</span>
                <Button icon="pi pi-chevron-right" text rounded size="small" @click="shiftMonth(1)" />
                <Button label="Budget hinzufügen" icon="pi pi-plus" size="small" class="ml-2" @click="openAdd" />
            </div>
        </PageHeader>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Budgetiert</div>
                <div class="text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(totals.budget) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Ausgegeben</div>
                <div class="text-xl font-semibold text-gray-900 dark:text-white">{{ formatCurrency(totals.spent) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400">Verbleibend</div>
                <div :class="['text-xl font-semibold', totals.remaining < 0 ? 'text-red-600' : 'text-green-600']">
                    {{ formatCurrency(totals.remaining) }}
                </div>
            </div>
        </div>

        <div v-if="budgets.length > 0" class="space-y-3">
            <div
                v-for="b in budgets"
                :key="b.id"
                class="bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 p-4 group"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-sm text-gray-900 dark:text-white">
                        <i v-if="b.icon" :class="[b.icon, 'mr-1 text-gray-400']" />{{ b.name }}
                    </span>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                            {{ formatCurrency(b.spent) }} / {{ formatCurrency(b.budget) }}
                        </span>
                        <Button icon="pi pi-pencil" text rounded size="small" class="opacity-0 group-hover:opacity-100" @click="openEdit(b)" />
                        <Button icon="pi pi-trash" text rounded size="small" severity="danger" class="opacity-0 group-hover:opacity-100" @click="removeBudget(b)" />
                    </div>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div :class="[barColor(b.percent), 'h-full rounded-full transition-all']" :style="{ width: Math.min(100, b.percent) + '%' }" />
                </div>
                <div class="flex justify-between mt-1 text-xs">
                    <span class="text-gray-400 dark:text-gray-500">{{ b.percent }}%</span>
                    <span :class="b.remaining < 0 ? 'text-red-600' : 'text-gray-400 dark:text-gray-500'">
                        {{ b.remaining < 0 ? 'Überschritten um ' + formatCurrency(-b.remaining) : formatCurrency(b.remaining) + ' übrig' }}
                    </span>
                </div>
            </div>
        </div>

        <EmptyState v-else message="Noch keine Budgets festgelegt." icon="pi-chart-bar">
            <Button label="Budget hinzufügen" icon="pi pi-plus" size="small" @click="openAdd" />
        </EmptyState>

        <Dialog v-model:visible="showDialog" :header="editing ? 'Budget bearbeiten' : 'Budget hinzufügen'" modal class="w-full max-w-md">
            <form @submit.prevent="save" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Kategorie</label>
                    <Select
                        v-if="!editing"
                        v-model="form.category_id"
                        :options="unbudgeted"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Kategorie wählen"
                        class="w-full"
                        filter
                    />
                    <div v-else class="text-sm font-medium text-gray-900 dark:text-white">{{ editing.name }}</div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Monatliches Budget</label>
                    <InputNumber v-model="form.budget_monthly" mode="currency" currency="EUR" locale="de-DE" class="w-full" :min="0" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showDialog = false" />
                    <Button type="submit" label="Speichern" size="small" :loading="form.processing" :disabled="!form.category_id" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
