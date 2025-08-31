<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import type { BreadcrumbItemType } from '@/types';

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
                    <p class="text-muted-foreground">
                        Update edition information for {{ product.name }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Edition Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="submit" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Edition Number -->
                                    <div class="space-y-2">
                                        <Label for="number">Edition Number *</Label>
                                        <Input
                                            id="number"
                                            v-model="form.number"
                                            type="number"
                                            min="1"
                                            placeholder="Edition number"
                                            required
                                        />
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
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
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
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="">No owner assigned</option>
                                            <option v-for="user in users" :key="user.id" :value="user.id">
                                                {{ user.name }} {{ user.email ? `(${user.email})` : '' }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.owner_id" class="text-sm text-red-600">
                                            {{ form.errors.owner_id }}
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            Assign this edition to a specific user
                                        </p>
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
                                <h4 class="font-medium text-sm text-muted-foreground mb-2">Long QR Code</h4>
                                <p class="text-xs font-mono bg-muted px-2 py-1 rounded break-all">{{ edition.qr_code }}</p>
                            </div>
                            <div v-if="edition.qr_short_code">
                                <h4 class="font-medium text-sm text-muted-foreground mb-2">Short Code</h4>
                                <p class="text-sm font-mono bg-muted px-2 py-1 rounded">{{ edition.qr_short_code }}</p>
                            </div>
                            <div v-else>
                                <p class="text-sm text-muted-foreground">No short code generated</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
