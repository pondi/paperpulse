/**
 * Date and time formatting utilities for the application.
 * Handles timezone conversion and user-friendly date display.
 */

/**
 * Get user's configured timezone or browser default
 * Note: User timezone configuration is planned for future implementation
 */
function getUserConfiguredTimezone(): string {
    // For now, use browser's timezone which automatically matches user's system
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
}

/**
 * Format an ISO 8601 datetime string to user's local time
 * @param datetime - ISO 8601 datetime string (e.g., "2024-01-15T10:30:00Z")
 * @param format - Display format: 'full', 'date', 'time', 'relative'
 * @returns Formatted datetime string in user's local timezone
 */
export function formatDateTime(datetime: string | null | undefined, format: 'full' | 'date' | 'time' | 'relative' = 'full'): string {
    if (!datetime) return '-';
    
    const date = new Date(datetime);
    
    // Check if date is valid
    if (isNaN(date.getTime())) return '-';
    
    const locale = navigator.language || 'en-US';
    
    switch (format) {
        case 'date':
            return date.toLocaleDateString(locale, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
            
        case 'time':
            return date.toLocaleTimeString(locale, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
        case 'relative':
            return getRelativeTime(date);
            
        case 'full':
        default:
            return date.toLocaleString(locale, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
    }
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
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (Math.abs(diffSecs) < 60) {
        return diffSecs < 0 ? `in ${Math.abs(diffSecs)} seconds` : `${diffSecs} seconds ago`;
    } else if (Math.abs(diffMins) < 60) {
        return diffMins < 0 ? `in ${Math.abs(diffMins)} minutes` : `${diffMins} minutes ago`;
    } else if (Math.abs(diffHours) < 24) {
        return diffHours < 0 ? `in ${Math.abs(diffHours)} hours` : `${diffHours} hours ago`;
    } else if (Math.abs(diffDays) < 30) {
        return diffDays < 0 ? `in ${Math.abs(diffDays)} days` : `${diffDays} days ago`;
    } else {
        return date.toLocaleDateString();
    }
}

/**
 * Get timezone abbreviation for the user's current timezone
 * @returns Timezone abbreviation (e.g., "PST", "EST", "UTC")
 */
export function getUserTimezone(): string {
    const date = new Date();
    const timezoneName = date.toLocaleTimeString('en-US', { 
        timeZoneName: 'short' 
    }).split(' ').pop() || 'Local';
    
    return timezoneName;
}