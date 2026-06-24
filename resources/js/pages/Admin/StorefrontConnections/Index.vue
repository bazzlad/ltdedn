<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface ConnectionRow {
    id: number;
    platform: string;
    name: string;
    artist_name: string | null;
    store_url: string | null;
    status: string;
    last_synced_at: string | null;
    orders_count: number;
    imports_count: number;
}

defineProps<{ connections: ConnectionRow[] }>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Connections', href: '/admin/storefront-connections' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div>
                <h1 class="text-2xl font-semibold">Storefront Connections</h1>
                <p class="text-sm text-muted-foreground">Shopify and Squarespace intake setup status</p>
            </div>

            <Card>
                <CardHeader><CardTitle>Connections</CardTitle></CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Platform</TableHead>
                                <TableHead>Artist</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Orders</TableHead>
                                <TableHead>Imports</TableHead>
                                <TableHead>Last sync</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="connection in connections" :key="connection.id">
                                <TableCell>{{ connection.name }}</TableCell>
                                <TableCell>{{ connection.platform }}</TableCell>
                                <TableCell>{{ connection.artist_name || '-' }}</TableCell>
                                <TableCell><Badge variant="secondary">{{ connection.status }}</Badge></TableCell>
                                <TableCell>{{ connection.orders_count }}</TableCell>
                                <TableCell>{{ connection.imports_count }}</TableCell>
                                <TableCell>{{ connection.last_synced_at || '-' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
