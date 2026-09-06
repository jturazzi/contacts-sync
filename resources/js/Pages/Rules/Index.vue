<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    rules: Array,
    departments: Array,
    jobTitles: Array,
    syncAllEnabled: Boolean,
});

const form = useForm({
    name: '',
    department: '',
    job_title: '',
    match_type: 'contains',
    enabled: true,
});

const previewCount = ref(null);
const previewNames = ref([]);
let previewTimer = null;

watch(() => [form.department, form.job_title, form.match_type], () => {
    clearTimeout(previewTimer);

    if (!form.department && !form.job_title) {
        previewCount.value = null;
        previewNames.value = [];
        return;
    }

    previewTimer = setTimeout(async () => {
        const { data } = await axios.post(route('rules.preview'), {
            department: form.department || null,
            job_title: form.job_title || null,
            match_type: form.match_type,
        });
        previewCount.value = data.count;
        previewNames.value = data.names;
    }, 300);
});

const expandedRuleId = ref(null);

function toggleExpand(rule) {
    expandedRuleId.value = expandedRuleId.value === rule.id ? null : rule.id;
}

function submit() {
    form.post(route('rules.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            previewCount.value = null;
            previewNames.value = [];
        },
    });
}

function toggleEnabled(rule) {
    router.patch(route('rules.update', rule.id), {
        name: rule.name,
        department: rule.department,
        job_title: rule.job_title,
        match_type: rule.match_type,
        enabled: !rule.enabled,
    }, { preserveScroll: true });
}

function destroy(rule) {
    if (confirm(t('rules.confirmDelete', { name: rule.name }))) {
        router.delete(route('rules.destroy', rule.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t('rules.title')" />

    <AppLayout>
        <div class="mb-7 flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500 text-white shadow-sm shadow-indigo-200">
                <Icon name="sliders" class="h-5.5 w-5.5" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('rules.title') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500">{{ t('rules.subtitle') }}</p>
            </div>
        </div>

        <div v-if="syncAllEnabled" class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <Icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" />
            <span>
                {{ t('rules.syncAllActiveBefore') }}
                <Link :href="route('directory.index')" class="font-medium underline underline-offset-2">{{ t('rules.syncAllActiveLink') }}</Link>
                {{ t('rules.syncAllActiveAfter') }}
            </span>
        </div>

        <form @submit.prevent="submit" class="mb-8 rounded-xl border border-slate-200 bg-white p-5">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-1">
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">{{ t('rules.formName') }}</label>
                    <input v-model="form.name" required type="text" :placeholder="t('rules.formNamePlaceholder')" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100" />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">{{ t('rules.formDepartment') }}</label>
                    <select v-model="form.department" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        <option value="">{{ t('rules.none') }}</option>
                        <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">{{ t('rules.formJobTitle') }}</label>
                    <select v-model="form.job_title" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        <option value="">{{ t('rules.none') }}</option>
                        <option v-for="jt in jobTitles" :key="jt" :value="jt">{{ jt }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-slate-500">{{ t('rules.formMatchType') }}</label>
                    <select v-model="form.match_type" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                        <option value="contains">{{ t('rules.matchContains') }}</option>
                        <option value="exact">{{ t('rules.matchExact') }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm shadow-indigo-200 transition hover:bg-indigo-500 disabled:opacity-50">
                        <Icon name="plus" class="h-4 w-4" /> {{ t('rules.create') }}
                    </button>
                </div>
            </div>
            <div v-if="previewCount !== null" class="mt-3">
                <div class="flex items-center gap-1.5 text-xs font-medium text-indigo-600">
                    <Icon name="users" class="h-3.5 w-3.5" /> {{ t('rules.matchedNow', { n: previewCount }, previewCount) }}
                </div>
                <div v-if="previewNames.length" class="mt-2 flex flex-wrap gap-1.5">
                    <span v-for="name in previewNames" :key="name" class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                        {{ name }}
                    </span>
                </div>
            </div>
        </form>

        <div class="space-y-3">
            <div v-for="rule in rules" :key="rule.id" class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-slate-300">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="rule.enabled ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-400'">
                        <Icon name="sliders" class="h-5 w-5" />
                    </div>

                    <div class="min-w-[10rem] flex-1">
                        <div class="font-medium text-slate-900">{{ rule.name }}</div>
                        <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-500">
                            <span v-if="rule.department" class="flex items-center gap-1"><Icon name="building" class="h-3 w-3" /> {{ rule.department }}</span>
                            <span v-if="rule.job_title" class="flex items-center gap-1"><Icon name="users" class="h-3 w-3" /> {{ rule.job_title }}</span>
                            <span class="text-slate-300">·</span>
                            <span>{{ rule.match_type === 'exact' ? t('rules.matchExact') : t('rules.matchContains') }}</span>
                        </div>
                    </div>

                    <button
                        @click="toggleExpand(rule)"
                        :disabled="rule.matches_count === 0"
                        class="flex items-center gap-1.5 rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100 disabled:cursor-default disabled:hover:bg-slate-50"
                    >
                        <Icon name="users" class="h-3.5 w-3.5 text-slate-400" /> {{ t('rules.matchesCount', { n: rule.matches_count }, rule.matches_count) }}
                        <Icon v-if="rule.matches_count > 0" name="chevronDown" class="h-3 w-3 text-slate-400 transition" :class="{ 'rotate-180': expandedRuleId === rule.id }" />
                    </button>

                    <button
                        @click="toggleEnabled(rule)"
                        role="switch"
                        :aria-checked="rule.enabled"
                        class="relative inline-flex h-5.5 w-10 items-center rounded-full transition"
                        :class="rule.enabled ? 'bg-indigo-600' : 'bg-slate-200'"
                    >
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition" :class="rule.enabled ? 'translate-x-5' : 'translate-x-1'" />
                    </button>

                    <button @click="destroy(rule)" class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                        <Icon name="trash" class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="expandedRuleId === rule.id" class="mt-3 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3">
                    <span v-for="name in rule.matched_names" :key="name" class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                        {{ name }}
                    </span>
                </div>
            </div>

            <div v-if="rules.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-white px-5 py-16 text-center">
                <Icon name="sliders" class="mx-auto h-9 w-9 text-slate-300" />
                <p class="mt-3 text-sm text-slate-400">{{ t('rules.emptyState') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
