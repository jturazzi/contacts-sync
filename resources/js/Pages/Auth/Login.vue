<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import MicrosoftLogo from '@/Components/MicrosoftLogo.vue';
import { persistLocale } from '@/i18n';

const { t, locale } = useI18n();

const page = usePage();
const appVersion = computed(() => page.props.appVersion);
const githubUrl = computed(() => page.props.githubUrl);

function setLocale(value) {
    locale.value = value;
    persistLocale(value);
}
</script>

<template>
    <Head :title="t('login.pageTitle')" />

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-1.5 bg-indigo-500"></div>
        <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-indigo-100/60 blur-3xl"></div>

        <div class="absolute top-4 right-4 flex gap-0.5 rounded-md border border-slate-200 bg-white p-0.5 text-xs font-medium shadow-sm">
            <button
                @click="setLocale('fr')"
                class="rounded px-2.5 py-1 transition"
                :class="locale === 'fr' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:text-slate-700'"
            >
                FR
            </button>
            <button
                @click="setLocale('en')"
                class="rounded px-2.5 py-1 transition"
                :class="locale === 'en' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:text-slate-700'"
            >
                EN
            </button>
        </div>

        <div class="relative w-full max-w-sm">
            <div class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                <div class="mb-6 flex justify-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-white shadow-sm">
                        <MicrosoftLogo class="h-7 w-7" />
                    </div>
                </div>

                <h1 class="text-xl font-semibold text-slate-900">{{ t('login.title') }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                    {{ t('login.subtitle') }}
                </p>

                <a
                    :href="route('auth.redirect')"
                    class="mt-7 flex w-full items-center justify-center gap-2.5 rounded-md border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    <MicrosoftLogo class="h-4 w-4" />
                    {{ t('login.connect') }}
                </a>
            </div>

            <div class="mt-4 flex items-center justify-center gap-3 text-xs text-slate-400">
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
    </div>
</template>
