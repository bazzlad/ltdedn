<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Role {
    value: string;
    label: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

const props = defineProps<{
    user: User;
    roles: Role[];
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role,
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
    { title: 'Edit User', href: `/admin/users/${props.user.id}/edit` },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Edit User</h2>
                    <p class="text-muted-foreground">
                        Update user account information
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="`/admin/users/${props.user.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to User
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>User Details</CardTitle>
                    <CardDescription>
                        Update the user account information
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="form.put(`/admin/users/${props.user.id}`)">
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label for="name">Full Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    v-model="form.name"
                                    placeholder="Enter full name"
                                    required
                                />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email Address</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    v-model="form.email"
                                    placeholder="Enter email address"
                                    required
                                />
                                <div v-if="form.errors.email" class="text-sm text-red-600">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">New Password</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    v-model="form.password"
                                    placeholder="Leave blank to keep current password"
                                />
                                <p class="text-sm text-muted-foreground">
                                    Leave blank to keep the current password
                                </p>
                                <div v-if="form.errors.password" class="text-sm text-red-600">
                                    {{ form.errors.password }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Confirm New Password</Label>
                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    v-model="form.password_confirmation"
                                    placeholder="Confirm new password"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="role">User Role</Label>
                                <select
                                    name="role"
                                    v-model="form.role"
                                    required
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>Select a role</option>
                                    <option v-for="role in roles" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </option>
                                </select>
                                <div v-if="form.errors.role" class="text-sm text-red-600">
                                    {{ form.errors.role }}
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-2">
                                <Button variant="outline" as-child>
                                    <Link :href="`/admin/users/${props.user.id}`">Cancel</Link>
                                </Button>
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Updating...' : 'Update User' }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
