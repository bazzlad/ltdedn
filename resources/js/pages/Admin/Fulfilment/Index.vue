<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface FulfilmentItem {
    id: number;
    product_name: string;
    variant_title_snapshot: string | null;
    sku_code_snapshot: string | null;
    quantity: number;
}

interface FulfilmentOrder {
    id: number;
    source_platform: string;
    external_order_number: string | null;
    artist_name: string | null;
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
    shipment_pushback_status: string | null;
    items: FulfilmentItem[];
}

const props = defineProps<{ orders: FulfilmentOrder[] }>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Fulfilment', href: '/admin/fulfilment' },
];

const forms = reactive<Record<number, { carrier: string; tracking: string; busy: boolean; error: string | null }>>({});

props.orders.forEach((order) => {
    forms[order.id] = {
        carrier: order.shipping_carrier || '',
        tracking: order.shipping_tracking_number || '',
        busy: false,
        error: null,
    };
});

function addressLines(order: FulfilmentOrder): string[] {
    return [
        order.shipping_name,
        order.shipping_line1,
        order.shipping_line2,
        [order.shipping_city, order.shipping_state, order.shipping_postal_code].filter(Boolean).join(' '),
        order.shipping_country,
    ].filter((line): line is string => !!line && line.trim() !== '');
}

function ship(order: FulfilmentOrder): void {
    const form = forms[order.id];
    if (!form || form.busy) return;

    form.busy = true;
    form.error = null;

    router.post(
        `/admin/sales/${order.id}/ship`,
        { carrier: form.carrier.trim(), tracking: form.tracking.trim() },
        {
            preserveScroll: true,
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
            <div>
                <h1 class="text-2xl font-semibold">Fulfilment</h1>
                <p class="text-sm text-muted-foreground">{{ orders.length }} paid orders waiting to ship</p>
            </div>

            <Card v-if="orders.length === 0">
                <CardContent class="py-10 text-center text-sm text-muted-foreground">No orders are waiting for fulfilment.</CardContent>
            </Card>

            <Card v-for="order in orders" :key="order.id">
                <CardHeader class="flex flex-row items-start justify-between gap-4">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <span>{{ order.external_order_number || `Order #${order.id}` }}</span>
                            <Badge variant="secondary">{{ order.source_platform }}</Badge>
                        </CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">{{ order.artist_name || 'Unassigned artist' }} · {{ order.paid_at || 'Paid' }}</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/admin/sales/${order.id}`">Open</Link>
                    </Button>
                </CardHeader>

                <CardContent class="grid gap-6 md:grid-cols-3">
                    <div>
                        <div class="text-xs font-semibold uppercase text-muted-foreground">Ship to</div>
                        <div class="mt-2 text-sm leading-6">
                            <div v-for="line in addressLines(order)" :key="line">{{ line }}</div>
                        </div>
                        <p v-if="order.customer_email" class="mt-2 text-xs text-muted-foreground">{{ order.customer_email }}</p>
                        <p v-if="order.shipping_phone" class="text-xs text-muted-foreground">{{ order.shipping_phone }}</p>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase text-muted-foreground">Items</div>
                        <div v-for="item in order.items" :key="item.id" class="mt-2 text-sm">
                            <span class="font-medium">{{ item.quantity }}x {{ item.product_name }}</span>
                            <span v-if="item.sku_code_snapshot" class="text-muted-foreground"> · {{ item.sku_code_snapshot }}</span>
                            <span v-if="item.variant_title_snapshot" class="text-muted-foreground"> · {{ item.variant_title_snapshot }}</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Carrier</span>
                            <input v-model="forms[order.id].carrier" class="mt-1 w-full rounded border px-2 py-1.5 text-sm" />
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Tracking</span>
                            <input v-model="forms[order.id].tracking" class="mt-1 w-full rounded border px-2 py-1.5 text-sm" />
                        </label>
                        <Button class="w-full" :disabled="forms[order.id].busy || !forms[order.id].carrier || !forms[order.id].tracking" @click="ship(order)">
                            {{ forms[order.id].busy ? 'Saving...' : 'Mark shipped' }}
                        </Button>
                        <p v-if="forms[order.id].error" class="text-xs text-red-600">{{ forms[order.id].error }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
