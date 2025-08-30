<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link, router } from '@inertiajs/vue3';
import { Plus, Search, Eye, Edit, Trash2, Palette, User } from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';
import { ref } from 'vue';

interface Owner {
    id: number;
    name: string;
}

interface Artist {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    owner?: Owner;
    created_at: string;
}

interface PaginatedArtists {
    data: Artist[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

defineProps<{
    artists: PaginatedArtists;
}>();

const searchTerm = ref('');

const deleteArtist = (artistId: number) => {
    if (confirm('Are you sure you want to delete this artist?')) {
        router.delete(`/admin/artists/${artistId}`);
    }
};

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Artists', href: '/admin/artists' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Artists</h2>
                    <p class="text-muted-foreground">
                        Manage artist profiles and accounts
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <Button as-child>
                        <Link href="/admin/artists/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Artist
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Artists</CardTitle>
                            <CardDescription>
                                {{ artists.total }} total artists
                            </CardDescription>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="relative">
                                <Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="searchTerm"
                                    placeholder="Search artists..."
                                    class="pl-8 w-64"
                                />
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Slug</TableHead>
                                <TableHead>Owner</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="artist in artists.data" :key="artist.id">
                                <TableCell class="font-medium">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                            <Palette class="h-4 w-4" />
                                        </div>
                                        <span>{{ artist.name }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <code class="text-sm bg-muted px-2 py-1 rounded">{{ artist.slug }}</code>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center space-x-2">
                                        <User class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ artist.owner?.name || 'Unknown' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ formatDistanceToNow(new Date(artist.created_at), { addSuffix: true }) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <Button as-child size="sm" variant="ghost">
                                            <Link :href="`/admin/artists/${artist.id}`">
                                                <Eye class="h-3 w-3" />
                                            </Link>
                                        </Button>
                                        <Button as-child size="sm" variant="ghost">
                                            <Link :href="`/admin/artists/${artist.id}/edit`">
                                                <Edit class="h-3 w-3" />
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="deleteArtist(artist.id)"
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
                    <div v-if="artists.last_page > 1" class="flex items-center justify-between space-x-2 py-4">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (artists.current_page - 1) * artists.per_page + 1 }} to
                            {{ Math.min(artists.current_page * artists.per_page, artists.total) }} of
                            {{ artists.total }} results
                        </div>
                        <div class="flex items-center space-x-2">
                            <Button
                                v-for="link in artists.links"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="!link.url"
                                as-child
                            >
                                <Link v-if="link.url" :href="link.url" v-html="link.label" />
                                <span v-else v-html="link.label" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
