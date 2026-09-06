<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    directoryUsers: Object,
    filters: Object,
    departments: Array,
    jobTitles: Array,
    syncAllEnabled: Boolean,
});

const search = ref(props.filters.search ?? '');
const department = ref(props.filters.department ?? '');
const jobTitle = ref(props.filters.job_title ?? '');
const syncStatus = ref(props.filters.sync_status ?? '');

const hasActiveFilters = computed(() => !!(department.value || jobTitle.value || syncStatus.value));

function applyFilters() {
    router.get(route('directory.index'), {
        search: search.value || undefined,
        department: department.value || undefined,
        job_title: jobTitle.value || undefined,
        sync_status: syncStatus.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    department.value = '';
    jobTitle.value = '';
    syncStatus.value = '';
    applyFilters();
}

function isAutoManaged(directoryUser) {
    return directoryUser.sync_source === 'rule' || directoryUser.sync_source === 'all';
}

function toggle(directoryUser) {
    if (isAutoManaged(directoryUser)) {
        return;
    }

    router.patch(route('directory.toggle', directoryUser.id), {
        enabled: !directoryUser.sync_enabled,
    }, { preserveScroll: true });
}

const syncAllLoading = ref(false);

function toggleSyncAll() {
    syncAllLoading.value = true;
    router.patch(route('directory.sync-all'), {
        enabled: !props.syncAllEnabled,
    }, {
        preserveScroll: true,
        onFinish: () => (syncAllLoading.value = false),
    });
}

function initials(name) {
    if (!name) return '?';
    return name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();
}

const avatarPalette = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700', 'bg-sky-100 text-sky-700', 'bg-violet-100 text-violet-700'];

function avatarColor(id) {
    let hash = 0;
    for (let i = 0; i < id.length; i++) hash = id.charCodeAt(i) + ((hash << 5) - hash);
    return avatarPalette[Math.abs(hash) % avatarPalette.length];
}
</script>

<template>
    <Head :title="t('directory.title')" />

    <AppLayout wide>
        <div class="mb-7 flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500 text-white shadow-sm shadow-indigo-200">
                <Icon name="users" class="h-5.5 w-5.5" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ t('directory.title') }}</h1>
                <p class="mt-0.5 text-sm text-slate-500">{{ t('directory.subtitle', { n: directoryUsers.total }, directoryUsers.total) }}</p>
            </div>
        </div>

        <div
            class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4"
            :class="syncAllEnabled ? 'border-indigo-200 bg-indigo-50/60' : 'border-slate-200 bg-white'"
        >
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="syncAllEnabled ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400'">
                    <Icon name="users" class="h-5 w-5" />
                </div>
                <div>
                    <div class="text-sm font-medium text-slate-900">{{ t('directory.syncAllTitle') }}</div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ t('directory.syncAllDescription') }}
                    </p>
                    <p v-if="!syncAllEnabled" class="mt-1 flex items-center gap-1 text-xs text-amber-600">
                        <Icon name="warning" class="h-3.5 w-3.5 shrink-0" /> {{ t('directory.syncAllHint') }}
                    </p>
                </div>
            </div>
            <button
                @click="toggleSyncAll"
                :disabled="syncAllLoading"
                role="switch"
                :aria-checked="syncAllEnabled"
                class="relative inline-flex h-5.5 w-10 shrink-0 items-center rounded-full transition disabled:opacity-50"
                :class="syncAllEnabled ? 'bg-indigo-600' : 'bg-slate-200'"
            >
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition" :class="syncAllEnabled ? 'translate-x-5' : 'translate-x-1'" />
            </button>
        </div>

        <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[220px]">
                    <Icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        :placeholder="t('directory.searchPlaceholder')"
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    />
                </div>
                <select v-model="department" @change="applyFilters" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                    <option value="">{{ t('directory.allDepartments') }}</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                </select>
                <select v-model="jobTitle" @change="applyFilters" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                    <option value="">{{ t('directory.allJobTitles') }}</option>
                    <option v-for="t2 in jobTitles" :key="t2" :value="t2">{{ t2 }}</option>
                </select>
                <select v-model="syncStatus" @change="applyFilters" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-indigo-300 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-100">
                    <option value="">{{ t('directory.allStatuses') }}</option>
                    <option value="synced">{{ t('directory.syncedOnly') }}</option>
                    <option value="not_synced">{{ t('directory.notSyncedOnly') }}</option>
                </select>
                <button @click="applyFilters" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700">
                    {{ t('directory.filter') }}
                </button>
                <button v-if="hasActiveFilters" @click="resetFilters" class="flex items-center gap-1 rounded-lg px-2 py-2 text-sm text-slate-400 transition hover:text-slate-600">
                    <Icon name="x" class="h-4 w-4" /> {{ t('directory.reset') }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-100 text-left text-xs font-medium uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">{{ t('directory.columnName') }}</th>
                            <th class="px-5 py-3">{{ t('directory.columnJobTitle') }}</th>
                            <th class="px-5 py-3">{{ t('directory.columnDepartment') }}</th>
                            <th class="px-5 py-3">{{ t('directory.columnMobile') }}</th>
                            <th class="px-5 py-3">{{ t('directory.columnBusinessPhone') }}</th>
                            <th class="px-5 py-3 text-right">{{ t('directory.columnSync') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="du in directoryUsers.data" :key="du.id" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="du.avatar_url"
                                        :src="du.avatar_url"
                                        :alt="du.display_name"
                                        class="h-9 w-9 shrink-0 rounded-full object-cover"
                                    />
                                    <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold" :class="avatarColor(du.id)">
                                        {{ initials(du.display_name) }}
                                    </div>
                                    <div class="min-w-0 max-w-[200px]">
                                        <div class="truncate font-medium text-slate-900" :title="du.display_name">{{ du.display_name }}</div>
                                        <div class="truncate text-xs text-slate-400" :title="du.mail">{{ du.mail }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="max-w-[180px] truncate px-5 py-3.5 text-slate-600" :title="du.job_title">{{ du.job_title || '-' }}</td>
                            <td class="max-w-[180px] truncate px-5 py-3.5 text-slate-600" :title="du.department">{{ du.department || '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-600">{{ du.mobile_phone || '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-600">{{ du.business_phone || '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        @click="toggle(du)"
                                        :disabled="isAutoManaged(du)"
                                        :title="isAutoManaged(du) ? (du.sync_source === 'all' ? t('directory.managedBySyncAll') : t('directory.managedByRule')) : null"
                                        role="switch"
                                        :aria-checked="du.sync_enabled"
                                        class="relative inline-flex h-5.5 w-10 items-center rounded-full transition"
                                        :class="[du.sync_enabled ? 'bg-indigo-600' : 'bg-slate-200', isAutoManaged(du) ? 'cursor-not-allowed opacity-50' : '']"
                                    >
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition" :class="du.sync_enabled ? 'translate-x-5' : 'translate-x-1'" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="directoryUsers.data.length === 0">
                            <td colspan="6" class="px-5 py-16 text-center">
                                <Icon name="inbox" class="mx-auto h-9 w-9 text-slate-300" />
                                <p class="mt-3 text-sm text-slate-400">{{ t('directory.noResults') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="directoryUsers.links.length > 3" class="mt-5 flex justify-center gap-1 text-sm">
            <button
                v-for="link in directoryUsers.links"
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
