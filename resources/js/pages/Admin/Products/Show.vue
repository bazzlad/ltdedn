<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

import { edit as productsEdit, index as productsIndex } from '@/routes/admin/products';
import { index as editionsIndex } from '@/routes/admin/products/editions';
import { formatDistanceToNow } from 'date-fns';
import { ArrowLeft, Edit, Package } from 'lucide-vue-next';
import { computed } from 'vue';

interface Artist {
    id: number;
    name: string;
    slug: string;
}
interface Product {
    id: number;
    artist_id: number;
    name: string;
    slug: string;
    description?: string;
    base_price?: string | number;
    sell_through_ltdedn: boolean;
    is_limited: boolean;
    edition_size?: number;
    is_public: boolean;
    cover_image?: string | null;
    editions_count?: number;
    created_at: string;
    updated_at: string;
    artist: Artist;
}

defineProps<{
    product: Product;
    editionStats: Record<string, number>;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: productsIndex().url },
    { title: 'View Product', href: '#' },
];

const formatPrice = (price?: string | number) => {
    if (price == null || price === '') return '-';
    const n = typeof price === 'number' ? price : parseFloat(price);
    return isNaN(n) ? '-' : `$${n.toFixed(2)}`;
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="productsIndex().url">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Products
                    </Link>
                </Button>
                <div class="flex-1">
                    <h2 class="text-3xl font-bold tracking-tight">{{ product.name }}</h2>
                    <p class="text-muted-foreground">Product details and edition management</p>
                </div>
                <Button as-child>
                    <Link :href="productsEdit(product).url">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Product
                    </Link>
                </Button>
            </div>

            <!-- Product Details -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Name</h4>
                                    <p class="font-medium">{{ product.name }}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Artist</h4>
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
                                    <h4 class="text-sm font-medium text-muted-foreground">Base Price</h4>
                                    <p>{{ formatPrice(product.base_price) }}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Edition Size</h4>
                                    <p v-if="product.edition_size">{{ product.edition_size }}</p>
                                    <p v-else class="text-muted-foreground">Open Edition</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Limited Edition</h4>
                                    <Badge :variant="product.is_limited ? 'default' : 'secondary'">
                                        {{ product.is_limited ? 'Yes' : 'No' }}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Public</h4>
                                    <Badge :variant="product.is_public ? 'default' : 'secondary'">
                                        {{ product.is_public ? 'Yes' : 'No' }}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Sell Through LTD/EDN</h4>
                                    <Badge :variant="product.sell_through_ltdedn ? 'default' : 'secondary'">
                                        {{ product.sell_through_ltdedn ? 'Yes' : 'No' }}
                                    </Badge>
                                </div>
                                <div v-if="product.cover_image">
                                    <h4 class="text-sm font-medium text-muted-foreground">Cover Image</h4>
                                    <a :href="product.cover_image" target="_blank" class="text-sm text-blue-600 hover:underline">
                                        View Image
                                    </a>
                                </div>
                            </div>
                            <div v-if="product.description">
                                <h4 class="mb-2 text-sm font-medium text-muted-foreground">Description</h4>
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
                                <h4 class="text-sm font-medium text-muted-foreground">Slug</h4>
                                <p class="rounded bg-muted px-2 py-1 font-mono text-sm">{{ product.slug }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-muted-foreground">Created</h4>
                                <p class="text-sm">{{ formatDistanceToNow(new Date(product.created_at), { addSuffix: true }) }}</p>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-muted-foreground">Last Updated</h4>
                                <p class="text-sm">{{ formatDistanceToNow(new Date(product.updated_at), { addSuffix: true }) }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Editions Overview -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Editions</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ product.editions_count }} total edition{{ product.editions_count === 1 ? '' : 's' }}
                            </p>
                        </div>
                        <Button as-child>
                            <Link :href="editionsIndex(product).url">
                                <Package class="mr-2 h-4 w-4" />
                                Manage Editions
                            </Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="product.editions_count === 0" class="py-8 text-center">
                        <p class="mb-4 text-muted-foreground">No editions created yet.</p>
                        <Button as-child>
                            <Link :href="editionsIndex(product).url">
                                <Package class="mr-2 h-4 w-4" />
                                Create editions
                            </Link>
                        </Button>
                    </div>

                    <div v-else>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="rounded-lg border bg-card p-6 text-card-foreground">
                                <p class="text-sm text-muted-foreground">Available</p>
                                <p class="text-3xl font-bold">{{ editionStats.available || 0 }}</p>
                            </div>
                            <div class="rounded-lg border bg-card p-6 text-card-foreground">
                                <p class="text-sm text-muted-foreground">Claimed</p>
                                <p class="text-3xl font-bold">{{ editionStats.claimed || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
