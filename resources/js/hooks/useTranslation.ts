import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

export function useTranslation() {
    const { translations = {}, locale = 'en' } = usePage<PageProps>().props;

    const t = (key: string, defaultVal?: string): string => {
        return translations[key] ?? defaultVal ?? key;
    };

    return { t, locale };
}

