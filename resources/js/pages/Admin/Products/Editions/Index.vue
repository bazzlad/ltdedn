<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { ArrowLeft, Hash, Plus, SquarePen, Trash2, User } from 'lucide-vue-next';

interface Artist {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    artist: Artist;
}

interface Owner {
    id: number;
    name: string;
}

interface Edition {
    id: number;
    product_id: number;
    number: number;
    status: string;
    owner_id?: number;
    owner?: Owner;
    qr_code: string;
    qr_short_code?: string;
    created_at: string;
    updated_at: string;
}

interface EditionsData {
    data: Edition[];
    links: Array<{
        url?: string;
        label: string;
        active: boolean;
    }>;
    meta?: {
        current_page: number;
        from: number;
        last_page: number;
        per_page: number;
        to: number;
        total: number;
    };
}

const props = defineProps<{
    product: Product;
    editions: EditionsData;
}>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
    { title: props.product.name, href: `/admin/products/${props.product.id}` },
    { title: 'Editions', href: '#' },
];

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'available':
            return 'default';
        case 'sold':
            return 'secondary';
        case 'redeemed':
            return 'outline';
        case 'pending_transfer':
            return 'secondary';
        case 'invalidated':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'available':
            return 'Available';
        case 'sold':
            return 'Sold';
        case 'redeemed':
            return 'Redeemed';
        case 'pending_transfer':
            return 'Pending Transfer';
        case 'invalidated':
            return 'Invalidated';
        default:
            return status;
    }
};

const deleteEdition = (editionId: number, editionNumber: number) => {
    if (confirm(`Are you sure you want to delete Edition #${editionNumber}?`)) {
        router.delete(`/admin/products/${props.product.id}/editions/${editionId}`);
    }
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/admin/products/${product.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Product
                    </Link>
                </Button>
                <div class="flex-1">
                    <h2 class="text-3xl font-bold tracking-tight">{{ product.name }} - Editions</h2>
                    <p class="text-muted-foreground">Manage individual editions for this product</p>
                </div>
                <div class="flex items-center space-x-2">
                    <Button as-child>
                        <Link :href="`/admin/products/${product.id}/editions/create`">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Edition
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Editions</CardTitle>
                            <CardDescription> {{ editions.meta?.total || 0 }} total editions </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="editions.data.length === 0" class="py-8 text-center">
                            <p class="mb-4 text-muted-foreground">No editions created yet.</p>
                            <Button as-child>
                                <Link :href="`/admin/products/${product.id}/editions/create`">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create your first edition
                                </Link>
                            </Button>
                        </div>

                        <div v-else>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Edition</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead>QR Code</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="edition in editions.data" :key="edition.id">
                                        <TableCell class="font-medium">
                                            <div class="flex items-center space-x-3">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                                    <Hash class="h-4 w-4" />
                                                </div>
                                                <span class="font-mono text-lg">#{{ edition.number }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="getStatusBadgeVariant(edition.status)">
                                                {{ getStatusLabel(edition.status) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div v-if="edition.owner" class="flex items-center space-x-2">
                                                <User class="h-4 w-4 text-muted-foreground" />
                                                <span>{{ edition.owner.name }}</span>
                                            </div>
                                            <span v-else class="text-muted-foreground">No owner</span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="space-y-1">
                                                <div class="font-mono text-xs text-muted-foreground">{{ edition.qr_code.substring(0, 12) }}...</div>
                                                <div v-if="edition.qr_short_code" class="font-mono text-sm">
                                                    {{ edition.qr_short_code }}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-sm text-muted-foreground">
                                            {{ formatDistanceToNow(new Date(edition.created_at), { addSuffix: true }) }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div class="flex items-center justify-end space-x-2">
                                                <Button as-child size="sm" variant="ghost">
                                                    <Link :href="`/admin/products/${product.id}/editions/${edition.id}/edit`">
                                                        <SquarePen class="h-3 w-3" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="deleteEdition(edition.id, edition.number)"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            <!-- Pagination -->
                            <div v-if="editions.meta && editions.meta.last_page > 1" class="flex items-center justify-between space-x-2 py-4">
                                <div class="text-sm text-muted-foreground">
                                    Showing {{ editions.meta.from }} to {{ editions.meta.to }} of {{ editions.meta.total }} results
                                </div>
                                <div class="flex items-center space-x-2">
                                    <Button
                                        v-for="link in editions.links"
                                        :key="link.label"
                                        :variant="link.active ? 'default' : 'outline'"
                                        size="sm"
                                        :disabled="!link.url"
                                        as-child
                                    >
                                        <Link v-if="link.url" :href="link.url">{{ link.label }}</Link>
                                        <span v-else>{{ link.label }}</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
