import { createI18n } from 'vue-i18n';
import fr from './locales/fr';
import en from './locales/en';

const STORAGE_KEY = 'locale';

export function resolveInitialLocale(serverLocale) {
    if (serverLocale === 'fr' || serverLocale === 'en') {
        return serverLocale;
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (stored === 'fr' || stored === 'en') {
            return stored;
        }
    } catch {
    }

    return navigator.language?.toLowerCase().startsWith('fr') ? 'fr' : 'en';
}

export function createI18nInstance(serverLocale) {
    return createI18n({
        legacy: false,
        locale: resolveInitialLocale(serverLocale),
        fallbackLocale: 'fr',
        messages: { fr, en },
    });
}

export function persistLocale(locale) {
    try {
        window.localStorage.setItem(STORAGE_KEY, locale);
    } catch {
    }
}
