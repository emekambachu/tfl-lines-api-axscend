<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import LineSearch from '../../Components/LineSearch.vue';
import LineList from '../../Components/LineList.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({
    mode: { type: String, default: 'browse' },
    search: { type: String, default: null },
    // Laravel paginator payload: { data, current_page, last_page, total, prev_page_url, next_page_url }
    lines: { type: Object, required: true },
    error: { type: String, default: null },
});

const query = ref(props.search ?? '');
const loading = ref(false);
let debounce = null;

watch(query, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        const trimmed = value.trim();
        router.get(
            trimmed ? '/search' : '/',
            trimmed ? { search: trimmed } : {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (loading.value = true),
                onFinish: () => (loading.value = false),
            },
        );
    }, 350);
});

const items = computed(() => props.lines.data ?? []);
const hasResults = computed(() => items.value.length > 0);
const isSearch = computed(() => props.mode === 'search');
</script>

<template>
    <Head title="Search" />

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <header class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">TfL Lines</h1>
            <p class="mt-1 text-gray-600">
                Browse London transport lines, or search by name, mode, or stop.
            </p>
        </header>

        <div class="mb-8">
            <LineSearch v-model="query" :loading="loading" />
        </div>

        <!-- Error -->
        <div
            v-if="error"
            role="alert"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-6 text-center text-red-700"
        >
            {{ error }}
        </div>

        <!-- Results (browse or search) -->
        <template v-else-if="hasResults">
            <p v-if="isSearch" class="mb-4 text-sm text-gray-500">
                {{ lines.total }} line{{ lines.total === 1 ? '' : 's' }} matching “{{ props.search }}”
            </p>
            <LineList :lines="items" />
            <Pagination :paginator="lines" />
        </template>

        <!-- Search with no matches -->
        <div
            v-else-if="isSearch"
            class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-12 text-center text-gray-500"
        >
            <p class="font-medium text-gray-700">No lines match “{{ props.search }}”.</p>
            <p class="mt-1 text-sm">Try a different name, mode, or stop.</p>
        </div>

        <!-- Browse with no data (dataset empty / unavailable) -->
        <div
            v-else
            class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-12 text-center text-gray-500"
        >
            <p class="font-medium text-gray-700">No lines to show right now.</p>
        </div>
    </main>
</template>
