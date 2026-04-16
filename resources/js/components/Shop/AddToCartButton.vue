<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    productId: number;
    productSkuId?: number | null;
    quantity?: number;
    disabled?: boolean;
    label?: string;
    variant?: 'primary' | 'secondary';
}>();

const processing = ref(false);

function add(): void {
    if (processing.value || props.disabled) {
        return;
    }

    processing.value = true;

    router.post(
        '/cart/items',
        {
            product_id: props.productId,
            product_sku_id: props.productSkuId ?? null,
            quantity: props.quantity ?? 1,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <button
        type="button"
        :disabled="disabled || processing"
        :class="[
            'inline-flex h-12 items-center justify-center gap-2 px-5 text-sm font-extrabold tracking-wider uppercase transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40',
            (variant ?? 'primary') === 'primary'
                ? 'border border-white bg-white text-black hover:bg-neutral-100'
                : 'border border-white/20 bg-neutral-900/80 text-white hover:border-white hover:bg-white hover:text-black',
        ]"
        @click="add"
    >
        <svg v-if="processing" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>
        <span>{{ processing ? 'ADDING…' : (label ?? 'ADD TO CART') }}</span>
    </button>
</template>
