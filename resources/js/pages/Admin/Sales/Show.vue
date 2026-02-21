<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';

interface OrderItemRow {
	id: number;
	product_name: string;
	product_slug: string;
	sku_code_snapshot: string | null;
	quantity: number;
	unit_amount: number;
	line_total_amount: number;
	attributes_snapshot: Record<string, string> | null;
}

interface OrderView {
	id: number;
	status: string;
	currency: string;
	subtotal_amount: number;
	shipping_amount: number;
	total_amount: number;
	customer_email: string | null;
	user: { name: string; email: string } | null;
	stripe_checkout_session_id: string | null;
	stripe_payment_intent_id: string | null;
	paid_at: string | null;
	created_at: string;
	items: OrderItemRow[];
}

const props = defineProps<{ order: OrderView }>();

const breadcrumbs: BreadcrumbItemType[] = [
	{ title: 'Admin', href: '/admin' },
	{ title: 'Sales', href: '/admin/sales' },
	{ title: 'Order #' + props.order.id, href: '#' },
];

function money(pence: number, currency: string): string {
	return currency.toUpperCase() + ' ' + (pence / 100).toFixed(2);
}
</script>

<template>
	<AdminLayout :breadcrumbs="breadcrumbs">
		<div class="space-y-4 p-8 pt-6">
			<div><Link href="/admin/sales" class="text-sm text-blue-600">← Back to sales</Link></div>
			<Card>
				<CardHeader><CardTitle>Order #{{ props.order.id }}</CardTitle></CardHeader>
				<CardContent class="space-y-2 text-sm">
					<div>Status: {{ props.order.status }}</div>
					<div>Customer: {{ props.order.customer_email || props.order.user?.email || 'Unknown' }}</div>
					<div>Total: {{ money(props.order.total_amount, props.order.currency) }}</div>
					<div>Stripe Checkout: {{ props.order.stripe_checkout_session_id || 'N/A' }}</div>
					<div>Payment Intent: {{ props.order.stripe_payment_intent_id || 'N/A' }}</div>
				</CardContent>
			</Card>
			<Card>
				<CardHeader><CardTitle>Items</CardTitle></CardHeader>
				<CardContent>
					<div v-for="item in props.order.items" :key="item.id" class="border-b py-2 text-sm">
						<div class="font-medium">{{ item.product_name }} {{ item.sku_code_snapshot ? '(' + item.sku_code_snapshot + ')' : '' }}</div>
						<div>Qty {{ item.quantity }} • {{ money(item.line_total_amount, props.order.currency) }}</div>
					</div>
				</CardContent>
			</Card>
		</div>
	</AdminLayout>
</template>
