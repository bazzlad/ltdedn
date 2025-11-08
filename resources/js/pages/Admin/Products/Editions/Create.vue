<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    email: string;
}

interface SelectOption {
    value: string;
    label: string;
}

const props = defineProps<{
    product: Product;
    nextNumber: number;
    users: User[];
    statuses: SelectOption[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');

const endNumber = computed(() => {
    if (form.creation_type === 'bulk' && form.start_number && form.quantity) {
        return Number(form.start_number) + Number(form.quantity) - 1;
    }
    return null;
});

const isLargeQuantity = computed(() => {
    return form.creation_type === 'bulk' && Number(form.quantity) > 100;
});

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Products', href: '/admin/products' },
    { title: props.product.name, href: `/admin/products/${props.product.id}` },
    { title: 'Editions', href: `/admin/products/${props.product.id}/editions` },
    { title: 'Create', href: '#' },
];

const form = useForm({
    creation_type: 'single', // 'single' or 'bulk'
    number: props.nextNumber,
    quantity: 1,
    start_number: props.nextNumber,
    status: 'available',
    owner_id: '',
});

const submit = () => {
    if (form.creation_type === 'bulk') {
        form.post(`/admin/products/${props.product.id}/editions/bulk`);
    } else {
        form.post(`/admin/products/${props.product.id}/editions`);
    }
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
                    <h2 class="text-3xl font-bold tracking-tight">Create Edition</h2>
                    <p class="text-muted-foreground">Add a new edition for {{ product.name }}</p>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Edition Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Creation Type Selection -->
                        <div class="space-y-4">
                            <div>
                                <Label>Creation Type</Label>
                                <div class="mt-2 space-x-6">
                                    <label class="inline-flex items-center">
                                        <input
                                            v-model="form.creation_type"
                                            type="radio"
                                            value="single"
                                            class="focus:ring-opacity-50 border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                        />
                                        <span class="ml-2">Single Edition</span>
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input
                                            v-model="form.creation_type"
                                            type="radio"
                                            value="bulk"
                                            class="focus:ring-opacity-50 border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200"
                                        />
                                        <span class="ml-2">Multiple Editions</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Single Edition Number -->
                            <div v-if="form.creation_type === 'single'" class="space-y-2">
                                <Label for="number">Edition Number *</Label>
                                <Input id="number" v-model="form.number" type="number" min="1" placeholder="Edition number" required />
                                <div v-if="form.errors.number" class="text-sm text-red-600">
                                    {{ form.errors.number }}
                                </div>
                                <p class="text-xs text-muted-foreground">Next available number: {{ nextNumber }}</p>
                            </div>

                            <!-- Bulk Edition Fields -->
                            <template v-if="form.creation_type === 'bulk'">
                                <div class="space-y-2">
                                    <Label for="start_number">Starting Number *</Label>
                                    <Input
                                        id="start_number"
                                        v-model="form.start_number"
                                        type="number"
                                        min="1"
                                        placeholder="Starting edition number"
                                        required
                                    />
                                    <div v-if="form.errors.start_number" class="text-sm text-red-600">
                                        {{ form.errors.start_number }}
                                    </div>
                                    <p class="text-xs text-muted-foreground">Next available number: {{ nextNumber }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="quantity">Quantity *</Label>
                                    <Input
                                        id="quantity"
                                        v-model="form.quantity"
                                        type="number"
                                        min="1"
                                        max="1000"
                                        placeholder="Number of editions to create"
                                        required
                                    />
                                    <div v-if="form.errors.quantity" class="text-sm text-red-600">
                                        {{ form.errors.quantity }}
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        <span v-if="endNumber">Will create editions {{ form.start_number }} - {{ endNumber }}</span>
                                    </p>
                                    <div v-if="isLargeQuantity" class="text-xs font-medium text-amber-600">
                                        ⚠️ Creating {{ form.quantity }} editions may take a moment due to QR code generation
                                    </div>
                                </div>
                            </template>

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
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                                </select>
                                <div v-if="form.errors.owner_id" class="text-sm text-red-600">
                                    {{ form.errors.owner_id }}
                                </div>
                                <p class="text-xs text-muted-foreground">Assign this edition to a specific user</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button type="submit" :disabled="form.processing">
                                <template v-if="form.processing">
                                    {{ form.creation_type === 'bulk' ? 'Creating Editions...' : 'Creating Edition...' }}
                                </template>
                                <template v-else>
                                    {{ form.creation_type === 'bulk' ? `Create ${form.quantity} Editions` : 'Create Edition' }}
                                </template>
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <a :href="`/admin/products/${product.id}/editions`">Cancel</a>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
