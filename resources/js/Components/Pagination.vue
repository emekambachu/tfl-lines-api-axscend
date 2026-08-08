<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Laravel paginator payload: { current_page, last_page, prev_page_url, next_page_url, total }
    paginator: { type: Object, required: true },
});

const linkClass =
    'rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50';
const disabledClass =
    'rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-300 cursor-not-allowed';
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="mt-8 flex items-center justify-between gap-4"
        aria-label="Pagination"
    >
        <component
            :is="paginator.prev_page_url ? Link : 'span'"
            :href="paginator.prev_page_url || undefined"
            :class="paginator.prev_page_url ? linkClass : disabledClass"
            :aria-disabled="paginator.prev_page_url ? undefined : 'true'"
            preserve-state
            preserve-scroll
        >
            ← Previous
        </component>

        <span class="text-sm text-gray-500">
            Page {{ paginator.current_page }} of {{ paginator.last_page }}
        </span>

        <component
            :is="paginator.next_page_url ? Link : 'span'"
            :href="paginator.next_page_url || undefined"
            :class="paginator.next_page_url ? linkClass : disabledClass"
            :aria-disabled="paginator.next_page_url ? undefined : 'true'"
            preserve-state
            preserve-scroll
        >
            Next →
        </component>
    </nav>
</template>
