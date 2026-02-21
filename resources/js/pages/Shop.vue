<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

type ShopProduct = {
	id: number;
	name: string;
	image: string | null;
	shop_url: string;
};

const props = defineProps<{
	products: ShopProduct[];
}>();
</script>

<template>
	<Head title="Shop - LTD/EDN" />

	<div class="mx-auto max-w-4xl px-6 py-10 text-white">
		<h1 class="text-3xl font-bold">Shop</h1>
		<p class="mt-2 text-white/70">Choose an item to open its dedicated product URL.</p>

		<div class="mt-8 grid gap-4">
			<div v-for="product in props.products" :key="product.id" class="rounded border border-white/20 p-4">
				<div class="flex items-center justify-between gap-4">
					<div class="text-lg font-semibold">{{ product.name }}</div>
					<Link :href="product.shop_url" class="rounded bg-white px-3 py-1 text-sm text-black">View item</Link>
				</div>
			</div>
		</div>
	</div>
</template>
