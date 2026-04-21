<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

interface FulfilmentItem {
    id: number;
    product_name: string;
    sku_code_snapshot: string | null;
    quantity: number;
    line_total_amount: number;
}

interface FulfilmentOrder {
    id: number;
    currency: string;
    total_amount: number;
    paid_at: string | null;
    customer_email: string | null;
    shipping_name: string | null;
    shipping_phone: string | null;
    shipping_line1: string | null;
    shipping_line2: string | null;
    shipping_city: string | null;
    shipping_state: string | null;
    shipping_postal_code: string | null;
    shipping_country: string | null;
    shipping_carrier: string | null;
    shipping_tracking_number: string | null;
    items: FulfilmentItem[];
}

const props = defineProps<{ orders: FulfilmentOrder[] }>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Fulfilment', href: '#' },
];

const LAST_CARRIER_KEY = 'ltdedn.fulfilment.lastCarrier';

const forms = reactive<Record<number, { carrier: string; tracking: string; busy: boolean; error: string | null }>>({});
const copied = ref<number | null>(null);

props.orders.forEach((order) => {
    forms[order.id] = {
        carrier: order.shipping_carrier || localStorage.getItem(LAST_CARRIER_KEY) || '',
        tracking: order.shipping_tracking_number || '',
        busy: false,
        error: null,
    };
});

function money(pence: number, currency: string): string {
    return currency.toUpperCase() + ' ' + (pence / 100).toFixed(2);
}

function addressLines(order: FulfilmentOrder): string[] {
    const cityLine = [order.shipping_city, order.shipping_postal_code].filter(Boolean).join(' ');
    return [
        order.shipping_name,
        order.shipping_line1,
        order.shipping_line2,
        cityLine,
        order.shipping_state,
        order.shipping_country,
    ].filter((line): line is string => !!line && line.trim() !== '');
}

async function copyAddress(order: FulfilmentOrder): Promise<void> {
    const text = addressLines(order).join('\n');
    try {
        await navigator.clipboard.writeText(text);
        copied.value = order.id;
        setTimeout(() => {
            if (copied.value === order.id) copied.value = null;
        }, 1500);
    } catch {
        // clipboard unavailable — silent
    }
}

function ship(order: FulfilmentOrder): void {
    const form = forms[order.id];
    if (!form || form.busy) return;
    if (!form.carrier.trim() || !form.tracking.trim()) {
        form.error = 'Carrier and tracking are required.';
        return;
    }

    form.busy = true;
    form.error = null;

    router.post(
        `/admin/sales/${order.id}/ship`,
        { carrier: form.carrier.trim(), tracking: form.tracking.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                localStorage.setItem(LAST_CARRIER_KEY, form.carrier.trim());
            },
            onError: (errors) => {
                form.error = (errors.shipping as string) || 'Unable to mark as shipped.';
            },
            onFinish: () => {
                form.busy = false;
            },
        },
    );
}
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Orders to ship</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ props.orders.length }} paid {{ props.orders.length === 1 ? 'order' : 'orders' }} waiting to be posted.
                    </p>
                </div>
            </div>

            <Card v-if="props.orders.length === 0">
                <CardContent class="py-10 text-center text-sm text-muted-foreground">
                    Nothing to ship right now. Paid orders will show up here automatically.
                </CardContent>
            </Card>

            <Card v-for="order in props.orders" :key="order.id">
                <CardHeader class="flex flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <span>Order #{{ order.id }}</span>
                            <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">PAID</span>
                        </CardTitle>
                        <div class="mt-1 text-xs text-muted-foreground">
                            Paid {{ order.paid_at || 'recently' }} • {{ money(order.total_amount, order.currency) }}
                        </div>
                    </div>
                    <Link :href="'/admin/sales/' + order.id" class="text-sm text-blue-600">Open in Sales →</Link>
                </CardHeader>

                <CardContent class="grid gap-6 md:grid-cols-2">
                    <div>
                        <div class="mb-1 flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Ship to</span>
                            <Button size="sm" variant="outline" class="h-6 px-2 text-xs" @click="copyAddress(order)">
                                {{ copied === order.id ? 'Copied!' : 'Copy' }}
                            </Button>
                        </div>
                        <div v-if="addressLines(order).length === 0" class="text-sm italic text-red-600">
                            No shipping address on file — check Stripe.
                        </div>
                        <div v-else class="text-sm leading-6">
                            <div v-for="(line, idx) in addressLines(order)" :key="idx">{{ line }}</div>
                        </div>
                        <div v-if="order.customer_email" class="mt-2 text-xs text-muted-foreground">
                            Contact: {{ order.customer_email }}<span v-if="order.shipping_phone"> • {{ order.shipping_phone }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 text-xs font-semibold uppercase text-muted-foreground">Items</div>
                        <div v-for="item in order.items" :key="item.id" class="text-sm">
                            <span class="font-medium">{{ item.quantity }}× {{ item.product_name }}</span>
                            <span v-if="item.sku_code_snapshot && item.sku_code_snapshot !== 'STANDARD'" class="text-muted-foreground">
                                ({{ item.sku_code_snapshot }})
                            </span>
                        </div>
                    </div>
                </CardContent>

                <CardContent class="border-t pt-4">
                    <div class="grid gap-3 md:grid-cols-[1fr_2fr_auto]">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Carrier</span>
                            <input
                                v-model="forms[order.id].carrier"
                                type="text"
                                class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                                placeholder="e.g. Royal Mail"
                                list="carriers"
                            />
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Tracking number</span>
                            <input
                                v-model="forms[order.id].tracking"
                                type="text"
                                class="mt-1 w-full rounded border px-2 py-1.5 text-sm"
                                placeholder="Required"
                                @keydown.enter="ship(order)"
                            />
                        </label>
                        <div class="flex items-end">
                            <Button
                                class="w-full md:w-auto"
                                :disabled="forms[order.id].busy || !forms[order.id].carrier || !forms[order.id].tracking"
                                @click="ship(order)"
                            >
                                {{ forms[order.id].busy ? 'Shipping…' : 'Mark shipped' }}
                            </Button>
                        </div>
                    </div>
                    <div v-if="forms[order.id].error" class="mt-2 text-xs text-red-600">{{ forms[order.id].error }}</div>
                </CardContent>
            </Card>

            <datalist id="carriers">
                <option value="Royal Mail" />
                <option value="Parcelforce" />
                <option value="DPD" />
                <option value="Evri" />
                <option value="UPS" />
                <option value="FedEx" />
                <option value="DHL" />
            </datalist>
        </div>
    </AdminLayout>
</template>
