<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import type { BreadcrumbItemType } from '@/types';

interface Artist {
    id: number;
    name: string;
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
    base_price?: number;
    is_public: boolean;
    artist: Artist;
}

const props = defineProps<{
    product: Product;
    artists: Artist[];
}>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Edit', href: '#' },
];

const form = useForm({
    artist_id: props.product.artist_id.toString(),
    name: props.product.name,
    slug: props.product.slug,
    description: props.product.description || '',
    cover_image_url: props.product.cover_image_url || '',
    sell_through_ltdedn: props.product.sell_through_ltdedn,
    is_limited: props.product.is_limited,
    edition_size: props.product.edition_size || null,
    base_price: props.product.base_price || null,
    is_public: props.product.is_public,
});

const submit = () => {
    form.put(`/admin/products/${props.product.id}`);
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" as-child>
                    <a :href="`/admin/products/${product.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Product
                    </a>
                </Button>
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Edit Product</h2>
                    <p class="text-muted-foreground">
                        Update product information
                    </p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Product Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Artist Selection -->
                            <div class="space-y-2">
                                <Label for="artist_id">Artist *</Label>
                                <select
                                    id="artist_id"
                                    v-model="form.artist_id"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select an artist</option>
                                    <option v-for="artist in artists" :key="artist.id" :value="artist.id">
                                        {{ artist.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.artist_id" class="text-sm text-red-600">
                                    {{ form.errors.artist_id }}
                                </div>
                            </div>

                            <!-- Name -->
                            <div class="space-y-2">
                                <Label for="name">Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Enter product name"
                                    required
                                />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Slug -->
                            <div class="space-y-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    type="text"
                                    placeholder="auto-generated-from-title"
                                />
                                <div v-if="form.errors.slug" class="text-sm text-red-600">
                                    {{ form.errors.slug }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Leave empty to auto-generate from name
                                </p>
                            </div>

                            <!-- Cover Image URL -->
                            <div class="space-y-2">
                                <Label for="cover_image_url">Cover Image URL</Label>
                                <Input
                                    id="cover_image_url"
                                    v-model="form.cover_image_url"
                                    type="url"
                                    placeholder="https://example.com/image.jpg"
                                />
                                <div v-if="form.errors.cover_image_url" class="text-sm text-red-600">
                                    {{ form.errors.cover_image_url }}
                                </div>
                            </div>

                            <!-- Base Price -->
                            <div class="space-y-2">
                                <Label for="base_price">Base Price</Label>
                                <Input
                                    id="base_price"
                                    v-model="form.base_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                />
                                <div v-if="form.errors.base_price" class="text-sm text-red-600">
                                    {{ form.errors.base_price }}
                                </div>
                            </div>

                            <!-- Edition Size -->
                            <div class="space-y-2">
                                <Label for="edition_size">Edition Size</Label>
                                <Input
                                    id="edition_size"
                                    v-model="form.edition_size"
                                    type="number"
                                    min="1"
                                    placeholder="Leave empty for unlimited"
                                    :disabled="!form.is_limited"
                                />
                                <div v-if="form.errors.edition_size" class="text-sm text-red-600">
                                    {{ form.errors.edition_size }}
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Only applies to limited editions
                                </p>
                            </div>

                            <!-- Edition Options -->
                            <div class="space-y-4 md:col-span-2">
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="is_limited"
                                        v-model="form.is_limited"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    />
                                    <Label for="is_limited">Limited Edition</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="sell_through_ltdedn"
                                        v-model="form.sell_through_ltdedn"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    />
                                    <Label for="sell_through_ltdedn">Sell through LTDEDN</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="is_public"
                                        v-model="form.is_public"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    />
                                    <Label for="is_public">Public (SEO)</Label>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="space-y-2 md:col-span-2">
                                <Label for="description">Description</Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    placeholder="Enter product description..."
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                ></textarea>
                                <div v-if="form.errors.description" class="text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Updating...' : 'Update Product' }}
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <a :href="`/admin/products/${product.id}`">Cancel</a>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
