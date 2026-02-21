<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
	status: 'success' | 'cancel';
	order: {
		id: number;
		status: string;
	};
}>();
</script>

<template>
	<Head title="Checkout result - LTD/EDN" />
	<div class="mx-auto max-w-2xl px-6 py-12 text-white">
		<h1 class="text-3xl font-bold">
			{{ props.status === 'success' ? 'Thanks for your order' : 'Checkout cancelled' }}
		</h1>
		<p class="mt-3 text-white/70">Order #{{ props.order.id }} • {{ props.order.status }}</p>
		<Link href="/shop" class="mt-6 inline-block rounded bg-white px-4 py-2 text-black">Back to shop</Link>
	</div>
</template>
