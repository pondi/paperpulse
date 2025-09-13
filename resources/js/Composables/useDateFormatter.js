import { usePage } from '@inertiajs/vue3';

export function useDateFormatter() {
    const page = usePage();
    
    const formatDate = (date, includeTime = false) => {
        if (!date) return '';
        
        const user = page.props.auth?.user;
        const timezone = user?.preferences?.timezone || user?.timezone || 'UTC';
        const dateFormat = user?.preferences?.date_format || 'Y-m-d';
        
        // Convert date string to Date object
        const dateObj = new Date(date);
        
        // Create Intl.DateTimeFormat options based on user's date format preference
        let options = {
            timeZone: timezone,
        };
        
        // Map PHP date formats to Intl.DateTimeFormat options
        switch (dateFormat) {
            case 'Y-m-d':
                options.year = 'numeric';
                options.month = '2-digit';
                options.day = '2-digit';
                break;
            case 'd/m/Y':
            case 'd.m.Y':
                options.day = '2-digit';
                options.month = '2-digit';
                options.year = 'numeric';
                break;
            case 'm/d/Y':
                options.month = '2-digit';
                options.day = '2-digit';
                options.year = 'numeric';
                break;
            case 'F j, Y':
                options.month = 'long';
                options.day = 'numeric';
                options.year = 'numeric';
                break;
            case 'j F Y':
                options.day = 'numeric';
                options.month = 'long';
                options.year = 'numeric';
                break;
            default:
                // Default to ISO format
                options.year = 'numeric';
                options.month = '2-digit';
                options.day = '2-digit';
        }
        
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        
        // Get user's locale from language preference
        const locale = user?.preferences?.language === 'nb' ? 'nb-NO' : 'en-US';
        
        try {
            const formatter = new Intl.DateTimeFormat(locale, options);
            let formatted = formatter.format(dateObj);
            
            // Handle specific format adjustments
            if (dateFormat === 'Y-m-d') {
                // Ensure YYYY-MM-DD format
                const parts = formatter.formatToParts(dateObj);
                const year = parts.find(p => p.type === 'year')?.value;
                const month = parts.find(p => p.type === 'month')?.value.padStart(2, '0');
                const day = parts.find(p => p.type === 'day')?.value.padStart(2, '0');
                formatted = `${year}-${month}-${day}`;
            } else if (dateFormat === 'd.m.Y') {
                // Replace slashes with dots
                formatted = formatted.replace(/\//g, '.');
            }
            
            return formatted;
        } catch (error) {
            console.error('Date formatting error:', error);
            return dateObj.toLocaleDateString();
        }
    };
    
    const formatDateTime = (date) => {
        return formatDate(date, true);
    };
    
    const formatCurrency = (amount, currency = null) => {
        const user = page.props.auth?.user;
        let userCurrency = currency || user?.preferences?.currency || 'NOK';
        const locale = user?.preferences?.language === 'nb' ? 'nb-NO' : 'en-US';
        
        // Handle invalid currency codes (e.g., "Kr" instead of "NOK")
        const currencyMap = {
            'Kr': 'NOK',
            'kr': 'NOK',
            '€': 'EUR',
            '$': 'USD',
            '£': 'GBP'
        };
        
        // If currency is in the map, replace it with the proper ISO code
        if (currencyMap[userCurrency]) {
            userCurrency = currencyMap[userCurrency];
        }
        
        // Validate currency code format (3 uppercase letters)
        if (!/^[A-Z]{3}$/.test(userCurrency)) {
            console.warn(`Invalid currency code: ${userCurrency}, defaulting to NOK`);
            userCurrency = 'NOK';
        }
        
        // Amounts are already in main currency unit (NOK, EUR, etc.)
        const actualAmount = amount || 0;
        
        try {
            return new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: userCurrency
            }).format(actualAmount);
        } catch (error) {
            console.error(`Currency formatting error: ${error.message}`);
            // Fallback to simple number formatting with currency suffix
            return `${actualAmount.toFixed(2)} ${userCurrency}`;
        }
    };
    
    return {
        formatDate,
        formatDateTime,
        formatCurrency
    };
}