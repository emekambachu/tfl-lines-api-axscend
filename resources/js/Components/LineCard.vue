<script setup>
import { computed } from 'vue';

const props = defineProps({
    line: { type: Object, required: true },
});

const MAX_SECTIONS = 6;

const visibleSections = computed(() => props.line.routeSections.slice(0, MAX_SECTIONS));
const hiddenSectionCount = computed(() => Math.max(0, props.line.routeSections.length - MAX_SECTIONS));
</script>

<template>
    <article class="flex h-full flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <header class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ line.name }}</h3>
                <span class="mt-1 inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium tracking-wide text-blue-700 uppercase">
                    {{ line.mode }}
                </span>
            </div>
            <span
                v-if="line.hasDisruptions"
                class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700"
                title="This line has reported disruptions"
            >
                Disruptions
            </span>
        </header>

        <ul v-if="visibleSections.length" class="space-y-1 text-sm text-gray-600">
            <li v-for="(section, index) in visibleSections" :key="index" class="flex items-center gap-1.5">
                <span>{{ section.origin }}</span>
                <span class="text-gray-400" aria-hidden="true">→</span>
                <span>{{ section.destination }}</span>
            </li>
        </ul>
        <p v-else class="text-sm text-gray-400">No route sections listed.</p>

        <p v-if="hiddenSectionCount" class="mt-1 text-xs text-gray-400">
            + {{ hiddenSectionCount }} more route section{{ hiddenSectionCount === 1 ? '' : 's' }}
        </p>

        <p v-if="line.serviceTypes.length" class="mt-auto pt-3 text-xs text-gray-500">
            {{ line.serviceTypes.join(', ') }}
        </p>
    </article>
</template>
