import { useEffect, useState } from 'react';

export type Theme = 'light' | 'dark';

const THEME_CHANGE_EVENT = 'aura-theme-change';

function getInitialTheme(): Theme {
    if (typeof window === 'undefined') return 'light';
    try {
        const saved = localStorage.getItem('theme') as Theme | null;
        if (saved === 'dark' || saved === 'light') return saved;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch {
        return 'light';
    }
}

function applyThemeClass(theme: Theme) {
    if (typeof document === 'undefined') return;
    const root = document.documentElement;
    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
}

export function useTheme() {
    const [theme, setThemeState] = useState<Theme>(getInitialTheme);

    // Apply class and synchronize across components/tabs
    useEffect(() => {
        applyThemeClass(theme);

        const handleCustomThemeChange = (e: Event) => {
            const customEvent = e as CustomEvent<Theme>;
            if (customEvent.detail && (customEvent.detail === 'dark' || customEvent.detail === 'light')) {
                setThemeState(customEvent.detail);
                applyThemeClass(customEvent.detail);
            }
        };

        const handleStorageChange = (e: StorageEvent) => {
            if (e.key === 'theme' && (e.newValue === 'dark' || e.newValue === 'light')) {
                setThemeState(e.newValue as Theme);
                applyThemeClass(e.newValue as Theme);
            }
        };

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleMediaChange = (e: MediaQueryListEvent) => {
            if (!localStorage.getItem('theme')) {
                const newTheme: Theme = e.matches ? 'dark' : 'light';
                setThemeState(newTheme);
                applyThemeClass(newTheme);
            }
        };

        window.addEventListener(THEME_CHANGE_EVENT, handleCustomThemeChange);
        window.addEventListener('storage', handleStorageChange);
        mediaQuery.addEventListener('change', handleMediaChange);

        return () => {
            window.removeEventListener(THEME_CHANGE_EVENT, handleCustomThemeChange);
            window.removeEventListener('storage', handleStorageChange);
            mediaQuery.removeEventListener('change', handleMediaChange);
        };
    }, [theme]);

    const setTheme = (newTheme: Theme) => {
        setThemeState(newTheme);
        applyThemeClass(newTheme);
        try {
            localStorage.setItem('theme', newTheme);
        } catch {
            // Ignore quota issues
        }
        window.dispatchEvent(new CustomEvent(THEME_CHANGE_EVENT, { detail: newTheme }));
    };

    const toggleTheme = () => {
        setTheme(theme === 'dark' ? 'light' : 'dark');
    };

    return { theme, toggleTheme, setTheme, isDark: theme === 'dark' };
}
