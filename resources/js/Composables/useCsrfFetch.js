export function useCsrfFetch() {
    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async function csrfFetch(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {}),
        };

        if (method !== 'GET' && method !== 'HEAD') {
            headers['X-CSRF-TOKEN'] = getCsrfToken();
        }

        return fetch(url, {
            ...options,
            method,
            headers,
            credentials: 'same-origin',
        });
    }

    return { csrfFetch, getCsrfToken };
}
