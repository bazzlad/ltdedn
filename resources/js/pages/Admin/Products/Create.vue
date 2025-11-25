<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ArrowLeft, Upload, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Artist {
    id: number;
    name: string;
}

const props = defineProps<{
    artists: Artist[];
}>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Create', href: '/admin/products/create' },
];

const form = useForm({
    artist_id: '',
    name: '',
    slug: '',
    description: '',
    cover_image: null as File | null,
    sell_through_ltdedn: false,
    is_limited: true,
    edition_size: undefined as number | undefined,
    base_price: undefined as number | undefined,
    is_public: false,
});

const imagePreview = ref<string | null>(null);

const handleImageUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        form.cover_image = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
};

const clearImage = () => {
    form.cover_image = null;
    imagePreview.value = null;
};

const submit = () => {
    form.post('/admin/products');
};

onMounted(() => {
    if (props.artists.length === 1) {
        form.artist_id = props.artists[0].id.toString();
    }
});

</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" as-child>
                    <a href="/admin/products">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Products
                    </a>
                </Button>
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Create Product</h2>
                    <p class="text-muted-foreground">Add a new music product or release</p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Product Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Artist Selection -->
                            <div class="space-y-2">
                                <Label for="artist_id">Artist *</Label>
                                <select
                                    id="artist_id"
                                    v-model="form.artist_id"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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
                                <Input id="name" v-model="form.name" type="text" placeholder="Enter product name" required />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Slug -->
                            <div class="space-y-2">
                                <Label for="slug">Slug</Label>
                                <Input id="slug" v-model="form.slug" type="text" placeholder="auto-generated-from-title" />
                                <div v-if="form.errors.slug" class="text-sm text-red-600">
                                    {{ form.errors.slug }}
                                </div>
                                <p class="text-xs text-muted-foreground">Leave empty to auto-generate from name</p>
                            </div>

                            <!-- Cover Image Upload -->
                            <div class="space-y-2">
                                <Label for="cover_image">Cover Image</Label>
                                <div v-if="imagePreview" class="relative mb-4 inline-block">
                                    <img :src="imagePreview" alt="Cover preview" class="h-48 w-auto rounded-lg border object-cover" />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        class="absolute right-2 top-2"
                                        @click="clearImage"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div v-else class="flex items-center justify-center rounded-lg border-2 border-dashed border-muted-foreground/25 p-12">
                                    <div class="text-center">
                                        <Upload class="mx-auto h-12 w-12 text-muted-foreground" />
                                        <div class="mt-4 flex text-sm leading-6 text-muted-foreground">
                                            <label
                                                for="cover_image"
                                                class="relative cursor-pointer rounded-md font-semibold text-primary hover:text-primary/80"
                                            >
                                                <span>Upload a file</span>
                                                <input
                                                    id="cover_image"
                                                    type="file"
                                                    accept="image/*"
                                                    class="sr-only"
                                                    @change="handleImageUpload"
                                                />
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs leading-5 text-muted-foreground">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                                <div v-if="form.errors.cover_image" class="text-sm text-red-600">
                                    {{ form.errors.cover_image }}
                                </div>
                            </div>

                            <!-- Base Price -->
                            <div class="space-y-2">
                                <Label for="base_price">Base Price</Label>
                                <Input id="base_price" v-model="form.base_price" type="number" step="0.01" min="0" placeholder="0.00" />
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
                                <p class="text-xs text-muted-foreground">Only applies to limited editions</p>
                            </div>

                            <!-- Edition Options -->
                            <div class="space-y-4 md:col-span-2">
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="is_limited"
                                        v-model="form.is_limited"
                                        type="checkbox"
                                        class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                    />
                                    <Label for="is_limited">Limited Edition</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="sell_through_ltdedn"
                                        v-model="form.sell_through_ltdedn"
                                        type="checkbox"
                                        class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                    />
                                    <Label for="sell_through_ltdedn">Sell through LTDEDN</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="is_public"
                                        v-model="form.is_public"
                                        type="checkbox"
                                        class="focus:ring-opacity-50 rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
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
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                ></textarea>
                                <div v-if="form.errors.description" class="text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Creating...' : 'Create Product' }}
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <a href="/admin/products">Cancel</a>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
