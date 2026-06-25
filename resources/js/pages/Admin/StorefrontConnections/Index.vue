<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

interface ConnectionRow {
    id: number;
    platform: string;
    name: string;
    artist_name: string | null;
    store_url: string | null;
    status: string;
    connection_status: string;
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
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Storefront Connections</h1>
                    <p class="text-sm text-muted-foreground">Order Desk intake setup status for artist storefronts</p>
                </div>
                <Button as-child>
                    <Link href="/admin/storefront-connections/create">
                        <Plus class="mr-2 h-4 w-4" />
                        New Connection
                    </Link>
                </Button>
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
                                <TableHead>Connect</TableHead>
                                <TableHead>Orders</TableHead>
                                <TableHead>Imports</TableHead>
                                <TableHead>Last sync</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="connection in connections" :key="connection.id">
                                <TableCell>
                                    <Link class="font-medium underline-offset-4 hover:underline" :href="`/admin/storefront-connections/${connection.id}`">
                                        {{ connection.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>{{ connection.platform }}</TableCell>
                                <TableCell>{{ connection.artist_name || '-' }}</TableCell>
                                <TableCell><Badge variant="secondary">{{ connection.status }}</Badge></TableCell>
                                <TableCell><Badge variant="outline">{{ connection.connection_status }}</Badge></TableCell>
                                <TableCell>{{ connection.orders_count }}</TableCell>
                                <TableCell>{{ connection.imports_count }}</TableCell>
                                <TableCell>{{ connection.last_synced_at || '-' }}</TableCell>
                            </TableRow>
                            <TableRow v-if="connections.length === 0">
                                <TableCell colspan="8" class="py-8 text-center text-sm text-muted-foreground">No storefront connections yet.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
