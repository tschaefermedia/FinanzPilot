export function useFormatters() {
    const currencyFormatter = new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: 'EUR',
    });

    const dateFormatter = new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });

    const percentFormatter = new Intl.NumberFormat('de-DE', {
        style: 'percent',
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });

    function formatCurrency(amount) {
        return currencyFormatter.format(amount ?? 0);
    }

    function formatDate(date) {
        if (!date) return '';
        return dateFormatter.format(new Date(date));
    }

    function formatNumber(num, decimals = 2) {
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(num ?? 0);
    }

    function formatPercent(num) {
        return percentFormatter.format((num ?? 0) / 100);
    }

    return { formatCurrency, formatDate, formatNumber, formatPercent };
}
