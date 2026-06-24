<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';

interface ImportRow {
    id: number;
    platform: string;
    external_order_id: string | null;
    delivery_id: string | null;
    status: string;
    error_details: string | null;
    order_id: number | null;
    artist_name: string | null;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

defineProps<{ imports: Paginator<ImportRow> }>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'External Imports', href: '/admin/external-imports' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div>
                <h1 class="text-2xl font-semibold">External Imports</h1>
                <p class="text-sm text-muted-foreground">{{ imports.total }} webhook deliveries and normalized imports</p>
            </div>

            <Card>
                <CardHeader><CardTitle>Imports</CardTitle></CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Platform</TableHead>
                                <TableHead>External order</TableHead>
                                <TableHead>Artist</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Error</TableHead>
                                <TableHead class="text-right">Order</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in imports.data" :key="row.id">
                                <TableCell>{{ row.platform }}</TableCell>
                                <TableCell>{{ row.external_order_id || '-' }}</TableCell>
                                <TableCell>{{ row.artist_name || '-' }}</TableCell>
                                <TableCell><Badge variant="secondary">{{ row.status }}</Badge></TableCell>
                                <TableCell class="max-w-md truncate">{{ row.error_details || '-' }}</TableCell>
                                <TableCell class="text-right">
                                    <Button v-if="row.order_id" as-child variant="outline" size="sm">
                                        <Link :href="`/admin/sales/${row.order_id}`">Open</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-if="imports.links.length > 3" class="mt-4 flex flex-wrap gap-2">
                        <Button v-for="link in imports.links" :key="link.label" as-child size="sm" :variant="link.active ? 'default' : 'outline'" :disabled="!link.url">
                            <Link v-if="link.url" :href="link.url" v-html="link.label" />
                            <span v-else v-html="link.label" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
