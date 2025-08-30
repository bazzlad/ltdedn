<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { Form, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Role {
    value: string;
    label: string;
}

defineProps<{
    roles: Role[];
}>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
    { title: 'Create User', href: '/admin/users/create' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Create User</h2>
                    <p class="text-muted-foreground">
                        Add a new user to the system
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/admin/users">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Users
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>User Details</CardTitle>
                    <CardDescription>
                        Enter the details for the new user account
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        action="/admin/users"
                        method="post"
                        #default="{ errors, processing }"
                    >
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label for="name">Full Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    placeholder="Enter full name"
                                    required
                                />
                                <div v-if="errors.name" class="text-sm text-red-600">
                                    {{ errors.name }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Email Address</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    placeholder="Enter email address"
                                    required
                                />
                                <div v-if="errors.email" class="text-sm text-red-600">
                                    {{ errors.email }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">Password</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    placeholder="Enter password"
                                    required
                                />
                                <div v-if="errors.password" class="text-sm text-red-600">
                                    {{ errors.password }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Confirm Password</Label>
                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    placeholder="Confirm password"
                                    required
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="role">User Role</Label>
                                <select
                                    name="role"
                                    required
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled selected>Select a role</option>
                                    <option v-for="role in roles" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </option>
                                </select>
                                <div v-if="errors.role" class="text-sm text-red-600">
                                    {{ errors.role }}
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-2">
                                <Button variant="outline" as-child>
                                    <Link href="/admin/users">Cancel</Link>
                                </Button>
                                <Button type="submit" :disabled="processing">
                                    {{ processing ? 'Creating...' : 'Create User' }}
                                </Button>
                            </div>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
