<script setup lang="ts">
	import { Badge } from '@/components/ui/badge';
	import { Button } from '@/components/ui/button';
	import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

	import AdminLayout from '@/layouts/AdminLayout.vue';
	import type { BreadcrumbItemType } from '@/types';
	import { Link, usePage } from '@inertiajs/vue3';

	import { ArrowLeft, Plus } from 'lucide-vue-next';
	import { formatDistanceToNow } from 'date-fns';
	import { computed } from 'vue';
	import { index as editionsIndex, create as editionsCreate } from '@/routes/admin/products/editions';
	import { index as productsIndex, edit as productsEdit } from '@/routes/admin/products';

	interface Artist { id: number; name: string; slug: string; }
	interface Edition {
		id: number; product_id: number; name: string; format: string; price: string | number;
		stock_quantity: number; limited_quantity?: number; description?: string; sku?: string;
		status: string; created_at: string; updated_at: string;
	}
	interface Product {
		id: number; artist_id: number; title: string; slug: string; description?: string;
		price?: string | number; status: string; type?: string; release_date?: string;
		created_at: string; updated_at: string; artist: Artist;
	}

	interface EditionsData {
		data: Edition[];
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
	}

	const props = defineProps<{ 
		product: Product; 
		editions: EditionsData;
		editionStats: Record<string, number>;
		totalEditions: number;
	}>();

	const page = usePage();
	const user = computed(() => page.props.auth.user);
	const isAdmin = computed(() => user.value?.role === 'admin');

	const breadcrumbs: BreadcrumbItemType[] = [
		{ title: 'Admin', href: '/admin' },
		{ title: 'Products', href: productsIndex().url },
		{ title: 'View Product', href: '#' },
	];

	const getStatusBadgeVariant = (status: string) => {
		switch (status) {
			case 'published':
			case 'available': return 'default';
			case 'draft':
			case 'sold_out': return 'secondary';
			case 'archived':
			case 'discontinued': return 'outline';
			default: return 'secondary';
		}
	};
	const getStatusLabel = (status: string) => {
		switch (status) {
			case 'published': return 'Published';
			case 'draft': return 'Draft';
			case 'archived': return 'Archived';
			case 'available': return 'Available';
			case 'sold_out': return 'Sold Out';
			case 'discontinued': return 'Discontinued';
			default: return status;
		}
	};
	const getTypeLabel = (type?: string) => {
		if (!type) return '-';
		switch (type) {
			case 'album': return 'Album';
			case 'single': return 'Single';
			case 'ep': return 'EP';
			case 'merchandise': return 'Merchandise';
			case 'other': return 'Other';
			default: return type;
		}
	};
	const formatPrice = (price?: string | number) => {
		if (price == null || price === '') return '-';
		const n = typeof price === 'number' ? price : parseFloat(price);
		return isNaN(n) ? '-' : `$${n.toFixed(2)}`;
	};


	// Compute edition summary statistics
	const editionsSummary = computed(() => {
		const total = props.totalEditions || 0;
		
		if (total === 0) {
			return {
				total: 0,
				displayText: 'No editions created'
			};
		}

		// Get counts from the actual stats (all editions, not just paginated)
		const stats = props.editionStats || {};
		const redeemed = stats.redeemed || 0;
		const sold = stats.sold || 0;

		// Build simple display text: "201 Editions, 5 claimed, 3 sold"
		const parts = [`${total} Edition${total === 1 ? '' : 's'}`];
		
		if (redeemed > 0) {
			parts.push(`${redeemed} claimed`);
		}
		
		if (sold > 0) {
			parts.push(`${sold} sold`);
		}

		return {
			total,
			displayText: parts.join(', ')
		};
	});




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
                    <h2 class="text-3xl font-bold tracking-tight">{{ product.title }}</h2>
                    <p class="text-muted-foreground">Product details and edition management</p>
                </div>
                <Button as-child>
                    <Link :href="productsEdit(product).url">
                        <SquarePen class="mr-2 h-4 w-4" />
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
                                    <h4 class="text-sm font-medium text-muted-foreground">Title</h4>
                                    <p class="font-medium">{{ product.title }}</p>
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
                                    <h4 class="text-sm font-medium text-muted-foreground">Type</h4>
                                    <p>{{ getTypeLabel(product.type) }}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Status</h4>
                                    <Badge :variant="getStatusBadgeVariant(product.status)">
                                        {{ getStatusLabel(product.status) }}
                                    </Badge>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Price</h4>
                                    <p>{{ formatPrice(product.price) }}</p>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-muted-foreground">Release Date</h4>
                                    <p v-if="product.release_date">
                                        {{ new Date(product.release_date).toLocaleDateString() }}
                                    </p>
                                    <p v-else class="text-muted-foreground">Not set</p>
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

            <!-- Editions -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Editions</CardTitle>
                    <!--
                    <div class="flex items-center gap-2">
                        <Button size="sm" variant="outline" as-child>
                            <Link :href="editionsIndex(product).url"> Manage Editions </Link>
                        </Button>
                        <Button size="sm" as-child>
                            <Link :href="editionsCreate(product).url">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Edition
                            </Link>
                        </Button>
                    </div>
                    -->
                </CardHeader>
                <CardContent>
                    <div v-if="editionsSummary.total === 0" class="py-8 text-center">
                        <p class="mb-4 text-muted-foreground">{{ editionsSummary.displayText }}</p>
                        <Button as-child>
                            <Link :href="`/admin/products/${product.id}/editions/create`">
                                <Plus class="mr-2 h-4 w-4" />
                                Create your first edition
                            </Link>
                        </Button>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Simple Edition Summary -->
                        <div>
                            <p class="mb-8">{{ editionsSummary.displayText }}</p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <Button variant="outline" as-child>
                                <Link :href="editionsIndex(product).url">
                                    Manage Editions
                                </Link>
                            </Button>
                            <Button as-child>
                                <Link :href="editionsCreate(product).url">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Edition
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
