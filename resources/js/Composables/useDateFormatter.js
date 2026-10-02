import { formatPreferredDate, formatCurrency, daysUntilCalendarDate, formattingPreferences } from '@/utils/datetime';

export function useDateFormatter() {
    return {
        formatDate: formatPreferredDate,
        formatDateTime: date => formatPreferredDate(date, true),
        formatCurrency,
        daysUntilDate: date => daysUntilCalendarDate(date, formattingPreferences().timezone),
    };
}
