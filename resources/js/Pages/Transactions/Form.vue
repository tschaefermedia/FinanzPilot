<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const { formatDateForSubmit } = useFormatters();
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import DatePicker from 'primevue/datepicker';
import CategoryTreeSelect from '@/Components/CategoryTreeSelect.vue';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Select from 'primevue/select';

const props = defineProps({
    transaction: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.transaction);
const isTransferLeg = computed(() => !!props.transaction?.transfer_counterpart);

const form = useForm({
    date: props.transaction?.date ? new Date(props.transaction.date) : new Date(),
    amount: props.transaction?.amount ?? null,
    description: props.transaction?.description ?? '',
    counterparty: props.transaction?.counterparty ?? '',
    category_id: props.transaction?.category_id ?? null,
    notes: props.transaction?.notes ?? '',
    account_id: props.transaction?.account_id ?? null,
});

const selectedCategory = computed({
    get: () => form.category_id ? { [form.category_id]: true } : null,
    set: (val) => {
        form.category_id = val ? Number(Object.keys(val)[0]) : null;
    },
});

function submit() {
    const data = {
        ...form.data(),
        date: formatDateForSubmit(form.date),
    };
    if (isEditing.value) {
        form.transform(() => data).put(`/transactions/${props.transaction.id}`);
    } else {
        form.transform(() => data).post('/transactions');
    }
}

</script>

<template>
    <AppLayout>
        <PageHeader :title="isEditing ? 'Buchung bearbeiten' : 'Neue Buchung'" backHref="/transactions" backLabel="Alle Buchungen" />

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-6 max-w-2xl">
            <div v-if="isTransferLeg" class="mb-5 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/30 p-3 text-sm text-blue-700 dark:text-blue-300">
                <i class="pi pi-arrow-right-arrow-left mr-1"></i>
                Teil einer Umbuchung {{ transaction.amount < 0 ? 'an' : 'von' }} {{ transaction.transfer_counterpart.account?.name }}.
                Betrag, Konto und Kategorie sind gesperrt, solange die Verknüpfung besteht.
            </div>
            <small v-if="form.errors.transfer" class="block mb-4 text-red-500">{{ form.errors.transfer }}</small>
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Datum</label>
                    <DatePicker v-model="form.date" dateFormat="dd.mm.yy" showIcon class="w-full" />
                    <small v-if="form.errors.date" class="text-red-500">{{ form.errors.date }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Konto</label>
                    <Select
                        v-model="form.account_id"
                        :options="accounts"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Konto wählen"
                        class="w-full"
                        showClear
                        :disabled="isTransferLeg"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Betrag</label>
                    <InputNumber v-model="form.amount" mode="currency" currency="EUR" locale="de-DE" class="w-full" placeholder="0,00" :disabled="isTransferLeg" />
                    <small class="text-gray-400 dark:text-gray-500">Positiv = Einnahme, Negativ = Ausgabe</small>
                    <br v-if="form.errors.amount" />
                    <small v-if="form.errors.amount" class="text-red-500">{{ form.errors.amount }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Beschreibung</label>
                    <InputText v-model="form.description" class="w-full" placeholder="z.B. Einkauf REWE" />
                    <small v-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Empfänger / Auftraggeber</label>
                    <InputText v-model="form.counterparty" class="w-full" placeholder="z.B. REWE Markt GmbH" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Kategorie</label>
                    <CategoryTreeSelect v-model="selectedCategory" :options="categories" placeholder="Kategorie wählen" class="w-full" :disabled="isTransferLeg" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Notizen</label>
                    <Textarea v-model="form.notes" rows="3" class="w-full" placeholder="Optionale Notizen..." />
                </div>

                <div class="flex gap-3 pt-2">
                    <Button type="submit" label="Speichern" icon="pi pi-check" :loading="form.processing" />
                    <Button label="Abbrechen" severity="secondary" @click="$inertia.visit('/transactions')" />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
