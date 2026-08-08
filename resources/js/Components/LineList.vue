<script setup>
import LineCard from './LineCard.vue';

defineProps({
    lines: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <LineCard v-for="line in lines" :key="line.id" :line="line" />
    </div>
</template>
