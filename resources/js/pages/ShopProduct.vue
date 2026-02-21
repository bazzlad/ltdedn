<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { checkout as shopCheckout } from '@/routes/shop';

type ShopSku = {
	id: number;
	sku_code: string;
	price: string;
	price_amount: number;
	currency: string;
	stock_available: number;
	attributes: Record<string, string>;
};

type ShopProduct = {
	id: number;
	artist_id: number;
	name: string;
	skus: ShopSku[];
	image: string | null;
	artist_name: string | null;
	base_price: string | number | null;
	standard_available: number;
};

const props = defineProps<{
	product: ShopProduct;
}>();

const form = useForm({
	artist_id: props.product.artist_id,
	product_id: props.product.id,
	product_sku_id: null as number | null,
});

function buySku(sku: ShopSku): void {
	form.product_sku_id = sku.id;
	form.post(shopCheckout.url());
}

function buyStandard(): void {
	form.product_sku_id = null;
	form.post(shopCheckout.url());
}

function skuLabel(sku: ShopSku): string {
	const attrs = Object.values(sku.attributes || {}).join(' / ');
	return attrs !== '' ? attrs : sku.sku_code;
}

function standardPrice(): string {
	const p = props.product.base_price;
	if (p === null || p === undefined || p === '') return '0.00';
	return Number(p).toFixed(2);
}
</script>

<template>
	<Head :title="`${props.product.name} - Shop - LTD/EDN`" />

	<div class="mx-auto max-w-4xl px-6 py-10 text-white">
		<h1 class="text-3xl font-bold">{{ props.product.name }}</h1>
		<p class="mt-2 text-white/70">Edition product checkout.</p>

		<div class="mt-8 space-y-2">
			<div
				v-if="props.product.standard_available > 0"
				class="flex items-center justify-between rounded border border-white/10 p-2"
			>
				<div>
					<div class="text-sm text-white">Standard</div>
					<div class="text-xs text-white/70">£{{ standardPrice() }} • Stock {{ props.product.standard_available }}</div>
				</div>
				<button
					:disabled="form.processing"
					class="rounded bg-white px-3 py-1 text-sm text-black disabled:opacity-40"
					@click="buyStandard"
				>
					Buy
				</button>
			</div>

			<div
				v-for="sku in props.product.skus"
				:key="sku.id"
				class="flex items-center justify-between rounded border border-white/10 p-2"
			>
				<div>
					<div class="text-sm text-white">{{ skuLabel(sku) }}</div>
					<div class="text-xs text-white/70">£{{ sku.price }} • Stock {{ sku.stock_available }}</div>
				</div>
				<button
					:disabled="sku.stock_available < 1 || form.processing"
					class="rounded bg-white px-3 py-1 text-sm text-black disabled:opacity-40"
					@click="buySku(sku)"
				>
					Buy
				</button>
			</div>
		</div>
	</div>
</template>
