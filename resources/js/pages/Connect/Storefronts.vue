<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import UserLayout from '@/layouts/UserLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const props = defineProps<{
    artists: Artist[];
    connections: Connection[];
}>();

const platform = ref<'shopify' | 'squarespace'>('shopify');
const page = usePage();

const connectAction = computed(() => (platform.value === 'shopify' ? '/connect/shopify/start' : '/connect/squarespace/start'));
const storeFieldId = computed(() => (platform.value === 'shopify' ? 'shopify_shop' : 'squarespace_website_id'));
const storeFieldName = computed(() => (platform.value === 'shopify' ? 'shop' : 'website_id'));
const storeFieldLabel = computed(() => (platform.value === 'shopify' ? 'Shopify store' : 'Squarespace website ID'));
const storeFieldPlaceholder = computed(() => (platform.value === 'shopify' ? 'ltdedn-test.myshopify.com' : 'Optional, for multi-site accounts'));
const submitLabel = computed(() => (platform.value === 'shopify' ? 'Connect Shopify' : 'Connect Squarespace'));
const errors = computed(() => (page.props.errors ?? {}) as Record<string, string | undefined>);
const connectErrors = computed(() =>
    ['shopify', 'squarespace', 'artist_id', 'shop', 'website_id', 'name']
        .map((key) => errors.value[key])
        .filter((message): message is string => Boolean(message)),
);
</script>

<template>
    <Head title="LTD EDN Connect" />

    <UserLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-3xl font-bold text-white">LTD EDN Connect</h1>
                <p class="text-sm text-neutral-400">Track your Shopify or Squarespace connection, SKU checks, and first test order.</p>
            </div>

            <Card class="border-white/10 bg-white/95 text-neutral-950 [&_.text-muted-foreground]:text-neutral-600">
                <CardHeader>
                    <CardTitle>Connection Setup</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <p class="text-sm text-muted-foreground">
                        Connect your store through LTD EDN Connect. Confirm your product SKUs with LTD EDN, then place the paid test order once setup
                        is ready.
                    </p>

                    <div v-if="connectErrors.length > 0" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p v-for="message in connectErrors" :key="message">{{ message }}</p>
                    </div>

                    <form
                        :action="connectAction"
                        method="get"
                        class="grid gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                    >
                        <div class="space-y-2">
                            <Label for="platform">Platform</Label>
                            <select
                                id="platform"
                                v-model="platform"
                                class="flex h-9 w-full rounded-md border border-neutral-300 bg-white px-3 py-1 text-sm text-neutral-950 shadow-xs ring-offset-background transition-[color,box-shadow] outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="props.artists.length === 0"
                            >
                                <option value="shopify">Shopify</option>
                                <option value="squarespace">Squarespace</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="artist_id">Artist</Label>
                            <select
                                id="artist_id"
                                name="artist_id"
                                required
                                class="flex h-9 w-full rounded-md border border-neutral-300 bg-white px-3 py-1 text-sm text-neutral-950 shadow-xs ring-offset-background transition-[color,box-shadow] outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="props.artists.length === 0"
                            >
                                <option value="" disabled selected>Select artist</option>
                                <option v-for="artist in props.artists" :key="artist.id" :value="artist.id">
                                    {{ artist.name }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label :for="storeFieldId">{{ storeFieldLabel }}</Label>
                            <Input
                                :id="storeFieldId"
                                :name="storeFieldName"
                                type="text"
                                class="border-neutral-300 bg-white text-neutral-950 placeholder:text-neutral-500"
                                :placeholder="storeFieldPlaceholder"
                                autocomplete="off"
                                :required="platform === 'shopify'"
                                :disabled="props.artists.length === 0"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="name">Connection name</Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                class="border-neutral-300 bg-white text-neutral-950 placeholder:text-neutral-500"
                                placeholder="LTD EDN Test"
                                autocomplete="off"
                                :disabled="props.artists.length === 0"
                            />
                        </div>

                        <div class="flex items-end">
                            <Button type="submit" class="w-full lg:w-auto" :disabled="props.artists.length === 0">{{ submitLabel }}</Button>
                        </div>
                    </form>

                    <p v-if="props.artists.length === 0" class="text-sm text-neutral-600">No artists are available for this account yet.</p>
                </CardContent>
            </Card>

            <Card
                class="border-white/10 bg-white/95 text-neutral-950 [&_.text-muted-foreground]:text-neutral-600 [&_td]:text-neutral-900 [&_th]:text-neutral-600"
            >
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
                                    <Link
                                        class="font-medium text-neutral-950 underline-offset-4 hover:underline"
                                        :href="`/connect/storefronts/${connection.id}/check`"
                                    >
                                        {{ connection.name }}
                                    </Link>
                                </TableCell>
                                <TableCell>{{ connection.platform }}</TableCell>
                                <TableCell>{{ connection.artist_name || '-' }}</TableCell>
                                <TableCell
                                    ><Badge variant="secondary">{{ connection.connection_status }}</Badge></TableCell
                                >
                                <TableCell>{{ connection.tested_at || '-' }}</TableCell>
                                <TableCell>{{ connection.activated_at || '-' }}</TableCell>
                            </TableRow>
                            <TableRow v-if="connections.length === 0">
                                <TableCell colspan="6" class="py-8 text-center text-sm text-neutral-600">No storefront connections yet.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </UserLayout>
</template>
