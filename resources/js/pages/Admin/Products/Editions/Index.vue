<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { generateAndDownloadQR } from '@/composables/useQRCode';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { qrBatchPdf } from '@/routes/admin/products/editions';
import type { BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { ArrowLeft, Download, Hash, Plus, QrCodeIcon, Share2, SquarePen, Trash2, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    product: Product;
    editions: EditionsData;
}>();

const totalCount = computed(() => props.editions.total || props.editions.data.length);

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

const qrModalOpen = ref(false);
const selectedEdition = ref<Edition | null>(null);

const selectedEditionQrUrl = computed(() => {
    if (!selectedEdition.value) return '';
    return `${window.location.origin}/qr/${selectedEdition.value.qr_code}`;
});

const openQrModal = (edition: Edition) => {
    selectedEdition.value = edition;
    qrModalOpen.value = true;
};

const closeQrModal = () => {
    qrModalOpen.value = false;
    selectedEdition.value = null;
};

const downloadQrCode = async () => {
    if (!selectedEdition.value) return;

    try {
        const filename = `qr_${selectedEdition.value.id}_${selectedEdition.value.number}_qrcode.png`;
        await generateAndDownloadQR(selectedEdition.value.qr_code, filename);
    } catch (error) {
        console.error('Failed to download QR code:', error);
    }
};

const isDownloadingBatchPDF = ref(false);

const downloadBatchPdf = async () => {
    try {
        isDownloadingBatchPDF.value = true;

        const link = document.createElement('a');
        // Include current pagination parameters
        const currentPage = props.editions?.current_page || 1;
        const perPage = props.editions?.per_page || 20;
        const url = new URL(qrBatchPdf(props.product).url, window.location.origin);
        url.searchParams.set('page', currentPage.toString());
        url.searchParams.set('per_page', perPage.toString());

        link.href = url.toString();
        link.download = ''; // Forces download instead of navigation
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

const batchPdfButtonText = computed(() => {
    if (isDownloadingBatchPDF.value) {
        return 'Generating...';
    }

    const total = props.editions?.total || 0;
    const currentPageCount = props.editions?.data?.length || 0;
    const currentPage = props.editions?.current_page || 1;

    if (total > currentPageCount && (props.editions?.last_page || 1) > 1) {
        return `Download QR Codes (Page ${currentPage})`;
    }

    return 'Download QR Codes';
});

const changePerPage = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('per_page', target.value);
    currentUrl.searchParams.delete('page'); // Reset to page 1 when changing per_page

    // Use router.visit with preserveState to maintain scroll position
    router.visit(currentUrl.toString(), {
        preserveState: false,
        preserveScroll: false,
    });
};

const formatLinkLabel = (label: string): string => {
    return label.replace(/&amp;laquo;|&laquo;|«/g, '‹').replace(/&amp;raquo;|&raquo;|»/g, '›');
};

const isNavDisabled = (link: { url?: string; label: string; active: boolean }): boolean => {
    const label = link.label.toLowerCase();

    if (label.includes('previous') || label.includes('‹')) {
        return !link.url || props.editions.current_page <= 1;
    }

    if (label.includes('next') || label.includes('›')) {
        return !link.url || props.editions.current_page >= props.editions.last_page;
    }

    return !link.url;
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
                    <div v-if="editions?.data?.length > 0">
                        <Button size="sm" variant="outline" @click="downloadBatchPdf" :disabled="isDownloadingBatchPDF">
                            <div
                                v-if="isDownloadingBatchPDF"
                                class="mr-2 h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"
                            ></div>
                            <QrCodeIcon v-else class="mr-2 h-3 w-3" />
                            {{ batchPdfButtonText }}
                        </Button>
                    </div>
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
                            <CardDescription>
                                {{ totalCount || 0 }} total editions
                                <span v-if="editions.last_page > 1" class="ml-2 text-xs">
                                    ({{ editions.current_page || 1 }}/{{ editions.last_page || 1 }} pages)
                                </span>
                            </CardDescription>
                        </div>
                        <div v-if="totalCount > 0" class="flex items-center space-x-2">
                            <span class="text-sm text-muted-foreground">Show:</span>
                            <select class="rounded border px-2 py-1 text-sm" :value="editions.per_page || 20" @change="changePerPage">
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <!--<option value="100">100</option>
                                <option value="200">200</option>-->
                            </select>
                            <span class="text-sm text-muted-foreground">per page</span>
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
                                        <TableHead class="text-center">Actions</TableHead>
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
                                                    <Link
                                                        :href="`/admin/products/${product.id}/editions/${edition.id}/edit`"
                                                        :title="`Edit Edition #${edition.number}`"
                                                    >
                                                        <SquarePen class="h-3 w-3" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    title="View QR Code"
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="openQrModal(edition)"
                                                    class="text-blue-600 hover:text-blue-700"
                                                >
                                                    <QrCodeIcon class="h-3 w-3" />
                                                </Button>
                                                <Button
                                                    title="Delete Edition"
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
                            <div v-if="editions.links && editions.last_page > 1" class="-mx-6 mt-6 border-t bg-gray-50 px-6 py-4 dark:bg-gray-800/50">
                                <!-- Always show results info -->
                                <div class="mb-4 flex items-center justify-between">
                                    <div class="text-sm text-muted-foreground">
                                        Showing {{ editions.from || 1 }} to {{ editions.to || editions.data.length }} of
                                        {{ editions.total || editions.data.length }} results
                                    </div>
                                    <div v-if="editions.last_page > 1" class="text-sm font-medium">
                                        Page {{ editions.current_page || 1 }} of {{ editions.last_page || 1 }}
                                    </div>
                                </div>

                                <!-- Page Navigation - Always show if multiple pages -->
                                <div v-if="editions.last_page > 1" class="flex items-center justify-center">
                                    <nav class="flex items-center space-x-1" aria-label="Pagination">
                                        <Button
                                            v-for="link in editions.links"
                                            :key="link.label"
                                            :variant="link.active ? 'default' : 'outline'"
                                            size="sm"
                                            :disabled="isNavDisabled(link)"
                                            class="min-w-[2.5rem]"
                                        >
                                            <Link v-if="!isNavDisabled(link)" :href="link.url" class="flex h-full w-full items-center justify-center">
                                                {{ formatLinkLabel(link.label) }}
                                            </Link>
                                            <span v-else class="flex h-full w-full items-center justify-center">
                                                {{ formatLinkLabel(link.label) }}
                                            </span>
                                        </Button>
                                    </nav>
                                </div>

                                <!-- No pagination message -->
                                <div v-else class="text-center text-sm text-muted-foreground">
                                    All {{ editions.total || editions.data.length }} editions shown on this page
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- QR Code Modal -->
        <Dialog :open="qrModalOpen" @update:open="closeQrModal">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>QR Code - Edition #{{ selectedEdition?.number }}</DialogTitle>
                    <DialogDescription>
                        {{ product.name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col items-center gap-6 py-4">
                    <!-- QR Code Display -->
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-neutral-200">
                        <div v-if="selectedEditionQrUrl" class="qr-code-container">
                            <img
                                :src="`https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(selectedEditionQrUrl)}`"
                                alt="QR Code"
                                class="h-64 w-64"
                            />
                        </div>
                    </div>

                    <!-- QR Code Info -->
                    <div class="w-full space-y-2 text-center">
                        <div class="text-sm font-medium text-muted-foreground">
                            Edition #{{ selectedEdition?.number }}
                        </div>
                        <div class="break-all px-4 font-mono text-xs text-muted-foreground">
                            {{ selectedEditionQrUrl }}
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-col sm:space-x-0">
                    <Button @click="downloadQrCode" class="w-full" variant="default">
                        <Download class="mr-2 h-4 w-4" />
                        Download QR Code
                    </Button>
                    <Button variant="outline" class="w-full" disabled>
                        <Share2 class="mr-2 h-4 w-4" />
                        Share (Coming Soon)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
