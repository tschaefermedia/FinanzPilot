import { ref, watch } from 'vue';

const isDark = ref(false);

function applyTheme(dark) {
    document.documentElement.classList.toggle('dark', dark);
}

function initTheme() {
    const stored = localStorage.getItem('theme');
    if (stored) {
        isDark.value = stored === 'dark';
    } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    applyTheme(isDark.value);
}

function toggleTheme() {
    isDark.value = !isDark.value;
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
    applyTheme(isDark.value);
}

// Initialize on first import
initTheme();

export function useTheme() {
    return { isDark, toggleTheme };
}
