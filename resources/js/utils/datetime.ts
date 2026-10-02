import { usePage } from '@inertiajs/vue3';

/**
 * Date and time formatting utilities for the application.
 * Handles timezone conversion and user-friendly date display.
 */

export function daysUntilCalendarDate(date: string | null | undefined, timezone: string, instant: Date = new Date()): number | null {
    if (!date) return null;

    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(instant);
    const year = Number(parts.find(part => part.type === 'year')?.value);
    const month = Number(parts.find(part => part.type === 'month')?.value);
    const day = Number(parts.find(part => part.type === 'day')?.value);
    const [expiryYear, expiryMonth, expiryDay] = date.slice(0, 10).split('-').map(Number);

    return (Date.UTC(expiryYear, expiryMonth - 1, expiryDay) - Date.UTC(year, month - 1, day)) / 86400000;
}

export function formattingPreferences(): { language: string; timezone: string; date_format: string; currency: string } {
    return usePage().props.auth?.user?.preferences || { language: 'en', timezone: 'UTC', date_format: 'Y-m-d', currency: 'NOK' };
}

export function formatPreferredDate(value: string | null | undefined, includeTime = false): string {
    if (!value) return '';
    const prefs = formattingPreferences();
    const calendar = !includeTime || /^\d{4}-\d{2}-\d{2}$/.test(value);
    const date = new Date(calendar ? value.slice(0, 10) + 'T00:00:00Z' : value);
    if (Number.isNaN(date.getTime())) return '';
    const locale = prefs.language === 'nb' ? 'nb-NO' : 'en-US';
    const timeZone = calendar ? 'UTC' : prefs.timezone;
    const formatter = new Intl.DateTimeFormat(locale, { timeZone, year: 'numeric', month: prefs.date_format.includes('F') ? 'long' : '2-digit', day: '2-digit' });
    const parts = formatter.formatToParts(date);
    const part = (type: string) => parts.find(p => p.type === type)?.value || '';
    const year = part('year'), month = part('month'), day = part('day');
    const formats: Record<string, string> = {
        'Y-m-d': `${year}-${month}-${day}`,
        'd/m/Y': `${day}/${month}/${year}`,
        'm/d/Y': `${month}/${day}/${year}`,
        'd.m.Y': `${day}.${month}.${year}`,
        'F j, Y': `${month} ${Number(day)}, ${year}`,
        'j F Y': `${Number(day)} ${month} ${year}`,
    };
    const formatted = formats[prefs.date_format];
    return calendar ? formatted : `${formatted} ${new Intl.DateTimeFormat(locale, { timeZone, hour: '2-digit', minute: '2-digit' }).format(date)}`;
}

export function formatCurrency(amount: number | string | null | undefined, currency: string | null = null): string {
    if (amount === null || amount === undefined) return 'Conversion unavailable';
    const prefs = formattingPreferences();
    const code = currency ?? prefs.currency;
    const locale = prefs.language === 'nb' ? 'nb-NO' : 'en-US';
    if (!/^[A-Z]{3}$/.test(code)) return `${Number(amount).toFixed(2)} ${code}`;
    return new Intl.NumberFormat(locale, { style: 'currency', currency: code }).format(Number(amount));
}

export function formatDateTime(datetime: string | null | undefined, format: 'full' | 'date' | 'time' | 'relative' = 'full'): string {
    if (!datetime) return '-';
    const date = new Date(datetime);
    if (Number.isNaN(date.getTime())) return '-';
    const prefs = formattingPreferences();
    const locale = prefs.language === 'nb' ? 'nb-NO' : 'en-US';
    if (format === 'relative') return getRelativeTime(date);
    if (format === 'time') return new Intl.DateTimeFormat(locale, { timeZone: prefs.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(date);
    return formatPreferredDate(datetime, format !== 'date');
}

/**
 * Format a duration in seconds to a human-readable string
 * @param seconds - Duration in seconds
 * @returns Formatted duration string (e.g., "1m 23s", "2h 15m 30s")
 */
export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined || seconds < 0) return '-';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    const parts: string[] = [];
    
    if (hours > 0) parts.push(`${hours}h`);
    if (minutes > 0) parts.push(`${minutes}m`);
    if (secs > 0 || parts.length === 0) parts.push(`${secs}s`);
    
    return parts.join(' ');
}

/**
 * Get relative time string (e.g., "5 minutes ago", "in 2 hours")
 * @param date - Date object to compare with current time
 * @returns Relative time string
 */
function getRelativeTime(date: Date): string {
    const seconds = (date.getTime() - Date.now()) / 1000;
    const prefs = formattingPreferences();
    const formatter = new Intl.RelativeTimeFormat(prefs.language === 'nb' ? 'nb-NO' : 'en-US');
    const [unit, divisor]: [Intl.RelativeTimeFormatUnit, number] = Math.abs(seconds) < 60 ? ['second', 1]
        : Math.abs(seconds) < 3600 ? ['minute', 60]
        : Math.abs(seconds) < 86400 ? ['hour', 3600] : ['day', 86400];
    return formatter.format(Math.round(seconds / divisor), unit);
}

/**
 * Get timezone abbreviation for the user's current timezone
 * @returns Timezone abbreviation (e.g., "PST", "EST", "UTC")
 */
export function getUserTimezone(): string {
    const prefs = formattingPreferences();
    const parts = new Intl.DateTimeFormat(prefs.language === 'nb' ? 'nb-NO' : 'en-US', { timeZone: prefs.timezone, timeZoneName: 'short' }).formatToParts(new Date());
    return parts.find(part => part.type === 'timeZoneName')?.value || prefs.timezone;
}
