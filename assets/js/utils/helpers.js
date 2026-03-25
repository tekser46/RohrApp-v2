/**
 * RohrApp+ — Helper utilities
 */

/** Escape HTML to prevent XSS */
function esc(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

/** Format date to German locale */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric'
    });
}

/** Format datetime */
function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('de-DE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

/** Format money (Euro) */
function formatMoney(amount) {
    return parseFloat(amount || 0).toLocaleString('de-DE', {
        style: 'currency', currency: 'EUR'
    });
}

/** Debounce function */
function debounce(fn, ms = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}
