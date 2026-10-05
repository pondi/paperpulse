export const documentTypes = [
    { value: 'all', label: 'All files' },
    { value: 'receipt', label: 'Receipts' },
    { value: 'document', label: 'Documents' },
    { value: 'invoice', label: 'Invoices' },
    { value: 'contract', label: 'Contracts' },
    { value: 'bank_statement', label: 'Bank statements' },
    { value: 'voucher', label: 'Vouchers' },
    { value: 'warranty', label: 'Warranties' },
    { value: 'return_policy', label: 'Return policies' },
];

export const smartViews = [
    { value: 'recent', label: 'Recent uploads', description: 'Uploaded in the last 30 days.' },
    { value: 'needs-review', label: 'Needs attention', description: 'Documents requiring review or a retry.' },
    { value: 'processing', label: 'In progress', description: 'Documents being processed or waiting to start.' },
    { value: 'unpaid', label: 'Unpaid invoices', description: 'Invoices with an outstanding balance.' },
    { value: 'expiring', label: 'Expiring soon', description: 'Contracts, warranties, vouchers and returns within 90 days.' },
    { value: 'shared', label: 'Shared with me', description: 'Documents others have shared with you.' },
];

export function librarySection(name = '') {
    return /^(library\.|saved-searches\.|search$|receipts\.|documents\.(index|show|shared)|invoices\.|contracts\.|bank-statements\.|vouchers\.|files\.show|files\.extraction-report)/.test(name);
}

export function documentTypeForRoute(name = '') {
    const prefixes = { receipts: 'receipt', documents: 'document', invoices: 'invoice', contracts: 'contract', 'bank-statements': 'bank_statement', vouchers: 'voucher' };
    return prefixes[name.split('.')[0]] || 'all';
}

export function cleanViewFilters(filters) {
    return Object.fromEntries(Object.entries(filters).filter(([key, value]) =>
        !['page', 'saved_search', 'limit'].includes(key) && value !== '' && value !== null && value !== undefined
    ));
}
