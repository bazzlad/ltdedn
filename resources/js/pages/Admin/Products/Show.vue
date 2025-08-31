<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, SquarePen, Plus, Eye, Trash2 } from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';
import { computed } from 'vue';
import type { BreadcrumbItemType } from '@/types';

interface Artist {
    id: number;
    name: string;
    slug: string;
}

interface Edition {
    id: number;
    product_id: number;
    name: string;
    format: string;
    price: string;
    stock_quantity: number;
    limited_quantity?: number;
    description?: string;
    sku?: string;
    status: string;
    created_at: string;
    updated_at: string;
}

interface Product {
    id: number;
    artist_id: number;
    title: string;
    slug: string;
    description?: string;
    price?: string;
    status: string;
    type?: string;
    release_date?: string;
    created_at: string;
    updated_at: string;
    artist: Artist;
    editions: Edition[];
}

defineProps<{
    product: Product;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
    { title: 'View Product', href: '#' },
];

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'published':
        case 'available':
            return 'default';
        case 'draft':
        case 'sold_out':
            return 'secondary';
        case 'archived':
        case 'discontinued':
            return 'outline';
        default:
            return 'secondary';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'published':
            return 'Published';
        case 'draft':
            return 'Draft';
        case 'archived':
            return 'Archived';
        case 'available':
            return 'Available';
        case 'sold_out':
            return 'Sold Out';
        case 'discontinued':
            return 'Discontinued';
        default:
            return status;
    }
};

const getTypeLabel = (type?: string) => {
    if (!type) return '-';

    switch (type) {
        case 'album':
            return 'Album';
        case 'single':
            return 'Single';
        case 'ep':
            return 'EP';
        case 'merchandise':
            return 'Merchandise';
        case 'other':
            return 'Other';
        default:
            return type;
    }
};

const formatPrice = (price?: string) => {
    if (!price) return '-';
    return `$${parseFloat(price).toFixed(2)}`;
};

const getFormatLabel = (format: string) => {
    switch (format) {
        case 'vinyl':
            return 'Vinyl';
        case 'cd':
            return 'CD';
        case 'digital':
            return 'Digital';
        case 'cassette':
            return 'Cassette';
        default:
            return format;
    }
};

const deleteEdition = (edition: Edition) => {
    if (confirm(`Are you sure you want to delete the "${edition.name}" edition?`)) {
        // We'll implement this with Inertia router later
        console.log('Delete edition:', edition.id);
    }
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" as-child>
                    <Link href="/admin/products">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Products
                    </Link>
                </Button>
                <div class="flex-1">
                    <h2 class="text-3xl font-bold tracking-tight">{{ product.title }}</h2>
                    <p class="text-muted-foreground">
                        Product details and edition management
                    </p>
                </div>
                <Button as-child>
                    <Link :href="`/admin/products/${product.id}/edit`">
                        <SquarePen class="mr-2 h-4 w-4" />
                        Edit Product
                    </Link>
                </Button>
            </div>

            <!-- Product Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-medium text-sm text-muted-foreground">Title</h4>
                                    <p class="font-medium">{{ product.title }}</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-sm text-muted-foreground">Artist</h4>
                                    <Link
                                        v-if="isAdmin"
                                        :href="`/admin/artists/${product.artist.id}`"
                                        class="font-medium text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ product.artist.name }}
                                    </Link>
                                    <p v-else class="font-medium">{{ product.artist.name }}</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-sm text-muted-foreground">Type</h4>
                                    <p>{{ getTypeLabel(product.type) }}</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-sm text-muted-foreground">Status</h4>
                                    <Badge :variant="getStatusBadgeVariant(product.status)">
                                        {{ getStatusLabel(product.status) }}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 class="font-medium text-sm text-muted-foreground">Price</h4>
                                    <p>{{ formatPrice(product.price) }}</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-sm text-muted-foreground">Release Date</h4>
                                    <p v-if="product.release_date">
                                        {{ new Date(product.release_date).toLocaleDateString() }}
                                    </p>
                                    <p v-else class="text-muted-foreground">Not set</p>
                                </div>
                            </div>
                            <div v-if="product.description">
                                <h4 class="font-medium text-sm text-muted-foreground mb-2">Description</h4>
                                <p class="text-sm leading-relaxed">{{ product.description }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Metadata</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h4 class="font-medium text-sm text-muted-foreground">Slug</h4>
                                <p class="text-sm font-mono bg-muted px-2 py-1 rounded">{{ product.slug }}</p>
                            </div>
                            <div>
                                <h4 class="font-medium text-sm text-muted-foreground">Created</h4>
                                <p class="text-sm">{{ formatDistanceToNow(new Date(product.created_at), { addSuffix: true }) }}</p>
                            </div>
                            <div>
                                <h4 class="font-medium text-sm text-muted-foreground">Last Updated</h4>
                                <p class="text-sm">{{ formatDistanceToNow(new Date(product.updated_at), { addSuffix: true }) }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Editions -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Editions</CardTitle>
                    <div class="flex items-center gap-2">
                        <Button size="sm" variant="outline" as-child>
                            <Link :href="`/admin/products/${product.id}/editions`">
                                Manage Editions
                            </Link>
                        </Button>
                        <Button size="sm" as-child>
                            <Link :href="`/admin/products/${product.id}/editions/create`">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Edition
                            </Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="product.editions.length === 0" class="text-center py-8">
                        <p class="text-muted-foreground mb-4">No editions created yet.</p>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Create your first edition
                        </Button>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Format</TableHead>
                                    <TableHead>Price</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>SKU</TableHead>
                                    <TableHead class="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="edition in product.editions" :key="edition.id">
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ edition.name }}</div>
                                            <div v-if="edition.description" class="text-sm text-muted-foreground truncate max-w-[150px]">
                                                {{ edition.description }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ getFormatLabel(edition.format) }}</TableCell>
                                    <TableCell>{{ formatPrice(edition.price) }}</TableCell>
                                    <TableCell>
                                        <div>
                                            <span>{{ edition.stock_quantity }}</span>
                                            <span v-if="edition.limited_quantity" class="text-muted-foreground">
                                                / {{ edition.limited_quantity }}
                                            </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadgeVariant(edition.status)">
                                            {{ getStatusLabel(edition.status) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="edition.sku" class="text-sm font-mono">{{ edition.sku }}</span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="sm">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button variant="ghost" size="sm">
                                                <SquarePen class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="deleteEdition(edition)"
                                                class="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
