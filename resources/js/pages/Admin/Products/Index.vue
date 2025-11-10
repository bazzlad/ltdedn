<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { Eye, Package, Plus, Search, SquarePen, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Artist {
    id: number;
    name: string;
    slug: string;
}

interface Edition {
    status: string;
}

interface Product {
    id: number;
    artist_id: number;
    name: string;
    slug: string;
    description?: string;
    cover_image_url?: string;
    sell_through_ltdedn: boolean;
    is_limited: boolean;
    edition_size?: number;
    base_price?: string;
    is_public: boolean;
    created_at: string;
    updated_at: string;
    artist: Artist;
    editions: Edition[];
    editions_count: number;
}

interface ProductsData {
    data: Product[];
    links: Array<{
        url?: string;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
    first_page_url?: string;
    last_page_url?: string;
    next_page_url?: string;
    prev_page_url?: string;
    path?: string;
}

const props = defineProps<{
    products: ProductsData;
    filters: {
        search?: string;
    };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

const searchTerm = ref(props.filters.search || '');
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const search = () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        router.get(
            '/admin/products',
            {
                search: searchTerm.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }, 300);
};

// Watch for changes in searchTerm and trigger search
watch(searchTerm, search);

const clearSearch = () => {
    searchTerm.value = '';
};

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
];

const getPublicBadgeVariant = (isPublic: boolean) => {
    return isPublic ? 'default' : 'secondary';
};

const getPublicLabel = (isPublic: boolean) => {
    return isPublic ? 'Public' : 'Private';
};

const getEditionsSummary = (product: Product) => {
    const totalCount = product.editions_count;

    if (totalCount === 0) {
        return 'No editions';
    }

    if (!product.editions || product.editions.length === 0) {
        return `${totalCount} edition${totalCount === 1 ? '' : 's'}`;
    }

    // Count by status
    const statusCounts = product.editions.reduce(
        (acc, edition) => {
            acc[edition.status] = (acc[edition.status] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    const available = statusCounts.available || 0;
    const redeemed = statusCounts.redeemed || 0;
    const sold = statusCounts.sold || 0;
    const otherCount = totalCount - available - redeemed - sold;

    // Build summary based on what exists
    const parts: string[] = [];

    if (available > 0) parts.push(`${available} available`);
    if (redeemed > 0) parts.push(`${redeemed} redeemed`);
    if (sold > 0) parts.push(`${sold} sold`);
    if (otherCount > 0) parts.push(`${otherCount} other`);

    if (parts.length === 0) {
        return `${totalCount} edition${totalCount === 1 ? '' : 's'}`;
    }

    // Show limited info for brevity in table
    if (parts.length <= 2) {
        return parts.join(', ');
    } else {
        return `${parts[0]}, +${parts.length - 1} more`;
    }
};

const formatPrice = (price?: string) => {
    if (!price) return '-';
    return `$${parseFloat(price).toFixed(2)}`;
};

const deleteProduct = (productId: number) => {
    if (confirm('Are you sure you want to delete this product?')) {
        router.delete(`/admin/products/${productId}`);
    }
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Products</h2>
                    <p class="text-muted-foreground">Manage your music products and releases</p>
                </div>
                <div class="flex items-center space-x-2">
                    <Button as-child>
                        <Link href="/admin/products/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Product
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Products</CardTitle>
                            <CardDescription> {{ products.total || 0 }} total products </CardDescription>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="relative">
                                <Search class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                <Input v-model="searchTerm" placeholder="Search products..." class="w-64 pr-8 pl-8" />
                                <button
                                    v-if="searchTerm"
                                    @click="clearSearch"
                                    class="absolute top-2.5 right-2 h-4 w-4 text-muted-foreground hover:text-foreground"
                                    title="Clear search"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-if="products.data.length === 0" class="py-8 text-center">
                            <p class="text-muted-foreground">No products found.</p>
                            <Button as-child class="mt-4">
                                <Link href="/admin/products/create">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create your first product
                                </Link>
                            </Button>
                        </div>

                        <div v-else>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Artist</TableHead>
                                        <TableHead>Editions</TableHead>
                                        <TableHead>Visibility</TableHead>
                                        <TableHead>Price</TableHead>
                                        <TableHead>Sales</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead class="text-center">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="product in products.data" :key="product.id">
                                        <TableCell class="font-medium">
                                            <Link :href="`/admin/products/${product.id}`">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                                        <Package class="h-4 w-4" />
                                                    </div>
                                                    <div>
                                                        <div class="font-medium">{{ product.name }}</div>
                                                        <div v-if="product.description" class="max-w-[200px] truncate text-sm text-muted-foreground">
                                                            {{ product.description }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                v-if="isAdmin"
                                                :href="`/admin/artists/${product.artist.id}`"
                                                class="text-blue-600 hover:text-blue-800 hover:underline"
                                            >
                                                {{ product.artist.name }}
                                            </Link>
                                            <span v-else>{{ product.artist.name }}</span>
                                        </TableCell>
                                        <TableCell>{{ getEditionsSummary(product) }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="getPublicBadgeVariant(product.is_public)">
                                                {{ getPublicLabel(product.is_public) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{{ formatPrice(product.base_price) }}</TableCell>
                                        <TableCell>
                                            <span v-if="product.sell_through_ltdedn" class="text-green-600"> Through LTDEDN </span>
                                            <span v-else class="text-muted-foreground"> Self-managed </span>
                                        </TableCell>
                                        <TableCell class="text-sm text-muted-foreground">
                                            {{ formatDistanceToNow(new Date(product.created_at), { addSuffix: true }) }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <div class="flex items-center justify-end space-x-2">
                                                <Button as-child size="sm" variant="ghost" title="View Product">
                                                    <Link :href="`/admin/products/${product.id}`">
                                                        <Eye class="h-3 w-3" />
                                                    </Link>
                                                </Button>
                                                <Button as-child size="sm" variant="ghost" title="Manage Editions">
                                                    <Link :href="`/admin/products/${product.id}/editions`">
                                                        <Package class="h-3 w-3" />
                                                    </Link>
                                                </Button>
                                                <Button as-child size="sm" variant="ghost" title="Edit Product">
                                                    <Link :href="`/admin/products/${product.id}/edit`">
                                                        <SquarePen class="h-3 w-3" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="deleteProduct(product.id)"
                                                    class="text-red-600 hover:text-red-700"
                                                    title="Delete Product"
                                                >
                                                    <Trash2 class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            <!-- Pagination -->
                            <div v-if="products.last_page > 1" class="flex items-center justify-between space-x-2 py-4">
                                <div class="text-sm text-muted-foreground">
                                    Showing {{ products.from }} to {{ products.to }} of {{ products.total }} results
                                </div>
                                <div class="flex items-center space-x-2">
                                    <Button
                                        v-for="link in products.links"
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
