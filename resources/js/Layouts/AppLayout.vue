<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Icon from '@/Components/Icon.vue';
import MicrosoftLogo from '@/Components/MicrosoftLogo.vue';
import { persistLocale } from '@/i18n';

defineProps({
    wide: { type: Boolean, default: false },
});

const { t, locale } = useI18n();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const microsoftConnected = computed(() => page.props.auth?.microsoftConnected ?? true);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const appVersion = computed(() => page.props.appVersion);
const githubUrl = computed(() => page.props.githubUrl);

const lastSyncCompletedAt = computed(() => page.props.auth?.lastSyncCompletedAt);

const lastSyncMinutesAgo = computed(() => {
    if (!lastSyncCompletedAt.value) return null;
    return Math.floor((Date.now() - new Date(lastSyncCompletedAt.value).getTime()) / 60000);
});

const lastSyncLabel = computed(() => {
    const minutes = lastSyncMinutesAgo.value;
    if (minutes === null) return t('layout.lastSyncNever');
    if (minutes < 1) return t('layout.lastSyncJustNow');
    if (minutes < 60) return t('layout.lastSyncMinutes', { n: minutes });
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return t('layout.lastSyncHours', { n: hours });
    return t('layout.lastSyncDays', { n: Math.floor(hours / 24) });
});

const lastSyncStale = computed(() => lastSyncMinutesAgo.value !== null && lastSyncMinutesAgo.value > 45);

const nav = computed(() => [
    { name: 'directory.index', label: t('layout.navDirectory'), icon: 'users' },
    { name: 'rules.index', label: t('layout.navRules'), icon: 'sliders' },
    { name: 'logs.index', label: t('layout.navLogs'), icon: 'clock' },
]);

const loggingOut = ref(false);

function logout() {
    loggingOut.value = true;
    router.post(route('logout'));
}

function setLocale(value) {
    locale.value = value;
    persistLocale(value);

    if (user.value) {
        router.patch(route('locale.update'), { locale: value }, { preserveScroll: true, preserveState: true });
    }
}

function initials(name) {
    if (!name) return '?';
    return name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 text-slate-900">
        <aside class="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-6 py-5">
                <div class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white">
                    <MicrosoftLogo class="h-4.5 w-4.5" />
                </div>
                <div class="leading-tight">
                    <div class="text-sm font-semibold text-slate-900">{{ t('layout.appName') }}</div>
                    <div class="text-xs text-slate-500">{{ t('layout.appSuite') }}</div>
                </div>
                <div class="ml-auto flex gap-0.5 rounded-md border border-slate-200 bg-slate-50 p-0.5 text-[0.65rem] font-medium">
                    <button
                        @click="setLocale('fr')"
                        :title="t('layout.language')"
                        class="rounded px-1.5 py-0.5 transition"
                        :class="locale === 'fr' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    >
                        FR
                    </button>
                    <button
                        @click="setLocale('en')"
                        :title="t('layout.language')"
                        class="rounded px-1.5 py-0.5 transition"
                        :class="locale === 'en' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    >
                        EN
                    </button>
                </div>
            </div>

            <div
                class="flex items-center gap-1.5 border-b border-slate-100 px-6 py-2.5 text-xs"
                :class="lastSyncStale ? 'text-amber-600' : 'text-slate-500'"
                :title="lastSyncStale ? t('layout.lastSyncStaleTitle') : null"
            >
                <Icon name="clock" class="h-3 w-3 shrink-0" />
                <span class="truncate">{{ lastSyncLabel }}</span>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
                <Link
                    v-for="item in nav"
                    :key="item.name"
                    :href="route(item.name)"
                    class="group flex items-center gap-3 rounded-md border-l-2 px-3 py-2.5 text-sm font-medium transition"
                    :class="route().current(item.name)
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                        : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                >
                    <Icon
                        :name="item.icon"
                        class="h-5 w-5 shrink-0"
                        :class="route().current(item.name) ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-500'"
                    />
                    {{ item.label }}
                </Link>
            </nav>

            <div class="shrink-0 border-t border-slate-100 p-3">
                <div class="flex items-center gap-3 rounded-md px-3 py-2.5">
                    <img v-if="user?.avatar" :src="user.avatar" class="h-9 w-9 rounded-full object-cover" alt="" />
                    <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                        {{ initials(user?.name) }}
                    </div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="truncate text-sm font-medium text-slate-900">{{ user?.name }}</div>
                    </div>
                    <button
                        @click="logout"
                        :disabled="loggingOut"
                        :title="t('layout.logout')"
                        class="shrink-0 rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
                    >
                        <Icon name="logout" class="h-4.5 w-4.5" />
                    </button>
                </div>
                <div class="flex items-center justify-between px-3 pt-1 text-[0.65rem] text-slate-400">
                    <span v-if="appVersion">v{{ appVersion }}</span>
                    <a
                        v-if="githubUrl"
                        :href="githubUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="transition hover:text-slate-600"
                    >
                        GitHub
                    </a>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white">
                        <MicrosoftLogo class="h-4.5 w-4.5" />
                    </div>
                    <span class="text-sm font-semibold text-slate-900">{{ t('layout.appName') }}</span>
                </div>
                <nav class="flex items-center gap-1">
                    <button
                        @click="setLocale(locale === 'fr' ? 'en' : 'fr')"
                        class="rounded-md px-2 py-2 text-xs font-medium text-slate-500"
                    >
                        {{ locale === 'fr' ? 'EN' : 'FR' }}
                    </button>
                    <Link
                        v-for="item in nav"
                        :key="item.name"
                        :href="route(item.name)"
                        class="rounded-md p-2 transition"
                        :class="route().current(item.name) ? 'bg-indigo-50 text-indigo-600' : 'text-slate-500'"
                    >
                        <Icon :name="item.icon" class="h-5 w-5" />
                    </Link>
                    <button @click="logout" class="rounded-md p-2 text-slate-500">
                        <Icon name="logout" class="h-5 w-5" />
                    </button>
                </nav>
            </header>

            <div v-if="!microsoftConnected" class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 sm:mx-8">
                <div class="flex items-center gap-2">
                    <Icon name="warning" class="h-5 w-5 shrink-0" />
                    {{ t('layout.disconnectedBanner') }}
                </div>
                <a :href="route('auth.redirect')" class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-amber-500">
                    {{ t('layout.reconnect') }}
                </a>
            </div>

            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
            >
                <div v-if="flashSuccess" class="mx-4 mt-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-700 sm:mx-8">
                    <Icon name="check" class="h-4 w-4 shrink-0" />
                    {{ flashSuccess }}
                </div>
            </transition>
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
            >
                <div v-if="flashError" class="mx-4 mt-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700 sm:mx-8">
                    <Icon name="warning" class="h-4 w-4 shrink-0" />
                    {{ flashError }}
                </div>
            </transition>

            <main class="mx-auto w-full flex-1 px-4 py-8 sm:px-8" :class="wide ? 'max-w-[90rem]' : 'max-w-6xl'">
                <slot />
            </main>
        </div>
    </div>
</template>
