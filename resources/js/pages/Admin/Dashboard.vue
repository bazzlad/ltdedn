<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Link, usePage } from '@inertiajs/vue3';
import { Users, Palette, Plus, Eye, Package } from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';
import { computed } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

interface Artist {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    owner?: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface Stats {
    total_users?: number;
    total_artists: number;
    total_products?: number;
    recent_users?: User[];
    recent_artists: Artist[];
    recent_products?: any[];
}

defineProps<{
    stats: Stats;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => user.value?.role === 'admin');
const isArtist = computed(() => user.value?.role === 'artist');

const roleColors: Record<string, string> = {
    admin: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    artist: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    user: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};

const dashboardTitle = computed(() => {
    if (isAdmin.value) return 'Admin Dashboard';
    if (isArtist.value) return 'Artist Dashboard';
    return 'Dashboard';
});
</script>

<template>
    <AdminLayout>
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <h2 class="text-3xl font-bold tracking-tight">{{ dashboardTitle }}</h2>
                <div class="flex items-center space-x-2">
                    <!-- Admin-only buttons -->
                    <template v-if="isAdmin">
                        <Button as-child>
                            <Link href="/admin/users/create">
                                <Plus class="mr-2 h-4 w-4" />
                                Add User
                            </Link>
                        </Button>
                        <Button as-child variant="outline">
                            <Link href="/admin/artists/create">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Artist
                            </Link>
                        </Button>
                    </template>

                    <!-- Shared buttons (both admin and artist) -->
                    <Button as-child variant="outline">
                        <Link href="/admin/products/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Product
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Admin-only stats -->
                <Card v-if="isAdmin && stats.total_users !== undefined">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_users }}</div>
                        <p class="text-xs text-muted-foreground">
                            Registered users in the system
                        </p>
                    </CardContent>
                </Card>

                <!-- Shared stats -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            {{ isAdmin ? 'Total Artists' : 'My Artists' }}
                        </CardTitle>
                        <Palette class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_artists }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ isAdmin ? 'Artist profiles created' : 'Artists you own' }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Products stats (placeholder for future) -->
                <Card v-if="stats.total_products !== undefined">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">
                            {{ isAdmin ? 'Total Products' : 'My Products' }}
                        </CardTitle>
                        <Package class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_products }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ isAdmin ? 'Products in the system' : 'Products you created' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent Activity -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Recent Users (Admin only) -->
                <Card v-if="isAdmin && stats.recent_users">
                    <CardHeader>
                        <CardTitle>Recent Users</CardTitle>
                        <CardDescription>Latest user registrations</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="user in stats.recent_users" :key="user.id" class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-muted">
                                    <Users class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium leading-none">{{ user.name }}</p>
                                    <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Badge :class="roleColors[user.role] || roleColors.user">
                                    {{ user.role }}
                                </Badge>
                                <Button as-child size="sm" variant="ghost">
                                    <Link :href="`/admin/users/${user.id}`">
                                        <Eye class="h-3 w-3" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <div class="text-center">
                            <Button as-child variant="outline" size="sm">
                                <Link href="/admin/users">View All Users</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Artists -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ isAdmin ? 'Recent Artists' : 'My Artists' }}</CardTitle>
                        <CardDescription>{{ isAdmin ? 'Latest artist profiles' : 'Your artist profiles' }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="artist in stats.recent_artists" :key="artist.id" class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-muted">
                                    <Palette class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium leading-none">{{ artist.name }}</p>
                                    <p class="text-sm text-muted-foreground" v-if="isAdmin">
                                        Owner: {{ artist.owner?.name || 'Unknown' }}
                                    </p>
                                    <p class="text-sm text-muted-foreground" v-else>
                                        {{ formatDistanceToNow(new Date(artist.created_at), { addSuffix: true }) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs text-muted-foreground" v-if="isAdmin">
                                    {{ formatDistanceToNow(new Date(artist.created_at), { addSuffix: true }) }}
                                </span>
                                <Button as-child size="sm" variant="ghost" v-if="isAdmin">
                                    <Link :href="`/admin/artists/${artist.id}`">
                                        <Eye class="h-3 w-3" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <div class="text-center" v-if="isAdmin">
                            <Button as-child variant="outline" size="sm">
                                <Link href="/admin/artists">View All Artists</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
