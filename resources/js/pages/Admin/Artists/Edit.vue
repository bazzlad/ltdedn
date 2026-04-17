<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';

import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

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
    bio: string | null;
    hero_image: string | null;
}

const props = defineProps<{
    artist: Artist;
    users: User[];
}>();

const form = useForm({
    _method: 'PUT',
    name: props.artist.name,
    slug: props.artist.slug,
    owner_id: props.artist.owner_id.toString(),
    bio: props.artist.bio ?? '',
    hero_image: null as File | null,
});

const heroPreview = ref<string | null>(props.artist.hero_image || null);

function handleHeroUpload(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;
    form.hero_image = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        heroPreview.value = (e.target?.result as string) ?? null;
    };
    reader.readAsDataURL(file);
}

function clearHero(): void {
    form.hero_image = null;
    heroPreview.value = null;
}

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
                    <p class="text-muted-foreground">Update artist profile information</p>
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
                    <CardDescription> Update the artist profile information </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="form.post(`/admin/artists/${props.artist.id}`)" enctype="multipart/form-data">
                        <div class="grid gap-6">
                            <div class="grid gap-2">
                                <Label for="name">Artist Name</Label>
                                <Input id="name" name="name" type="text" v-model="form.name" placeholder="Enter artist name" required />
                                <div v-if="form.errors.name" class="text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="slug">Slug</Label>
                                <Input id="slug" name="slug" type="text" v-model="form.slug" placeholder="URL-friendly identifier" />
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
                                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>Select an owner</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id.toString()">
                                        {{ user.name }} ({{ user.email }})
                                    </option>
                                </select>
                                <p class="text-sm text-muted-foreground">The user who will own and manage this artist profile.</p>
                                <div v-if="form.errors.owner_id" class="text-sm text-red-600">
                                    {{ form.errors.owner_id }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="bio">Bio</Label>
                                <textarea
                                    id="bio"
                                    name="bio"
                                    rows="5"
                                    v-model="form.bio"
                                    placeholder="Short biography shown on the artist's public landing page."
                                    class="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                ></textarea>
                                <div v-if="form.errors.bio" class="text-sm text-red-600">
                                    {{ form.errors.bio }}
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="hero_image">Hero Image</Label>
                                <div v-if="heroPreview" class="relative">
                                    <img :src="heroPreview" alt="Hero preview" class="h-48 w-full rounded border object-cover" />
                                    <button
                                        type="button"
                                        class="absolute top-2 right-2 rounded-full bg-black/70 p-1 text-white hover:bg-black"
                                        @click="clearHero"
                                        aria-label="Remove hero image"
                                    >
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                                <label
                                    v-else
                                    class="flex h-48 cursor-pointer flex-col items-center justify-center gap-2 rounded border border-dashed border-input bg-background text-sm text-muted-foreground hover:border-primary"
                                >
                                    <Upload class="h-5 w-5" />
                                    <span>Click to upload</span>
                                    <input id="hero_image" name="hero_image" type="file" accept="image/*" class="hidden" @change="handleHeroUpload" />
                                </label>
                                <p class="text-sm text-muted-foreground">Optional. Shown at the top of the public artist page. Max 8 MB.</p>
                                <div v-if="form.errors.hero_image" class="text-sm text-red-600">
                                    {{ form.errors.hero_image }}
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
