<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Palette, User, Users, Calendar, ExternalLink } from 'lucide-vue-next';
import { formatDistanceToNow, format } from 'date-fns';

interface Owner {
    id: number;
    name: string;
    email: string;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
}

interface Artist {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    owner?: Owner;
    team_members?: TeamMember[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    artist: Artist;
}>();

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Artists', href: '/admin/artists' },
    { title: 'Artist Details', href: `/admin/artists/${props.artist.id}` },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">{{ artist.name }}</h2>
                    <p class="text-muted-foreground">
                        Artist profile details and information
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/artists">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Artists
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/admin/artists/${artist.id}/edit`">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit Artist
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <!-- Artist Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Artist Information</CardTitle>
                        <CardDescription>
                            Basic profile details
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                <Palette class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-lg font-medium">{{ artist.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ artist.slug }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <div class="flex items-center space-x-3">
                                <ExternalLink class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium">Slug</p>
                                    <code class="text-sm bg-muted px-2 py-1 rounded">{{ artist.slug }}</code>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium">Created</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ format(new Date(artist.created_at), 'PPP') }}
                                        ({{ formatDistanceToNow(new Date(artist.created_at), { addSuffix: true }) }})
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Owner Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Owner Information</CardTitle>
                        <CardDescription>
                            User who owns this artist profile
                        </CardDescription>
                    </CardHeader>
                    <CardContent v-if="artist.owner" class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                <User class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-lg font-medium">{{ artist.owner.name }}</p>
                                <p class="text-sm text-muted-foreground">{{ artist.owner.email }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/admin/users/${artist.owner.id}`">
                                    View User Profile
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                    <CardContent v-else>
                        <p class="text-sm text-muted-foreground">No owner information available</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Members -->
            <Card>
                <CardHeader>
                    <CardTitle>Team Members</CardTitle>
                    <CardDescription>
                        Users who are part of this artist's team
                        <span v-if="artist.team_members">({{ artist.team_members.length }})</span>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="artist.team_members && artist.team_members.length > 0" class="grid gap-3">
                        <div
                            v-for="member in artist.team_members"
                            :key="member.id"
                            class="flex items-center justify-between p-3 border rounded-lg"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                    <Users class="h-4 w-4" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">{{ member.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                                </div>
                            </div>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/admin/users/${member.id}`">View</Link>
                            </Button>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <Users class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-2 text-sm font-semibold">No team members</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            This artist doesn't have any team members yet.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
