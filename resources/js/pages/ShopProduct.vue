<script setup lang="ts">
import AddToCartButton from '@/components/Shop/AddToCartButton.vue';
import ShopLayout from '@/layouts/ShopLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type ShopSku = {
    id: number;
    sku_code: string;
    price: string;
    price_amount: number;
    currency: string;
    stock_available: number;
    attributes: Record<string, string>;
    variant_value_ids: number[];
};

type VariantValue = { id: number; value: string };
type VariantAxis = { id: number; name: string; values: VariantValue[] };

type ShopProduct = {
    id: number;
    artist_id: number;
    name: string;
    skus: ShopSku[];
    image: string | null;
    artist_name: string | null;
    artist_slug: string | null;
    base_price: string | number | null;
    standard_available: number;
    variant_axes: VariantAxis[];
};

const props = defineProps<{
    product: ShopProduct;
}>();

const hasAxes = computed(() => props.product.variant_axes.length > 0);

const selectedByAxis = reactive<Record<number, number | null>>({});
for (const axis of props.product.variant_axes) {
    selectedByAxis[axis.id] = null;
}

function selectValue(axisId: number, valueId: number): void {
    selectedByAxis[axisId] = selectedByAxis[axisId] === valueId ? null : valueId;
}

const resolvedSku = computed<ShopSku | null>(() => {
    if (!hasAxes.value) return null;
    const selectedIds = Object.values(selectedByAxis).filter((v): v is number => v != null);
    if (selectedIds.length !== props.product.variant_axes.length) return null;

    const target = [...selectedIds].sort((a, b) => a - b).join(',');
    for (const sku of props.product.skus) {
        const skuIds = [...(sku.variant_value_ids || [])].sort((a, b) => a - b).join(',');
        if (skuIds === target) return sku;
    }
    return null;
});

function skuLabel(sku: ShopSku): string {
    const attrs = Object.values(sku.attributes || {}).join(' / ');
    return attrs !== '' ? attrs : sku.sku_code;
}

function standardPrice(): string {
    const p = props.product.base_price;
    if (p === null || p === undefined || p === '') return '0.00';
    return Number(p).toFixed(2);
}

const legacySkus = computed<ShopSku[]>(() => (hasAxes.value ? [] : props.product.skus));
</script>

<template>
    <Head :title="`${props.product.name} – Shop – LTD/EDN`" />

    <ShopLayout>
        <div class="mb-6">
            <Link href="/shop" class="inline-flex items-center gap-2 text-[0.625rem] font-bold tracking-widest text-white/60 hover:text-white">
                <span aria-hidden="true">←</span> BACK TO SHOP
            </Link>
        </div>

        <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,480px)] lg:gap-16">
            <div class="mx-auto w-full max-w-xl lg:mx-0">
                <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden border-4 border-white/80 bg-neutral-900">
                    <img
                        v-if="props.product.image"
                        :src="props.product.image"
                        :alt="props.product.name"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <svg v-else class="h-16 w-16 text-neutral-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
                    </svg>
                </div>
            </div>

            <section class="max-w-xl">
                <div class="space-y-1 text-sm tracking-widest">
                    <p>
                        <span class="text-neutral-500">TITLE</span>
                        <span class="mx-2 text-neutral-600">/</span>
                        <span class="font-bold text-white">{{ props.product.name.toUpperCase() }}</span>
                    </p>
                    <p v-if="props.product.artist_name">
                        <span class="text-neutral-500">ARTIST</span>
                        <span class="mx-1 text-neutral-600">/</span>
                        <Link
                            v-if="props.product.artist_slug"
                            :href="`/shop/${props.product.artist_slug}`"
                            class="font-bold text-white underline decoration-white/40 underline-offset-2 hover:decoration-white"
                            >{{ props.product.artist_name.toUpperCase() }}</Link
                        >
                        <span v-else class="font-bold text-white">{{ props.product.artist_name.toUpperCase() }}</span>
                    </p>
                </div>

                <div v-if="hasAxes" class="mt-6 space-y-5">
                    <div v-for="axis in props.product.variant_axes" :key="axis.id">
                        <div class="mb-2 text-[0.625rem] font-bold tracking-widest text-neutral-500">
                            {{ axis.name.toUpperCase() }}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="value in axis.values"
                                :key="value.id"
                                type="button"
                                :class="[
                                    'border px-3 py-2 text-xs font-bold tracking-wider transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50',
                                    selectedByAxis[axis.id] === value.id
                                        ? 'border-white bg-white text-black'
                                        : 'border-white/20 bg-neutral-900/60 text-white hover:border-white/60',
                                ]"
                                @click="selectValue(axis.id, value.id)"
                            >
                                {{ value.value.toUpperCase() }}
                            </button>
                        </div>
                    </div>

                    <div class="border border-white/10 bg-neutral-900/60 p-5 ring-1 ring-white/10">
                        <div v-if="resolvedSku" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">PRICE</p>
                                <p class="mt-1 text-2xl font-extrabold tracking-tight text-white">£{{ resolvedSku.price }}</p>
                                <p class="mt-1 text-[0.625rem] font-bold tracking-widest text-white/60">STOCK · {{ resolvedSku.stock_available }}</p>
                                <p v-if="resolvedSku.stock_available < 1" class="mt-2 text-xs font-bold tracking-wider text-red-400">SOLD OUT</p>
                            </div>
                            <AddToCartButton
                                :product-id="props.product.id"
                                :product-sku-id="resolvedSku.id"
                                :disabled="resolvedSku.stock_available < 1"
                            />
                        </div>
                        <p v-else class="text-xs font-bold tracking-wider text-white/60">PICK A VALUE FOR EACH OPTION TO SEE AVAILABILITY</p>
                    </div>
                </div>

                <div v-else class="mt-6 space-y-3">
                    <div
                        v-if="props.product.standard_available > 0"
                        class="flex items-center justify-between gap-4 border border-white/10 bg-neutral-900/60 p-5 ring-1 ring-white/10"
                    >
                        <div>
                            <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">STANDARD</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-white">£{{ standardPrice() }}</p>
                            <p class="mt-1 text-[0.625rem] font-bold tracking-widest text-white/60">STOCK · {{ props.product.standard_available }}</p>
                        </div>
                        <AddToCartButton :product-id="props.product.id" :product-sku-id="null" />
                    </div>

                    <div
                        v-for="sku in legacySkus"
                        :key="sku.id"
                        class="flex items-center justify-between gap-4 border border-white/10 bg-neutral-900/60 p-5 ring-1 ring-white/10"
                    >
                        <div>
                            <p class="text-[0.625rem] font-bold tracking-widest text-neutral-500">
                                {{ skuLabel(sku).toUpperCase() }}
                            </p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-white">£{{ sku.price }}</p>
                            <p class="mt-1 text-[0.625rem] font-bold tracking-widest text-white/60">STOCK · {{ sku.stock_available }}</p>
                        </div>
                        <AddToCartButton :product-id="props.product.id" :product-sku-id="sku.id" :disabled="sku.stock_available < 1" />
                    </div>
                </div>
            </section>
        </div>
    </ShopLayout>
</template>
