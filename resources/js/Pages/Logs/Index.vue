<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

defineProps({
    logs: Object,
});

const actionMeta = computed(() => ({
    created: { label: t('logs.actionCreated'), class: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
    updated: { label: t('logs.actionUpdated'), class: 'bg-sky-50 text-sky-700', dot: 'bg-sky-500' },
    deleted: { label: t('logs.actionDeleted'), class: 'bg-red-50 text-red-700', dot: 'bg-red-500' },
    error: { label: t('logs.actionError'), class: 'bg-red-50 text-red-700', dot: 'bg-red-500' },
}));

function formatDate(value) {
    const localeTag = locale.value === 'fr' ? 'fr-FR' : 'en-US';
    return new Date(value).toLocaleString(localeTag, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <Head :title="t('logs.title')" />

    <AppLayout>
        <div class="mb-7 flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500 text-white shadow-sm shadow-indigo-200">
                <Icon name="clock" class="h-5.5 w-5.5" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('logs.title') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500">{{ t('logs.subtitle') }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <ul class="divide-y divide-slate-100">
                <li v-for="log in logs.data" :key="log.id" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50/70">
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="actionMeta[log.action]?.dot ?? 'bg-slate-300'"></span>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-slate-900">{{ log.directory_user?.display_name ?? t('logs.directoryFallback') }}</div>
                        <div v-if="log.action === 'error'" class="truncate text-xs text-red-500">{{ log.message }}</div>
                    </div>

                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="actionMeta[log.action]?.class ?? 'bg-slate-100 text-slate-600'">
                        {{ actionMeta[log.action]?.label ?? log.action }}
                    </span>

                    <span class="hidden shrink-0 text-xs text-slate-400 sm:block">{{ formatDate(log.created_at) }}</span>
                </li>

                <li v-if="logs.data.length === 0" class="px-5 py-16 text-center">
                    <Icon name="clock" class="mx-auto h-9 w-9 text-slate-300" />
                    <p class="mt-3 text-sm text-slate-400">{{ t('logs.emptyState') }}</p>
                </li>
            </ul>
        </div>

        <div v-if="logs.links.length > 3" class="mt-5 flex justify-center gap-1 text-sm">
            <button
                v-for="link in logs.links"
                :key="link.label"
                v-html="link.label"
                :disabled="!link.url"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                class="min-w-[2.25rem] rounded-lg px-3 py-1.5 transition"
                :class="link.active ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-100 disabled:opacity-30'"
            />
        </div>
    </AppLayout>
</template>
