<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="block">
        <span class="mb-1 block text-sm font-medium text-gray-700">
            Search lines
        </span>
        <div class="relative">
            <input
                :value="modelValue"
                type="search"
                inputmode="search"
                autocomplete="off"
                placeholder="Try “Brixton”, “tube”, or “victoria”"
                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pr-10 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <span
                v-if="loading"
                class="absolute top-1/2 right-3 -translate-y-1/2"
                aria-hidden="true"
            >
                <svg class="h-5 w-5 animate-spin text-blue-600" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
            </span>
        </div>
    </label>
</template>
