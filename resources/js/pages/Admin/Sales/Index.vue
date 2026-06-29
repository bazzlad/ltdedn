<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';

interface OrderRow {
    id: number;
    status: string;
    source_platform: string;
    external_order_number: string | null;
    artist_name: string | null;
    currency: string;
    total_amount: number;
    customer_email: string | null;
    exception_reason: string | null;
    shipment_pushback_status: string | null;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

defineProps<{ orders: Paginator<OrderRow> }>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Sales', href: '/admin/sales' },
];

function money(amount: number, currency: string): string {
    return `${currency.toUpperCase()} ${(amount / 100).toFixed(2)}`;
}

const formatLinkLabel = (label: string): string => {
    return label.replace(/&amp;laquo;|&laquo;|«/g, '‹').replace(/&amp;raquo;|&raquo;|»/g, '›');
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div>
                <h1 class="text-2xl font-semibold">Sales</h1>
                <p class="text-sm text-muted-foreground">{{ orders.total }} orders across external channels</p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Orders</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Source</TableHead>
                                <TableHead>Artist</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Total</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="order in orders.data" :key="order.id">
                                <TableCell>{{ order.external_order_number || `#${order.id}` }}</TableCell>
                                <TableCell>{{ order.source_platform }}</TableCell>
                                <TableCell>{{ order.artist_name || '-' }}</TableCell>
                                <TableCell>
                                    <Badge :variant="order.exception_reason ? 'destructive' : 'secondary'">{{
                                        order.exception_reason ? 'exception' : order.status
                                    }}</Badge>
                                </TableCell>
                                <TableCell>{{ money(order.total_amount, order.currency) }}</TableCell>
                                <TableCell>{{ order.customer_email || '-' }}</TableCell>
                                <TableCell class="text-right">
                                    <Button as-child variant="outline" size="sm">
                                        <Link :href="`/admin/sales/${order.id}`">View</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-if="orders.links.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <Button
                            v-for="link in orders.links"
                            :key="link.label"
                            as-child
                            size="sm"
                            :variant="link.active ? 'default' : 'outline'"
                            :disabled="!link.url"
                        >
                            <Link v-if="link.url" :href="link.url">{{ formatLinkLabel(link.label) }}</Link>
                            <span v-else>{{ formatLinkLabel(link.label) }}</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
