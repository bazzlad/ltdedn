<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Check, ClipboardCheck } from 'lucide-vue-next';

interface Connection {
    id: number;
    platform: string;
    name: string;
    artist_name: string | null;
    store_url: string | null;
    status: string;
    connection_status: string;
    external_shop_id: string | null;
    external_shop_domain: string | null;
    oauth_scopes: string[];
    webhook_subscription_id: string | null;
    last_connection_error: string | null;
    tested_at: string | null;
    activated_at: string | null;
    webhook_url: string;
    orders_count: number;
    imports_count: number;
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

const props = defineProps<{
    connection: Connection;
    skuChecklist: SkuRow[];
    testOrder: TestOrder;
}>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Connections', href: '/admin/storefront-connections' },
    { title: props.connection.name, href: `/admin/storefront-connections/${props.connection.id}` },
];

const markTest = () => {
    router.post(`/admin/storefront-connections/${props.connection.id}/test`, {}, { preserveScroll: true });
};

const activate = () => {
    router.post(`/admin/storefront-connections/${props.connection.id}/activate`, {}, { preserveScroll: true });
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ connection.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ connection.artist_name || '-' }} · {{ connection.platform }}</p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/admin/storefront-connections">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Connections
                    </Link>
                </Button>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Setup</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 text-sm md:grid-cols-2">
                            <div>
                                <div class="text-muted-foreground">Connection status</div>
                                <Badge variant="outline">{{ connection.connection_status }}</Badge>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Operational status</div>
                                <Badge variant="secondary">{{ connection.status }}</Badge>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Store URL</div>
                                <div class="break-all">{{ connection.store_url || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Store domain</div>
                                <div class="break-all">{{ connection.external_shop_domain || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">External shop ID</div>
                                <div class="break-all">{{ connection.external_shop_id || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground">Webhook subscription</div>
                                <div class="break-all">{{ connection.webhook_subscription_id || '-' }}</div>
                            </div>
                            <div v-if="connection.webhook_url" class="md:col-span-2">
                                <div class="text-muted-foreground">Webhook URL</div>
                                <code class="block rounded border bg-muted px-3 py-2 text-xs break-all">{{ connection.webhook_url }}</code>
                            </div>
                            <div v-if="connection.platform === 'pipe17'" class="md:col-span-2">
                                <div class="text-muted-foreground">Pipe17 fallback setup</div>
                                <div class="rounded border bg-muted px-3 py-2 text-xs">
                                    Pipe17 is fallback-only. Scheduled polling runs only when PIPE17_SCHEDULE_ENABLED is enabled.
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="text-muted-foreground">OAuth scopes</div>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Badge v-for="scope in connection.oauth_scopes" :key="scope" variant="secondary">{{ scope }}</Badge>
                                    <span v-if="connection.oauth_scopes.length === 0">-</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="connection.last_connection_error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            {{ connection.last_connection_error }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Test Order</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div>
                            <div class="text-muted-foreground">State</div>
                            <Badge variant="outline">{{ testOrder.label }}</Badge>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Tested</div>
                            <div>{{ connection.tested_at || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-muted-foreground">Activated</div>
                            <div>{{ connection.activated_at || '-' }}</div>
                        </div>
                        <div v-if="testOrder.detail" class="rounded-md border bg-muted p-3">{{ testOrder.detail }}</div>
                        <div class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" @click="markTest">
                                <ClipboardCheck class="mr-2 h-4 w-4" />
                                Mark Tested
                            </Button>
                            <Button type="button" @click="activate">
                                <Check class="mr-2 h-4 w-4" />
                                Activate
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
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
                                <TableCell class="font-medium">{{ row.sku_code }}</TableCell>
                                <TableCell>{{ row.product_name }}</TableCell>
                                <TableCell>{{ row.store_sku_found === null ? 'Not checked' : row.store_sku_found ? 'Yes' : 'No' }}</TableCell>
                                <TableCell>{{ row.stock_available }}</TableCell>
                                <TableCell>{{ row.editions_available }}</TableCell>
                                <TableCell
                                    ><Badge variant="secondary">{{ row.status }}</Badge></TableCell
                                >
                            </TableRow>
                            <TableRow v-if="skuChecklist.length === 0">
                                <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground"
                                    >No active local SKUs for this artist.</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
