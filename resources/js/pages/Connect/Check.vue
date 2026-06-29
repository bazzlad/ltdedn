<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserLayout from '@/layouts/UserLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Connection {
    id: number;
    platform: string;
    name: string;
    artist_name: string | null;
    connection_status: string;
    last_connection_error: string | null;
    tested_at: string | null;
    activated_at: string | null;
}

interface SkuRow {
    sku_code: string;
    product_name: string;
    store_sku_found: boolean | null;
    stock_available: number;
    editions_available: number;
    status: string;
}

interface TestOrder {
    state: string;
    label: string;
    detail: string | null;
    import_id: number | null;
    order_id: number | null;
}

defineProps<{
    connection: Connection;
    skuChecklist: SkuRow[];
    testOrder: TestOrder;
}>();
</script>

<template>
    <Head :title="`${connection.name} · LTD EDN Connect`" />

    <UserLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-white">{{ connection.name }}</h1>
                    <p class="text-sm text-neutral-400">{{ connection.artist_name || '-' }} · {{ connection.platform }}</p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/connect/storefronts">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Stores
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card
                    class="border-white/10 bg-white/95 text-neutral-950 lg:col-span-2 [&_.text-muted-foreground]:text-neutral-600 [&_td]:text-neutral-900 [&_th]:text-neutral-600"
                >
                    <CardHeader>
                        <CardTitle>SKU Checklist</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>LTD EDN SKU</TableHead>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Store SKU Found</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Editions</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in skuChecklist" :key="row.sku_code">
                                    <TableCell class="font-medium text-neutral-950">{{ row.sku_code }}</TableCell>
                                    <TableCell>{{ row.product_name }}</TableCell>
                                    <TableCell>{{ row.store_sku_found === null ? 'Not checked' : row.store_sku_found ? 'Yes' : 'No' }}</TableCell>
                                    <TableCell>{{ row.stock_available }}</TableCell>
                                    <TableCell>{{ row.editions_available }}</TableCell>
                                    <TableCell
                                        ><Badge variant="secondary">{{ row.status }}</Badge></TableCell
                                    >
                                </TableRow>
                                <TableRow v-if="skuChecklist.length === 0">
                                    <TableCell colspan="6" class="py-8 text-center text-sm text-neutral-600"
                                        >No active local SKUs for this artist.</TableCell
                                    >
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card class="border-white/10 bg-white/95 text-neutral-950 [&_.text-muted-foreground]:text-neutral-600">
                    <CardHeader>
                        <CardTitle>Connection</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div>
                            <div class="text-muted-foreground">Status</div>
                            <Badge variant="outline">{{ connection.connection_status }}</Badge>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Test order</div>
                            <Badge variant="secondary">{{ testOrder.label }}</Badge>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Tested</div>
                            <div>{{ connection.tested_at || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Ready</div>
                            <div>{{ connection.activated_at || '-' }}</div>
                        </div>
                        <div v-if="connection.last_connection_error" class="rounded-md border border-red-200 bg-red-50 p-3 text-red-700">
                            {{ connection.last_connection_error }}
                        </div>
                        <div v-if="testOrder.detail" class="rounded-md border border-neutral-200 bg-neutral-100 p-3 text-neutral-700">
                            {{ testOrder.detail }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </UserLayout>
</template>
