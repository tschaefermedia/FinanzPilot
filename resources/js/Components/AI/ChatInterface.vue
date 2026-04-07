<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { useCsrfFetch } from '@/Composables/useCsrfFetch.js';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const { csrfFetch } = useCsrfFetch();

const messages = ref([]);
const input = ref('');
const loading = ref(false);
const error = ref(null);
const chatContainer = ref(null);
const conversationId = ref(localStorage.getItem('ai_conversation_id') || null);

const quickQuestions = [
    'Was sind meine größten Einsparpotenziale?',
    'Wie entwickeln sich meine Fixkosten?',
    'Welche Kategorien liegen über Budget?',
    'Wie kann ich schneller Schulden abbauen?',
];

onMounted(async () => {
    if (!conversationId.value) return;

    try {
        const response = await csrfFetch(`/api/ai/chat/history?conversationId=${conversationId.value}`, { method: 'GET' });
        const data = await response.json();
        if (data.messages?.length) {
            messages.value = data.messages;
            await scrollToBottom();
        }
    } catch (e) {
        // No history, that's fine
    }
});

async function sendMessage(text = null) {
    const msg = text || input.value.trim();
    if (!msg || loading.value) return;

    input.value = '';
    error.value = null;
    messages.value.push({ role: 'user', content: msg });
    await scrollToBottom();

    loading.value = true;

    try {
        const response = await csrfFetch('/api/ai/chat/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                message: msg,
                conversationId: conversationId.value,
            }),
        });

        const data = await response.json();

        if (data.success) {
            messages.value.push({ role: 'assistant', content: data.message });

            if (data.conversationId && data.conversationId !== conversationId.value) {
                conversationId.value = data.conversationId;
                localStorage.setItem('ai_conversation_id', data.conversationId);
            }
        } else {
            error.value = data.error || 'Fehler beim Senden der Nachricht.';
        }
    } catch (e) {
        error.value = 'Verbindungsfehler. Bitte erneut versuchen.';
    } finally {
        loading.value = false;
        await scrollToBottom();
    }
}

async function clearChat() {
    try {
        await csrfFetch('/api/ai/chat/clear', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversationId: conversationId.value }),
        });
        messages.value = [];
        error.value = null;
        conversationId.value = null;
        localStorage.removeItem('ai_conversation_id');
    } catch (e) {
        // ignore
    }
}

async function scrollToBottom() {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

function formatMessage(text) {
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                <i class="pi pi-comments text-purple-500 mr-1"></i>
                Finanzassistent
            </h3>
            <Button
                v-if="messages.length > 0"
                icon="pi pi-trash"
                text
                rounded
                size="small"
                severity="secondary"
                title="Chat leeren"
                @click="clearChat"
            />
        </div>

        <!-- Messages -->
        <div
            ref="chatContainer"
            class="px-6 py-4 space-y-4 overflow-y-auto"
            :class="messages.length > 0 ? 'h-80' : 'h-auto'"
        >
            <!-- Empty state -->
            <div v-if="messages.length === 0 && !loading" class="text-center py-8">
                <i class="pi pi-comments text-3xl text-gray-300 dark:text-gray-600 mb-3 block"></i>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Stelle eine Frage zu deinen Finanzen
                </p>

                <!-- Quick questions -->
                <div class="flex flex-wrap justify-center gap-2">
                    <button
                        v-for="(q, i) in quickQuestions"
                        :key="i"
                        class="text-xs px-3 py-1.5 rounded-full bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-400 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors"
                        @click="sendMessage(q)"
                    >
                        {{ q }}
                    </button>
                </div>
            </div>

            <!-- Message bubbles -->
            <div
                v-for="(msg, i) in messages"
                :key="i"
                :class="[
                    'flex',
                    msg.role === 'user' ? 'justify-end' : 'justify-start',
                ]"
            >
                <div
                    :class="[
                        'max-w-[80%] rounded-lg px-4 py-2.5 text-sm',
                        msg.role === 'user'
                            ? 'bg-purple-600 text-white'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200',
                    ]"
                >
                    <div v-if="msg.role === 'assistant'" v-html="formatMessage(msg.content)"></div>
                    <span v-else>{{ msg.content }}</span>
                </div>
            </div>

            <!-- Loading indicator -->
            <div v-if="loading" class="flex justify-start">
                <div class="bg-gray-100 dark:bg-gray-700 rounded-lg px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">
                    <i class="pi pi-spin pi-spinner mr-1"></i>
                    Denkt nach...
                </div>
            </div>

            <!-- Error -->
            <div v-if="error" class="text-center">
                <p class="text-xs text-red-500">{{ error }}</p>
            </div>
        </div>

        <!-- Quick questions (shown when there's history) -->
        <div v-if="messages.length > 0 && !loading" class="px-6 pb-2">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="(q, i) in quickQuestions"
                    :key="i"
                    class="text-xs px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    @click="sendMessage(q)"
                >
                    {{ q }}
                </button>
            </div>
        </div>

        <!-- Input -->
        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
            <form @submit.prevent="sendMessage()" class="flex gap-2">
                <InputText
                    v-model="input"
                    placeholder="Frage stellen..."
                    class="flex-1"
                    :disabled="loading"
                    maxlength="1000"
                />
                <Button
                    type="submit"
                    icon="pi pi-send"
                    :loading="loading"
                    :disabled="!input.trim() || loading"
                />
            </form>
        </div>
    </div>
</template>
