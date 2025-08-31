<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import { format, formatDistanceToNow } from 'date-fns';
import { ArrowLeft, Calendar, CheckCircle, Edit, Mail, Palette, Users, XCircle } from 'lucide-vue-next';

interface Artist {
    id: number;
    name: string;
    slug: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    owned_artists?: Artist[];
    artist_teams?: Artist[];
}

const props = defineProps<{
    user: User;
}>();

const roleColors: Record<string, string> = {
    admin: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    artist: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    user: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
    { title: 'User Details', href: `/admin/users/${props.user.id}` },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">{{ props.user.name }}</h2>
                    <p class="text-muted-foreground">User account details and information</p>
                </div>
                <div class="flex items-center space-x-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/users">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Users
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/admin/users/${props.user.id}/edit`">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit User
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <!-- User Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                        <CardDescription> Basic account details </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                <Users class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-lg font-medium">{{ props.user.name }}</p>
                                <Badge :class="roleColors[props.user.role] || roleColors.user">
                                    {{ props.user.role }}
                                </Badge>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div class="flex items-center space-x-3">
                                <Mail class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium">{{ props.user.email }}</p>
                                    <div class="flex items-center space-x-2 text-sm text-muted-foreground">
                                        <CheckCircle v-if="props.user.email_verified_at" class="h-3 w-3 text-green-500" />
                                        <XCircle v-else class="h-3 w-3 text-red-500" />
                                        <span>
                                            {{ props.user.email_verified_at ? 'Verified' : 'Unverified' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium">Member since</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ format(new Date(props.user.created_at), 'PPP') }}
                                        ({{ formatDistanceToNow(new Date(props.user.created_at), { addSuffix: true }) }})
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Activity Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Activity Summary</CardTitle>
                        <CardDescription> User activity and associations </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <Palette class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Owned Artists</span>
                                </div>
                                <span class="text-sm text-muted-foreground">
                                    {{ props.user.owned_artists?.length || 0 }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <Users class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Team Member Of</span>
                                </div>
                                <span class="text-sm text-muted-foreground">
                                    {{ props.user.artist_teams?.length || 0 }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Owned Artists -->
            <Card v-if="props.user.owned_artists && props.user.owned_artists.length > 0">
                <CardHeader>
                    <CardTitle>Owned Artists</CardTitle>
                    <CardDescription> Artists owned by this user </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3">
                        <div
                            v-for="artist in props.user.owned_artists"
                            :key="artist.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                    <Palette class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">{{ artist.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ artist.slug }}</p>
                                </div>
                            </div>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/admin/artists/${artist.id}`">View</Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Team Memberships -->
            <Card v-if="props.user.artist_teams && props.user.artist_teams.length > 0">
                <CardHeader>
                    <CardTitle>Team Memberships</CardTitle>
                    <CardDescription> Artists where this user is a team member </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3">
                        <div
                            v-for="artist in props.user.artist_teams"
                            :key="artist.id"
                            class="flex items-center justify-between rounded-lg border p-3"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                    <Palette class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">{{ artist.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ artist.slug }}</p>
                                </div>
                            </div>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/admin/artists/${artist.id}`">View</Link>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
