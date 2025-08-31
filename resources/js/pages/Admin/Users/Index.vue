<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { CheckCircle, Edit, Eye, Mail, Plus, Search, Trash2, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    email_verified_at: string | null;
    created_at: string;
}

interface PaginatedUsers {
    data: User[];
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
    users: PaginatedUsers;
}>();

const roleColors: Record<string, string> = {
    admin: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    artist: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    user: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
};

const searchTerm = ref('');

const deleteUser = (userId: number) => {
    if (confirm('Are you sure you want to delete this user?')) {
        router.delete(`/admin/users/${userId}`);
    }
};

const breadcrumbs = [
    { title: 'Admin', href: '/admin' },
    { title: 'Users', href: '/admin/users' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1 space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between space-y-2">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight">Users</h2>
                    <p class="text-muted-foreground">Manage user accounts and permissions</p>
                </div>
                <div class="flex items-center space-x-2">
                    <Button as-child>
                        <Link href="/admin/users/create">
                            <Plus class="mr-2 h-4 w-4" />
                            Add User
                        </Link>
                    </Button>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>All Users</CardTitle>
                            <CardDescription> {{ users.total }} total users </CardDescription>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="relative">
                                <Search class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                <Input v-model="searchTerm" placeholder="Search users..." class="w-64 pl-8" />
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Verified</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="user in users.data" :key="user.id">
                                <TableCell class="font-medium">
                                    {{ user.name }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center space-x-2">
                                        <Mail class="h-4 w-4 text-muted-foreground" />
                                        <span>{{ user.email }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge :class="roleColors[user.role] || roleColors.user">
                                        {{ user.role }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center space-x-2">
                                        <CheckCircle v-if="user.email_verified_at" class="h-4 w-4 text-green-500" />
                                        <XCircle v-else class="h-4 w-4 text-red-500" />
                                        <span class="text-sm">
                                            {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ formatDistanceToNow(new Date(user.created_at), { addSuffix: true }) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        <Button as-child size="sm" variant="ghost">
                                            <Link :href="`/admin/users/${user.id}`">
                                                <Eye class="h-3 w-3" />
                                            </Link>
                                        </Button>
                                        <Button as-child size="sm" variant="ghost">
                                            <Link :href="`/admin/users/${user.id}/edit`">
                                                <Edit class="h-3 w-3" />
                                            </Link>
                                        </Button>
                                        <Button size="sm" variant="ghost" @click="deleteUser(user.id)" class="text-red-600 hover:text-red-700">
                                            <Trash2 class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div v-if="users.last_page > 1" class="flex items-center justify-between space-x-2 py-4">
                        <div class="text-sm text-muted-foreground">
                            Showing {{ (users.current_page - 1) * users.per_page + 1 }} to
                            {{ Math.min(users.current_page * users.per_page, users.total) }} of {{ users.total }} results
                        </div>
                        <div class="flex items-center space-x-2">
                            <Button
                                v-for="link in users.links"
                                :key="link.label"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                :disabled="!link.url"
                                as-child
                            >
                                <Link v-if="link.url" :href="link.url">{{ link.label }}</Link>
                                <span v-else>{{ link.label }}</span>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
