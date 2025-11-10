<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { downloadQRCode, getQRCodeUrl, useQRCode } from '@/composables/useQRCode';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface Artist {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    artist: Artist;
}

interface User {
    id: number;
    name: string;
    email?: string;
}

interface Edition {
    id: number;
    number: number;
    status: string;
    owner_id?: number;
    owner?: User;
    qr_code: string;
    qr_short_code?: string;
}

interface SelectOption {
    value: string;
    label: string;
}

const props = defineProps<{
    product: Product;
    edition: Edition;
    users: User[];
    statuses: SelectOption[];
}>();

const qrValue = computed(() => {
    const qrCode = props.edition.qr_code;
    return qrCode ? getQRCodeUrl(qrCode) : '';
});

const {
    qrDataUrl,
    isGenerating,
    error: qrError,
} = useQRCode(qrValue, {
    width: 1024,
    margin: 1,
    errorCorrectionLevel: 'M',
});

const handleDownloadQR = () => {
    if (qrDataUrl.value) {
        const filename = `product-${props.product.id}-edition-${props.edition.number}.png`;
        downloadQRCode(qrDataUrl.value, filename);
    }
};

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
    { title: props.product.name, href: `/admin/products/${props.product.id}` },
    { title: 'Editions', href: `/admin/products/${props.product.id}/editions` },
    { title: `Edit #${props.edition.number}`, href: '#' },
];

const form = useForm({
    number: props.edition.number,
    status: props.edition.status,
    owner_id: props.edition.owner_id?.toString() || '',
});

const submit = () => {
    form.put(`/admin/products/${props.product.id}/editions/${props.edition.id}`);
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" as-child>
                    <a :href="`/admin/products/${product.id}/editions`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Editions
                    </a>
                </Button>
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Edit Edition #{{ edition.number }}</h2>
                    <p class="text-muted-foreground">Update edition information for {{ product.name }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Edition Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submit" class="space-y-6">
                                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <!-- Edition Number -->
                                    <div class="space-y-2">
                                        <Label for="number">Edition Number *</Label>
                                        <Input id="number" v-model="form.number" type="number" min="1" placeholder="Edition number" required />
                                        <div v-if="form.errors.number" class="text-sm text-red-600">
                                            {{ form.errors.number }}
                                        </div>
                                    </div>

                                    <!-- Status -->
                                    <div class="space-y-2">
                                        <Label for="status">Status *</Label>
                                        <select
                                            id="status"
                                            v-model="form.status"
                                            required
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                                {{ status.label }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.status" class="text-sm text-red-600">
                                            {{ form.errors.status }}
                                        </div>
                                    </div>

                                    <!-- Owner (Admin only) -->
                                    <div v-if="isAdmin" class="space-y-2 md:col-span-2">
                                        <Label for="owner_id">Owner (Optional)</Label>
                                        <select
                                            id="owner_id"
                                            v-model="form.owner_id"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="">No owner assigned</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">
                                                {{ user.name }} {{ user.email ? `(${user.email})` : '' }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.owner_id" class="text-sm text-red-600">
                                            {{ form.errors.owner_id }}
                                        </div>
                                        <p class="text-xs text-muted-foreground">Assign this edition to a specific user</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <Button type="submit" :disabled="form.processing">
                                        {{ form.processing ? 'Updating...' : 'Update Edition' }}
                                    </Button>
                                    <Button type="button" variant="outline" as-child>
                                        <a :href="`/admin/products/${product.id}/editions`">Cancel</a>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle>QR Codes</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h4 class="mb-2 text-sm font-medium text-muted-foreground">Long QR Code</h4>
                                <p class="rounded bg-muted px-2 py-1 font-mono text-xs break-all">{{ edition.qr_code }}</p>
                            </div>
                            <div v-if="edition.qr_short_code">
                                <h4 class="mb-2 text-sm font-medium text-muted-foreground">Short Code</h4>
                                <p class="rounded bg-muted px-2 py-1 font-mono text-sm">{{ edition.qr_short_code }}</p>
                            </div>
                            <div v-else>
                                <p class="text-sm text-muted-foreground">No short code generated</p>
                            </div>

                            <!-- actual qr -->
                            <div class="space-y-3">
                                <h4 class="mb-2 text-sm font-medium text-muted-foreground">QR Image</h4>
                                <div
                                    v-if="isGenerating"
                                    class="flex h-64 w-64 items-center justify-center rounded border bg-slate-50 dark:bg-slate-800"
                                >
                                    <div class="text-center">
                                        <div class="mx-auto mb-2 h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
                                        <p class="text-sm text-slate-600 dark:text-slate-400">Generating QR code...</p>
                                    </div>
                                </div>

                                <div
                                    v-else-if="qrError"
                                    class="flex h-64 w-64 items-center justify-center rounded border bg-red-50 dark:bg-red-900/20"
                                >
                                    <div class="text-center text-red-600 dark:text-red-400">
                                        <p class="text-sm font-medium">Error generating QR code</p>
                                        <p class="mt-1 text-xs">{{ qrError }}</p>
                                    </div>
                                </div>

                                <img v-else-if="qrDataUrl" :src="qrDataUrl" alt="QR code" class="rounded border" width="256" height="256" />

                                <div v-else class="flex h-64 w-64 items-center justify-center rounded border bg-slate-50 dark:bg-slate-800">
                                    <p class="text-sm text-slate-600 dark:text-slate-400">No QR code available</p>
                                </div>

                                <div v-if="qrDataUrl && !isGenerating" class="mt-4 flex gap-2">
                                    <Button variant="outline" size="sm" @click="handleDownloadQR"> Download PNG </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
