<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { Form, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
}

defineProps<{
    users: User[];
}>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Artists', href: '/admin/artists' },
    { title: 'Create Artist', href: '/admin/artists/create' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Create Artist</h2>
                    <p class="text-muted-foreground">
                        Add a new artist profile to the system
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/admin/artists">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Artists
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Artist Details</CardTitle>
                    <CardDescription>
                        Enter the details for the new artist profile
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        action="/admin/artists"
                        method="post"
                        #default="{ errors, processing }"
                    >
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label for="name">Artist Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    placeholder="Enter artist name"
                                    required
                                />
                                <div v-if="errors.name" class="text-sm text-red-600">
                                    {{ errors.name }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    type="text"
                                    placeholder="Leave blank to auto-generate from name"
                                />
                                <p class="text-sm text-muted-foreground">
                                    URL-friendly identifier. Leave blank to auto-generate from the artist name.
                                </p>
                                <div v-if="errors.slug" class="text-sm text-red-600">
                                    {{ errors.slug }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="owner_id">Artist Owner</Label>
                                <select
                                    name="owner_id"
                                    required
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled selected>Select an owner</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id.toString()">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                                <p class="text-sm text-muted-foreground">
                                    The user who will own and manage this artist profile.
                                </p>
                                <div v-if="errors.owner_id" class="text-sm text-red-600">
                                    {{ errors.owner_id }}
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-2">
                                <Button variant="outline" as-child>
                                    <Link href="/admin/artists">Cancel</Link>
                                </Button>
                                <Button type="submit" :disabled="processing">
                                    {{ processing ? 'Creating...' : 'Create Artist' }}
                                </Button>
                            </div>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
