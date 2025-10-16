<script setup lang="ts">
	import { Badge } from '@/components/ui/badge';
	import { Button } from '@/components/ui/button';
	import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
	import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
	import AdminLayout from '@/layouts/AdminLayout.vue';
	import type { BreadcrumbItemType } from '@/types';
	import { Link, usePage, router } from '@inertiajs/vue3';
	import { formatDistanceToNow } from 'date-fns';
	import { ArrowLeft, Eye, Plus, SquarePen, Trash2, QrCodeIcon } from 'lucide-vue-next';
	import { computed, ref } from 'vue';
	import { generateAndDownloadQR } from '@/composables/useQRCode';
	import { qrBatchPdf } from '@/routes/admin/products/editions';
	import { index as editionsIndex, create as editionsCreate, edit as editionsEdit, destroy as editionsDestroy } from '@/routes/admin/products/editions';
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
		created_at: string; updated_at: string; artist: Artist; editions: Edition[];
	}

	const props = defineProps<{ product: Product }>();

	const page = usePage();
	const user = computed(() => page.props.auth.user);
	const isAdmin = computed(() => user.value?.role === 'admin');

	const isDownloadingQR = ref(false);
	const isDownloadingBatchPDF = ref(false);

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
	const getFormatLabel = (format?: string) => {
		if (!format) return '-';
		switch (format) {
			case 'vinyl': return 'Vinyl';
			case 'cd': return 'CD';
			case 'digital': return 'Digital';
			case 'cassette': return 'Cassette';
			default: return format;
		}
	};

	// normalize editions from the prop (not page.props)
	const editionsDisplay = computed(() => {
		const p = props.product;
		const list = p.editions || [];
		return list.map((e: any) => {
			const price = e.price != null && e.price !== '' ? e.price : p.price;
			return {
				id: e.id,
				name: e.name || (e.number != null ? `#${e.number}` : ''),
				format: e.format || (e.qr_code ? 'Unique' : ''),
				price,
				stock_quantity: e.stock_quantity != null ? e.stock_quantity : 1,
				limited_quantity: e.limited_quantity != null ? e.limited_quantity : (p as any).edition_size || null,
				description: e.description || '',
				sku: e.sku || e.qr_short_code || '',
				status: e.status || 'available',
				created_at: e.created_at,
                updated_at: e.updated_at,
                qr_code: e.qr_code || '',
                number: e.number || 0,
			};
		});
	});


	const destroyUrl = (e: { id: number }) => editionsDestroy({ product: props.product.id, edition: e.id }).url;
    const downloadQrCode = async (edition: { qr_code: string, id: number, number: number }) => {
        try {
            isDownloadingQR.value = true;
            const filename = `qr_${edition.id}_${edition.number}_qrcode.png`;
            await generateAndDownloadQR(edition.qr_code, filename);
        } catch (error) {
            console.error('Failed to download QR code:', error);
        } finally {
            isDownloadingQR.value = false;
        }
    };

    const downloadBatchPdf = async () => {
        try {
            isDownloadingBatchPDF.value = true;

            const link = document.createElement('a');
            link.href = qrBatchPdf(props.product).url;
            link.download = ''; /
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            setTimeout(() => {
                isDownloadingBatchPDF.value = false;
            }, 1000);
        } catch (error) {
            console.error('Failed to download batch PDF:', error);
            isDownloadingBatchPDF.value = false;
        }
    };

	const editUrl = (e: { id: number }) => editionsEdit({ product: props.product.id, edition: e.id }).url;
	const destroy = (e: { id: number; name?: string }) => {
		const label = e.name ? `"${e.name}"` : `Edition #${e.id}`;
		if (confirm(`Delete ${label}? This cannot be undone.`)) {
			router.delete(destroyUrl(e));
		}
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
                    <div class="flex items-center gap-2">
                        <div v-if="product.editions.length > 0">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="downloadBatchPdf"
                                :disabled="isDownloadingBatchPDF"
                            >
                                <div v-if="isDownloadingBatchPDF" class="mr-2 h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                <QrCodeIcon v-else class="mr-2 h-3 w-3" />
                                {{ isDownloadingBatchPDF ? 'Generating...' : 'Download QR Codes' }}
                            </Button>
                        </div>
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
                </CardHeader>
                <CardContent>
                    <div v-if="product.editions.length === 0" class="py-8 text-center">
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
                                    <TableHead>Name</TableHead>
                                    <TableHead>Format</TableHead>
                                    <TableHead>Price</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>SKU</TableHead>
                                    <TableHead class="w-[100px] text-center">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="edition in editionsDisplay" :key="edition.id">
                                    <TableCell>
                                        <div>
                                            <div class="font-medium">{{ edition.name }}</div>
                                            <div v-if="edition.description" class="max-w-[150px] truncate text-sm text-muted-foreground">
                                                {{ edition.description }}
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>{{ getFormatLabel(edition.format) }}</TableCell>
                                    <TableCell>{{ formatPrice(edition.price) }}</TableCell>
                                    <TableCell>
                                        <div>
                                            <span>{{ edition.stock_quantity }}</span>
                                            <span v-if="edition.limited_quantity" class="text-muted-foreground"> / {{ edition.limited_quantity }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadgeVariant(edition.status)">{{ getStatusLabel(edition.status) }}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="edition.sku" class="font-mono text-sm">{{ edition.sku }}</span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" as-child>
                                                <Link :href="editUrl(edition)"><SquarePen class="h-4 w-4" /></Link>
                                            </Button>
                                            <Button
                                                title="Download QR Code"
                                                size="sm"
                                                variant="ghost"
                                                @click="downloadQrCode(edition)"
                                                :disabled="isDownloadingQR"
                                                class="text-white-600"
                                            >
                                                <div v-if="isDownloadingQR" class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
                                                <QrCodeIcon v-else class="h-3 w-3" />
                                            </Button>
                                            <Button variant="ghost" size="sm" class="text-red-600 hover:text-red-700" @click="destroy(edition)">
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
