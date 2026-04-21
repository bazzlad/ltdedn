<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

interface OrderEventRow {
    id: number;
    type: string;
    payload: Record<string, unknown> | null;
    actor: { id: number; name: string } | null;
    created_at: string;
}

interface OrderView {
    id: number;
    status: string;
    currency: string;
    subtotal_amount: number;
    shipping_amount: number;
    tax_amount: number;
    total_amount: number;
    refunded_amount: number;
    last_refunded_at: string | null;
    shipping_carrier: string | null;
    shipping_tracking_number: string | null;
    shipped_at: string | null;
    shipping_name: string | null;
    shipping_line1: string | null;
    shipping_line2: string | null;
    shipping_city: string | null;
    shipping_postal_code: string | null;
    shipping_country: string | null;
    customer_email: string | null;
    user: { name: string; email: string } | null;
    stripe_checkout_session_id: string | null;
    stripe_payment_intent_id: string | null;
    paid_at: string | null;
    created_at: string;
    items: OrderItemRow[];
    events: OrderEventRow[];
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

const refundableMinor = computed(() => Math.max(props.order.total_amount - props.order.refunded_amount, 0));
const canShip = computed(() => props.order.status === 'paid');
const canRefund = computed(() => props.order.status === 'paid' && refundableMinor.value > 0 && !!props.order.stripe_payment_intent_id);

const shipOpen = ref(false);
const shipCarrier = ref(props.order.shipping_carrier ?? '');
const shipTracking = ref(props.order.shipping_tracking_number ?? '');
const shipBusy = ref(false);

const refundOpen = ref(false);
const refundAmountPounds = ref<string>('');
const refundReason = ref('');
const refundBusy = ref(false);

function submitShip(): void {
    if (shipBusy.value) return;
    shipBusy.value = true;
    router.post(
        `/admin/sales/${props.order.id}/ship`,
        { carrier: shipCarrier.value, tracking: shipTracking.value },
        {
            preserveScroll: true,
            onFinish: () => {
                shipBusy.value = false;
            },
            onSuccess: () => {
                shipOpen.value = false;
            },
        },
    );
}

function submitRefund(): void {
    if (refundBusy.value) return;
    refundBusy.value = true;

    const parsed = refundAmountPounds.value.trim() === '' ? 0 : Math.round(parseFloat(refundAmountPounds.value) * 100);

    router.post(
        `/admin/sales/${props.order.id}/refund`,
        { amount_minor: parsed, reason: refundReason.value },
        {
            preserveScroll: true,
            onFinish: () => {
                refundBusy.value = false;
            },
            onSuccess: () => {
                refundOpen.value = false;
            },
        },
    );
}

function eventLabel(type: string): string {
    switch (type) {
        case 'shipped':
            return 'Shipped';
        case 'shipping_updated':
            return 'Shipping updated';
        case 'refunded_partial':
            return 'Refunded (partial)';
        case 'refunded_full':
            return 'Refunded (full)';
        case 'refund_failed':
            return 'Refund failed';
        case 'stripe_refund_webhook':
            return 'Stripe refund (dashboard)';
        case 'dispute_created':
            return 'Dispute opened';
        default:
            return type;
    }
}
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div>
                <Link href="/admin/sales" class="text-sm text-blue-600">← Back to sales</Link>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Order #{{ props.order.id }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div>
                        Status: <span class="font-medium">{{ props.order.status }}</span>
                    </div>
                    <div>
                        Customer:
                        <span class="font-medium">{{ props.order.shipping_name || props.order.user?.name || 'Unknown buyer' }}</span>
                        <span v-if="props.order.customer_email || props.order.user?.email">
                            • {{ props.order.customer_email || props.order.user?.email }}
                        </span>
                    </div>
                    <div>Subtotal: {{ money(props.order.subtotal_amount, props.order.currency) }}</div>
                    <div>Shipping: {{ money(props.order.shipping_amount, props.order.currency) }}</div>
                    <div>Tax: {{ money(props.order.tax_amount, props.order.currency) }}</div>
                    <div class="font-semibold">Total: {{ money(props.order.total_amount, props.order.currency) }}</div>
                    <div v-if="props.order.refunded_amount > 0" class="text-red-600">
                        Refunded: {{ money(props.order.refunded_amount, props.order.currency) }}
                    </div>
                    <div>Stripe Checkout: {{ props.order.stripe_checkout_session_id || 'N/A' }}</div>
                    <div>Payment Intent: {{ props.order.stripe_payment_intent_id || 'N/A' }}</div>
                </CardContent>
            </Card>

            <Card v-if="props.order.shipping_line1">
                <CardHeader>
                    <CardTitle>Shipping</CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-sm">
                    <div>{{ props.order.shipping_name }}</div>
                    <div>
                        {{ props.order.shipping_line1 }}<span v-if="props.order.shipping_line2">, {{ props.order.shipping_line2 }}</span>
                    </div>
                    <div>{{ props.order.shipping_city }} {{ props.order.shipping_postal_code }}</div>
                    <div>{{ props.order.shipping_country }}</div>
                    <div v-if="props.order.shipped_at" class="mt-2 text-xs text-gray-600">
                        Shipped {{ props.order.shipped_at }} via {{ props.order.shipping_carrier }} — {{ props.order.shipping_tracking_number }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Items</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-for="item in props.order.items" :key="item.id" class="border-b py-2 text-sm">
                        <div class="font-medium">{{ item.product_name }} {{ item.sku_code_snapshot ? '(' + item.sku_code_snapshot + ')' : '' }}</div>
                        <div>Qty {{ item.quantity }} • {{ money(item.line_total_amount, props.order.currency) }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Actions</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="!canShip"
                        class="rounded bg-black px-3 py-1 text-sm text-white disabled:opacity-40"
                        @click="shipOpen = !shipOpen"
                    >
                        {{ props.order.shipped_at ? 'Update tracking' : 'Mark shipped' }}
                    </button>
                    <button
                        type="button"
                        :disabled="!canRefund"
                        class="rounded border border-black px-3 py-1 text-sm text-black disabled:opacity-40"
                        @click="refundOpen = !refundOpen"
                    >
                        Refund
                    </button>
                </CardContent>

                <CardContent v-if="shipOpen" class="space-y-2 border-t pt-4 text-sm">
                    <label class="block">
                        <span class="text-gray-700">Carrier</span>
                        <input v-model="shipCarrier" type="text" class="mt-1 w-full rounded border px-2 py-1" placeholder="e.g. Royal Mail" />
                    </label>
                    <label class="block">
                        <span class="text-gray-700">Tracking number</span>
                        <input v-model="shipTracking" type="text" class="mt-1 w-full rounded border px-2 py-1" />
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="text-gray-500" @click="shipOpen = false">Cancel</button>
                        <button
                            type="button"
                            :disabled="shipBusy || !shipCarrier || !shipTracking"
                            class="rounded bg-black px-3 py-1 text-white disabled:opacity-40"
                            @click="submitShip"
                        >
                            {{ shipBusy ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </CardContent>

                <CardContent v-if="refundOpen" class="space-y-2 border-t pt-4 text-sm">
                    <p class="text-gray-600">
                        Refundable: {{ money(refundableMinor, props.order.currency) }}. Leave amount blank to refund the full remaining balance.
                    </p>
                    <label class="block">
                        <span class="text-gray-700">Amount ({{ props.order.currency.toUpperCase() }})</span>
                        <input
                            v-model="refundAmountPounds"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 w-full rounded border px-2 py-1"
                            placeholder="e.g. 9.99"
                        />
                    </label>
                    <label class="block">
                        <span class="text-gray-700">Reason</span>
                        <input
                            v-model="refundReason"
                            type="text"
                            class="mt-1 w-full rounded border px-2 py-1"
                            placeholder="Customer request, damaged item, etc."
                        />
                    </label>
                    <p class="text-xs text-gray-500">Refunds are final. Inventory is not automatically restocked.</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="text-gray-500" @click="refundOpen = false">Cancel</button>
                        <button
                            type="button"
                            :disabled="refundBusy || !refundReason"
                            class="rounded border border-red-600 bg-white px-3 py-1 text-red-600 disabled:opacity-40"
                            @click="submitRefund"
                        >
                            {{ refundBusy ? 'Processing…' : 'Issue refund' }}
                        </button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Timeline</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div v-if="props.order.events.length === 0" class="text-gray-500">No events yet.</div>
                    <div v-for="event in props.order.events" :key="event.id" class="border-b pb-2">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ eventLabel(event.type) }}</span>
                            <span class="text-xs text-gray-500">{{ event.created_at }}</span>
                        </div>
                        <div class="text-xs text-gray-600">
                            {{ event.actor ? event.actor.name : 'system' }}
                        </div>
                        <pre v-if="event.payload" class="mt-1 overflow-x-auto text-xs text-gray-500">{{
                            JSON.stringify(event.payload, null, 2)
                        }}</pre>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
