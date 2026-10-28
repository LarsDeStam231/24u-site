const currencyFormatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 0,
});

const decimalCurrencyFormatter = new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
});

export const formatMoney = (cents) => (
    cents % 100 === 0 ? currencyFormatter : decimalCurrencyFormatter
).format(cents / 100);

export const formatDate = (date, options = {}) => new Intl.DateTimeFormat('nl-NL', {
    timeZone: 'Europe/Amsterdam',
    day: 'numeric',
    month: 'long',
    ...options,
}).format(new Date(date));
