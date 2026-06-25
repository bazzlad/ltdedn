<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserLayout from '@/layouts/UserLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Artist {
    id: number;
    name: string;
}

interface Connection {
    id: number;
    platform: string;
    name: string;
    artist_name: string | null;
    connection_status: string;
    tested_at: string | null;
    activated_at: string | null;
}

defineProps<{
    artists: Artist[];
    connections: Connection[];
}>();
</script>

<template>
    <Head title="LTD EDN Connect" />

    <UserLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold text-white">LTD EDN Connect</h1>
                <p class="text-sm text-neutral-400">Track your Order Desk connection, SKU checks, and first test order.</p>
            </div>

            <Card class="border-white/10 bg-white/95">
                <CardHeader>
                    <CardTitle>Connection Setup</CardTitle>
                </CardHeader>
                <CardContent class="text-sm text-muted-foreground">
                    LTD EDN now connects storefronts through Order Desk. Confirm your product SKUs with LTD EDN, then place the paid test order once setup is
                    ready.
                </CardContent>
            </Card>

            <Card class="border-white/10 bg-white/95">
                <CardHeader>
                    <CardTitle>Connections</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Platform</TableHead>
                                <TableHead>Artist</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Tested</TableHead>
                                <TableHead>Ready</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="connection in connections" :key="connection.id">
                                <TableCell>
                                    <Link class="font-medium underline-offset-4 hover:underline" :href="`/connect/storefronts/${connection.id}/check`">
                                        {{ connection.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>{{ connection.platform }}</TableCell>
                                <TableCell>{{ connection.artist_name || '-' }}</TableCell>
                                <TableCell><Badge variant="secondary">{{ connection.connection_status }}</Badge></TableCell>
                                <TableCell>{{ connection.tested_at || '-' }}</TableCell>
                                <TableCell>{{ connection.activated_at || '-' }}</TableCell>
                            </TableRow>
                            <TableRow v-if="connections.length === 0">
                                <TableCell colspan="6" class="py-8 text-center text-sm text-muted-foreground">No storefront connections yet.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </UserLayout>
</template>
