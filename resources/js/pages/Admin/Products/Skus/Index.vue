<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface Product { id: number; name: string; slug: string; }
interface Sku { id: number; sku_code: string; price_amount: number; currency: string; stock_on_hand: number; stock_reserved: number; stock_available: number; is_active: boolean; attributes: Record<string, string>; }
interface Adjustment { id: number; sku_id: number; delta_on_hand: number; before_on_hand: number; after_on_hand: number; reason: string; source: string; actor_name: string | null; created_at: string; }
interface AdjustmentFilters { q: string; reason: string; source: string; }
interface SkuDraft { sku_code: string; price_amount: number; currency: string; stock_on_hand: number; is_active: boolean; attributes_text: string; }

const props = defineProps<{ product: Product; skus: Sku[]; adjustments: Adjustment[]; adjustmentFilters: AdjustmentFilters }>();

const breadcrumbs: BreadcrumbItemType[] = [
	{ title: 'Admin', href: '/admin' },
	{ title: 'Products', href: '/admin/products' },
	{ title: props.product.name, href: '/admin/products/' + props.product.id },
	{ title: 'SKUs', href: '#' },
];

const createForm = useForm({ sku_code: '', price_amount: 0, currency: 'gbp', stock_on_hand: 0, is_active: true, attributes_text: '' });

const filterForm = useForm({
	q: props.adjustmentFilters.q || '',
	reason: props.adjustmentFilters.reason || '',
	source: props.adjustmentFilters.source || '',
});

const drafts = reactive<Record<number, SkuDraft>>({});

function toAttributesMap(value: string): Record<string, string> {
	const map: Record<string, string> = {};
	value.split(',').forEach(function (pair) {
		const parts = pair.split(':');
		if (parts.length === 2) {
			map[parts[0].trim()] = parts[1].trim();
		}
	});
	return map;
}

function toAttributesText(attributes: Record<string, string>): string {
	return Object.keys(attributes || {}).map(function (k) { return k + ':' + attributes[k]; }).join(',');
}

function getDraft(sku: Sku): SkuDraft {
	if (!drafts[sku.id]) {
		drafts[sku.id] = {
			sku_code: sku.sku_code,
			price_amount: sku.price_amount,
			currency: sku.currency,
			stock_on_hand: sku.stock_on_hand,
			is_active: sku.is_active,
			attributes_text: toAttributesText(sku.attributes),
		};
	}
	return drafts[sku.id];
}

function resetDraft(sku: Sku): void {
	drafts[sku.id] = {
		sku_code: sku.sku_code,
		price_amount: sku.price_amount,
		currency: sku.currency,
		stock_on_hand: sku.stock_on_hand,
		is_active: sku.is_active,
		attributes_text: toAttributesText(sku.attributes),
	};
}

function createSku(): void {
	createForm.transform(function (data) {
		return { ...data, attributes: toAttributesMap(data.attributes_text) };
	}).post('/admin/products/' + props.product.id + '/skus');
}

function updateSku(sku: Sku): void {
	const draft = getDraft(sku);
	const form = useForm({
		sku_code: draft.sku_code,
		price_amount: draft.price_amount,
		currency: draft.currency,
		stock_on_hand: draft.stock_on_hand,
		is_active: draft.is_active,
		attributes_text: draft.attributes_text,
	});

	form.transform(function (data) {
		return {
			sku_code: data.sku_code,
			price_amount: data.price_amount,
			currency: data.currency,
			stock_on_hand: data.stock_on_hand,
			is_active: data.is_active,
			attributes: toAttributesMap(data.attributes_text),
		};
	}).put('/admin/products/' + props.product.id + '/skus/' + sku.id);
}

function deleteSku(sku: Sku): void {
	if (!confirm('Delete SKU ' + sku.sku_code + '?')) {
		return;
	}
	useForm({}).delete('/admin/products/' + props.product.id + '/skus/' + sku.id);
}

function applyFilters(): void {
	filterForm.get('/admin/products/' + props.product.id + '/skus', { preserveState: true, replace: true });
}

function clearFilters(): void {
	filterForm.q = '';
	filterForm.reason = '';
	filterForm.source = '';
	applyFilters();
}
</script>

<template>
	<AdminLayout :breadcrumbs="breadcrumbs">
		<div class="space-y-4 p-8 pt-6">
			<Card>
				<CardHeader><CardTitle>Create SKU</CardTitle></CardHeader>
				<CardContent class="grid gap-3 md:grid-cols-3">
					<div><Label>SKU Code</Label><Input v-model="createForm.sku_code" /></div>
					<div><Label>Price (pence)</Label><Input v-model="createForm.price_amount" type="number" min="0" /></div>
					<div><Label>Currency</Label><Input v-model="createForm.currency" maxlength="3" /></div>
					<div><Label>Stock On Hand</Label><Input v-model="createForm.stock_on_hand" type="number" min="0" /></div>
					<div class="md:col-span-2"><Label>Attributes</Label><Input v-model="createForm.attributes_text" placeholder="Size:XL,Color:Black" /></div>
					<div class="md:col-span-3"><Button @click="createSku" :disabled="createForm.processing">Create SKU</Button></div>
				</CardContent>
			</Card>

			<Card>
				<CardHeader><CardTitle>Existing SKUs</CardTitle></CardHeader>
				<CardContent>
					<div v-for="sku in props.skus" :key="sku.id" class="mb-3 rounded border p-3">
						<div class="grid gap-2 md:grid-cols-6">
							<div><Label>Code</Label><Input v-model="getDraft(sku).sku_code" /></div>
							<div><Label>Price</Label><Input type="number" min="0" v-model="getDraft(sku).price_amount" /></div>
							<div><Label>Currency</Label><Input maxlength="3" v-model="getDraft(sku).currency" /></div>
							<div><Label>On hand</Label><Input type="number" min="0" v-model="getDraft(sku).stock_on_hand" /></div>
							<div><Label>Reserved</Label><div class="pt-2 text-sm">{{ sku.stock_reserved }}</div></div>
							<div><Label>Available</Label><div class="pt-2 text-sm">{{ sku.stock_available }}</div></div>
						</div>
						<div class="mt-2"><Label>Attributes</Label><Input v-model="getDraft(sku).attributes_text" placeholder="Size:XL,Color:Black" /></div>
						<div class="mt-2 flex gap-2">
							<Button size="sm" variant="outline" @click="updateSku(sku)">Save</Button>
							<Button size="sm" variant="secondary" @click="resetDraft(sku)">Reset</Button>
							<Button size="sm" variant="destructive" @click="deleteSku(sku)">Delete</Button>
						</div>
					</div>
				</CardContent>
			</Card>

			<Card>
				<CardHeader><CardTitle>Stock adjustment history</CardTitle></CardHeader>
				<CardContent>
					<div class="mb-4 grid gap-2 md:grid-cols-4">
						<div class="md:col-span-2"><Label>Search</Label><Input v-model="filterForm.q" placeholder="reason, source, actor" /></div>
						<div><Label>Reason</Label><Input v-model="filterForm.reason" placeholder="manual_stock_update" /></div>
						<div><Label>Source</Label><Input v-model="filterForm.source" placeholder="admin" /></div>
					</div>
					<div class="mb-4 flex gap-2">
						<Button size="sm" variant="outline" @click="applyFilters">Apply filters</Button>
						<Button size="sm" variant="secondary" @click="clearFilters">Clear</Button>
					</div>
					<div v-if="props.adjustments.length === 0" class="text-sm text-muted-foreground">No stock adjustments yet.</div>
					<div v-for="adj in props.adjustments" :key="adj.id" class="border-b py-2 text-sm">
						SKU #{{ adj.sku_id }} • Δ {{ adj.delta_on_hand }} • {{ adj.before_on_hand }} → {{ adj.after_on_hand }} • {{ adj.reason }} • {{ adj.source }} • {{ adj.actor_name || 'system' }}
					</div>
				</CardContent>
			</Card>
		</div>
	</AdminLayout>
</template>
