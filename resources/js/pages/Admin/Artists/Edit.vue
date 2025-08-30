<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Artist {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
}

const props = defineProps<{
    artist: Artist;
    users: User[];
}>();

const form = useForm({
    name: props.artist.name,
    slug: props.artist.slug,
    owner_id: props.artist.owner_id.toString(),
});

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Artists', href: '/admin/artists' },
    { title: 'Edit Artist', href: `/admin/artists/${props.artist.id}/edit` },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Edit Artist</h2>
                    <p class="text-muted-foreground">
                        Update artist profile information
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="`/admin/artists/${props.artist.id}`">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Artist
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Artist Details</CardTitle>
                    <CardDescription>
                        Update the artist profile information
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="form.put(`/admin/artists/${props.artist.id}`)">
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label for="name">Artist Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    type="text"
                                    v-model="form.name"
                                    placeholder="Enter artist name"
                                    required
                                />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    type="text"
                                    v-model="form.slug"
                                    placeholder="URL-friendly identifier"
                                />
                                <p class="text-sm text-muted-foreground">
                                    URL-friendly identifier. Leave blank to auto-generate from the artist name.
                                </p>
                                <div v-if="form.errors.slug" class="text-sm text-red-600">
                                    {{ form.errors.slug }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="owner_id">Artist Owner</Label>
                                <select
                                    name="owner_id"
                                    v-model="form.owner_id"
                                    required
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>Select an owner</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id.toString()">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                                <p class="text-sm text-muted-foreground">
                                    The user who will own and manage this artist profile.
                                </p>
                                <div v-if="form.errors.owner_id" class="text-sm text-red-600">
                                    {{ form.errors.owner_id }}
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-2">
                                <Button variant="outline" as-child>
                                    <Link :href="`/admin/artists/${props.artist.id}`">Cancel</Link>
                                </Button>
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Updating...' : 'Update Artist' }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
