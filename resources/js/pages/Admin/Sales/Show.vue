<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface OrderDetail {
    id: number;
    status: string;
    source_platform: string;
    external_order_id: string | null;
    external_order_number: string | null;
    source_payment_status: string | null;
    source_fulfilment_status: string | null;
    artist_name: string | null;
    currency: string;
    total_amount: number;
    customer_email: string | null;
    exception_reason: string | null;
    shipment_pushback_status: string | null;
    shipment_pushback_error: string | null;
    shipping_carrier: string | null;
    shipping_tracking_number: string | null;
    items: Array<{ id: number; product_name: string; sku_code_snapshot: string | null; quantity: number; line_total_amount: number }>;
    events: Array<{ id: number; type: string; created_at: string }>;
}

const props = defineProps<{ order: OrderDetail }>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Sales', href: '/admin/sales' },
    { title: props.order.external_order_number || `#${props.order.id}`, href: `/admin/sales/${props.order.id}` },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="grid gap-4 p-8 pt-6 lg:grid-cols-[2fr_1fr]">
            <div class="space-y-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ order.external_order_number || `Order #${order.id}` }}</h1>
                    <p class="text-sm text-muted-foreground">{{ order.source_platform }} · {{ order.artist_name || 'Unassigned artist' }}</p>
                </div>

                <Card v-if="order.exception_reason">
                    <CardHeader><CardTitle>Exception</CardTitle></CardHeader>
                    <CardContent class="text-sm text-red-600">{{ order.exception_reason }}</CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Items</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="item in order.items" :key="item.id" class="flex justify-between text-sm">
                            <span
                                >{{ item.quantity }}x {{ item.product_name }}
                                <span class="text-muted-foreground">{{ item.sku_code_snapshot }}</span></span
                            >
                            <span>{{ order.currency.toUpperCase() }} {{ (item.line_total_amount / 100).toFixed(2) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Events</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div v-for="event in order.events" :key="event.id" class="flex justify-between">
                            <span>{{ event.type }}</span>
                            <span class="text-muted-foreground">{{ event.created_at }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-4">
                <Card>
                    <CardHeader><CardTitle>Status</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div>
                            <Badge>{{ order.status }}</Badge>
                        </div>
                        <div>Payment: {{ order.source_payment_status || '-' }}</div>
                        <div>Fulfilment: {{ order.source_fulfilment_status || '-' }}</div>
                        <div>Pushback: {{ order.shipment_pushback_status || 'pending' }}</div>
                        <div v-if="order.shipment_pushback_error" class="text-red-600">{{ order.shipment_pushback_error }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Shipment</CardTitle></CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div>Carrier: {{ order.shipping_carrier || '-' }}</div>
                        <div>Tracking: {{ order.shipping_tracking_number || '-' }}</div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
