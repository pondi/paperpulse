import { usePage } from '@inertiajs/vue3';

export function useTranslations() {
    const page = usePage();
    
    const __ = (key, replacements = {}) => {
        const messages = page.props.language?.messages || {};
        let translation = messages[key] || key;
        
        // Handle replacements like :count, :name, etc.
        Object.keys(replacements).forEach(key => {
            translation = translation.replace(`:${key}`, replacements[key]);
        });
        
        // Handle pluralization (simple implementation)
        if (replacements.count !== undefined) {
            const parts = translation.split('|');
            if (parts.length === 2) {
                translation = replacements.count === 1 ? parts[0] : parts[1];
                translation = translation.replace(':count', replacements.count);
            }
        }
        
        return translation;
    };
    
    return { __ };
}