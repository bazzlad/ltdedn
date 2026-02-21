<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';

interface OrderRow {
	id: number;
	status: string;
	currency: string;
	total_amount: number;
	customer_email: string | null;
	user_name: string | null;
	stripe_checkout_session_id: string | null;
	stripe_payment_intent_id: string | null;
	paid_at: string | null;
	created_at: string;
}

interface Pagination<T> {
	data: T[];
	links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
	orders: Pagination<OrderRow>;
	filters: { q: string; status: string; from: string; to: string };
	summary: { paid_revenue: number; paid_count: number; pending_count: number };
}>();

const breadcrumbs: BreadcrumbItemType[] = [
	{ title: 'Admin', href: '/admin' },
	{ title: 'Sales', href: '#' },
];

const filterForm = useForm({
	q: props.filters.q || '',
	status: props.filters.status || '',
	from: props.filters.from || '',
	to: props.filters.to || '',
});

function applyFilters(): void {
	filterForm.get('/admin/sales', { preserveState: true, replace: true });
}

function clearFilters(): void {
	filterForm.q = '';
	filterForm.status = '';
	filterForm.from = '';
	filterForm.to = '';
	applyFilters();
}

function money(pence: number, currency: string): string {
	return currency.toUpperCase() + ' ' + (pence / 100).toFixed(2);
}

function exportCsv(): void {
	const params = new URLSearchParams();
	if (filterForm.q) params.set('q', filterForm.q);
	if (filterForm.status) params.set('status', filterForm.status);
	if (filterForm.from) params.set('from', filterForm.from);
	if (filterForm.to) params.set('to', filterForm.to);
	window.location.href = '/admin/sales/export/csv' + (params.toString() ? '?' + params.toString() : '');
}

async function copyRefs(order: OrderRow): Promise<void> {
	const text = [
		'Order #' + order.id,
		'Stripe Checkout Session: ' + (order.stripe_checkout_session_id || 'N/A'),
		'Stripe Payment Intent: ' + (order.stripe_payment_intent_id || 'N/A'),
	].join('\n');

	try {
		await navigator.clipboard.writeText(text);
	} catch {
		// no-op fallback for unsupported clipboard contexts
	}
}
</script>

<template>
	<AdminLayout :breadcrumbs="breadcrumbs">
		<div class="space-y-4 p-8 pt-6">
			<div class="grid gap-4 md:grid-cols-3">
				<Card><CardHeader><CardTitle>Paid revenue</CardTitle></CardHeader><CardContent>{{ money(props.summary.paid_revenue, 'gbp') }}</CardContent></Card>
				<Card><CardHeader><CardTitle>Paid orders</CardTitle></CardHeader><CardContent>{{ props.summary.paid_count }}</CardContent></Card>
				<Card><CardHeader><CardTitle>Pending orders</CardTitle></CardHeader><CardContent>{{ props.summary.pending_count }}</CardContent></Card>
			</div>

			<Card>
				<CardHeader><CardTitle>Filters</CardTitle></CardHeader>
				<CardContent class="grid gap-2 md:grid-cols-5">
					<div><Label>Search</Label><Input v-model="filterForm.q" placeholder="email, stripe ref" /></div>
					<div>
						<Label>Status</Label>
						<select v-model="filterForm.status" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
							<option value="">All</option>
							<option value="pending">Pending</option>
							<option value="paid">Paid</option>
							<option value="failed">Failed</option>
							<option value="cancelled">Cancelled</option>
						</select>
					</div>
					<div><Label>From</Label><Input type="date" v-model="filterForm.from" /></div>
					<div><Label>To</Label><Input type="date" v-model="filterForm.to" /></div>
					<div class="flex items-end gap-2"><Button variant="outline" @click="applyFilters">Apply</Button><Button variant="secondary" @click="clearFilters">Clear</Button></div>
				</CardContent>
			</Card>

			<div class="flex justify-end">
				<Button variant="outline" @click="exportCsv">Export CSV</Button>
			</div>

			<Card>
				<CardHeader><CardTitle>Orders</CardTitle></CardHeader>
				<CardContent>
					<div v-if="props.orders.data.length === 0" class="text-sm text-muted-foreground">No orders found.</div>
					<div v-for="order in props.orders.data" :key="order.id" class="border-b py-3">
						<div class="flex items-center justify-between">
							<div>
								<div class="font-medium">Order #{{ order.id }} • {{ order.status }}</div>
								<div class="text-sm text-muted-foreground">{{ order.customer_email || order.user_name || 'Unknown customer' }}</div>
							</div>
							<div class="text-right">
								<div class="font-medium">{{ money(order.total_amount, order.currency || 'gbp') }}</div>
								<div class="mt-1 flex gap-2 justify-end">
									<Button size="sm" variant="outline" @click="copyRefs(order)">Copy refs</Button>
									<Link :href="'/admin/sales/' + order.id" class="text-sm text-blue-600">View</Link>
								</div>
							</div>
						</div>
					</div>
					<div class="mt-4 flex flex-wrap gap-2">
						<Link v-for="(link, idx) in props.orders.links" :key="idx" :href="link.url || '#'"><Button size="sm" :variant="link.active ? 'default' : 'outline'" :disabled="!link.url" v-html="link.label" /></Link>
					</div>
				</CardContent>
			</Card>
		</div>
	</AdminLayout>
</template>
