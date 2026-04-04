<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useFormatters } from '@/Composables/useFormatters.js';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Tag from 'primevue/tag';

const { formatCurrency } = useFormatters();

const props = defineProps({
    accounts: { type: Array, default: () => [] },
});

const showDialog = ref(false);
const editingAccount = ref(null);

const form = useForm({
    name: '',
    type: 'checking',
    starting_balance: 0,
    icon: '',
    color: '#3b82f6',
    is_active: true,
});

const typeOptions = [
    { label: 'Girokonto', value: 'checking' },
    { label: 'Sparkonto', value: 'savings' },
    { label: 'Kreditkarte', value: 'credit_card' },
    { label: 'Bargeld', value: 'cash' },
    { label: 'Sonstiges', value: 'other' },
];

function typeLabel(type) {
    return { checking: 'Girokonto', savings: 'Sparkonto', credit_card: 'Kreditkarte', cash: 'Bargeld', other: 'Sonstiges' }[type] || type;
}

function typeSeverity(type) {
    return { checking: 'info', savings: 'success', credit_card: 'warn', cash: 'secondary', other: 'secondary' }[type] || 'secondary';
}

function openCreate() {
    editingAccount.value = null;
    form.reset();
    form.clearErrors();
    form.type = 'checking';
    form.starting_balance = 0;
    form.color = '#3b82f6';
    form.is_active = true;
    showDialog.value = true;
}

function openEdit(account) {
    editingAccount.value = account;
    form.name = account.name;
    form.type = account.type;
    form.starting_balance = parseFloat(account.starting_balance);
    form.icon = account.icon || '';
    form.color = account.color || '#3b82f6';
    form.is_active = account.is_active;
    showDialog.value = true;
}

function submit() {
    if (editingAccount.value) {
        form.put(`/accounts/${editingAccount.value.id}`, {
            onSuccess: () => { showDialog.value = false; form.reset(); form.clearErrors(); },
        });
    } else {
        form.post('/accounts', {
            onSuccess: () => { showDialog.value = false; form.reset(); form.clearErrors(); },
        });
    }
}

function deleteAccount(id) {
    if (confirm('Konto wirklich löschen? Buchungen werden nicht gelöscht, aber die Kontozuordnung entfernt.')) {
        router.delete(`/accounts/${id}`);
    }
}
</script>

<template>
    <AppLayout>
        <PageHeader title="Konten">
            <Button label="Neues Konto" icon="pi pi-plus" size="small" @click="openCreate" />
        </PageHeader>

        <div v-if="accounts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="account in accounts"
                :key="account.id"
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 p-5 relative"
                :class="{ 'opacity-50': !account.is_active }"
            >
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ account.name }}</h3>
                        <Tag :value="typeLabel(account.type)" :severity="typeSeverity(account.type)" class="mt-1" />
                    </div>
                    <div class="flex gap-1">
                        <Button icon="pi pi-pencil" text rounded size="small" @click="openEdit(account)" />
                        <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteAccount(account.id)" />
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Aktueller Kontostand</p>
                    <p :class="[
                        'text-2xl font-bold',
                        account.current_balance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-600'
                    ]">
                        {{ formatCurrency(account.current_balance) }}
                    </p>
                </div>

                <div class="mt-3 flex justify-between text-xs text-gray-400 dark:text-gray-500">
                    <span>Startguthaben: {{ formatCurrency(account.starting_balance) }}</span>
                    <span>{{ account.transaction_count }} Buchungen</span>
                </div>

                <div
                    v-if="account.color"
                    class="absolute top-0 left-0 w-1 h-full rounded-l-lg"
                    :style="{ backgroundColor: account.color }"
                />
            </div>
        </div>
        <EmptyState v-else message="Keine Konten vorhanden. Erstelle dein erstes Konto." icon="pi-wallet" />

        <Dialog v-model:visible="showDialog" :header="editingAccount ? 'Konto bearbeiten' : 'Neues Konto'" modal class="w-full max-w-md">
            <form @submit.prevent="submit" class="space-y-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Name</label>
                    <InputText v-model="form.name" class="w-full" placeholder="z.B. Girokonto Sparkasse" />
                    <small v-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Typ</label>
                    <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Startguthaben</label>
                    <InputNumber v-model="form.starting_balance" mode="currency" currency="EUR" locale="de-DE" class="w-full" />
                    <small class="text-gray-400 dark:text-gray-500">Kontostand zum Zeitpunkt der Einrichtung</small>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Farbe</label>
                    <input type="color" v-model="form.color" class="w-10 h-10 rounded border border-gray-200 dark:border-gray-700 cursor-pointer" />
                </div>

                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" />
                    <label class="text-sm text-gray-700 dark:text-gray-200">Aktiv</label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Abbrechen" severity="secondary" size="small" @click="showDialog = false" />
                    <Button type="submit" label="Speichern" size="small" :loading="form.processing" />
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>
