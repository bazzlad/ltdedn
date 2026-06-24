<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserLayout from '@/layouts/UserLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PlugZap } from 'lucide-vue-next';
import { computed } from 'vue';

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

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string | undefined>);
</script>

<template>
    <Head title="LTD EDN Connect" />

    <UserLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold text-white">LTD EDN Connect</h1>
                <p class="text-sm text-neutral-400">Connect your store, check SKUs, and confirm the first test order.</p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="border-white/10 bg-white/95">
                    <CardHeader>
                        <CardTitle>Connect Shopify</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form action="/connect/shopify/start" method="get">
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="shopify_artist_id">Artist</Label>
                                    <select
                                        id="shopify_artist_id"
                                        name="artist_id"
                                        required
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        <option value="">Select artist</option>
                                        <option v-for="artist in artists" :key="artist.id" :value="artist.id">{{ artist.name }}</option>
                                    </select>
                                    <div v-if="errors.artist_id" class="text-sm text-red-600">{{ errors.artist_id }}</div>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="shop">Shopify domain</Label>
                                    <Input id="shop" name="shop" placeholder="example.myshopify.com" required />
                                    <div v-if="errors.shop" class="text-sm text-red-600">{{ errors.shop }}</div>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="shopify_name">Store name</Label>
                                    <Input id="shopify_name" name="name" placeholder="Joe Bloggs Store" />
                                </div>
                                <Button type="submit" :disabled="artists.length === 0">
                                    <PlugZap class="mr-2 h-4 w-4" />
                                    Connect Shopify
                                </Button>
                                <div v-if="errors.shopify" class="text-sm text-red-600">{{ errors.shopify }}</div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card class="border-white/10 bg-white/95">
                    <CardHeader>
                        <CardTitle>Connect Squarespace</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form action="/connect/squarespace/start" method="get">
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="squarespace_artist_id">Artist</Label>
                                    <select
                                        id="squarespace_artist_id"
                                        name="artist_id"
                                        required
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        <option value="">Select artist</option>
                                        <option v-for="artist in artists" :key="artist.id" :value="artist.id">{{ artist.name }}</option>
                                    </select>
                                    <div v-if="errors.artist_id" class="text-sm text-red-600">{{ errors.artist_id }}</div>
                                </div>
                                <div class="grid gap-2">
                                    <Label for="website_id">Website ID</Label>
                                    <Input id="website_id" name="website_id" />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="squarespace_name">Store name</Label>
                                    <Input id="squarespace_name" name="name" placeholder="Joe Bloggs Squarespace" />
                                </div>
                                <Button type="submit" :disabled="artists.length === 0">
                                    <PlugZap class="mr-2 h-4 w-4" />
                                    Connect Squarespace
                                </Button>
                                <div v-if="errors.squarespace" class="text-sm text-red-600">{{ errors.squarespace }}</div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

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
