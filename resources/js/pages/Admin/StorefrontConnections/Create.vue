<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Form, Link } from '@inertiajs/vue3';
import { ArrowLeft, Plug } from 'lucide-vue-next';

interface Option {
    value: string;
    label: string;
}

interface Artist {
    id: number;
    name: string;
}

defineProps<{
    artists: Artist[];
    platforms: Option[];
    statuses: Option[];
}>();

const breadcrumbs: BreadcrumbItemType[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Connections', href: '/admin/storefront-connections' },
    { title: 'Create', href: '/admin/storefront-connections/create' },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-8 pt-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">New Storefront Connection</h1>
                    <p class="text-sm text-muted-foreground">Create the Order Desk intake record and webhook endpoint for an artist store</p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/admin/storefront-connections">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Connections
                    </Link>
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Connection Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="mb-6 rounded-md border bg-muted p-3 text-sm text-muted-foreground">
                        For Order Desk, use the Order Desk store ID as the external shop ID and the Order Desk API key as the access token. The webhook secret
                        can be left blank; LTD EDN will verify the inbound hash with the encrypted API key.
                    </div>
                    <Form action="/admin/storefront-connections" method="post" #default="{ errors, processing }">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="artist_id">Artist</Label>
                                <select
                                    id="artist_id"
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
                                <Label for="platform">Platform</Label>
                                <select
                                    id="platform"
                                    name="platform"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                                >
                                    <option value="">Select platform</option>
                                    <option v-for="platform in platforms" :key="platform.value" :value="platform.value">{{ platform.label }}</option>
                                </select>
                                <div v-if="errors.platform" class="text-sm text-red-600">{{ errors.platform }}</div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="name">Store name</Label>
                                <Input id="name" name="name" required placeholder="Joe Bloggs Store" />
                                <div v-if="errors.name" class="text-sm text-red-600">{{ errors.name }}</div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="store_url">Store URL</Label>
                                <Input id="store_url" name="store_url" type="url" placeholder="https://example.myshopify.com" />
                                <div v-if="errors.store_url" class="text-sm text-red-600">{{ errors.store_url }}</div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="external_shop_domain">Store domain</Label>
                                <Input id="external_shop_domain" name="external_shop_domain" placeholder="example.myshopify.com or Order Desk store name" />
                                <div v-if="errors.external_shop_domain" class="text-sm text-red-600">{{ errors.external_shop_domain }}</div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="external_shop_id">External shop ID</Label>
                                <Input id="external_shop_id" name="external_shop_id" placeholder="Order Desk store ID" />
                                <div v-if="errors.external_shop_id" class="text-sm text-red-600">{{ errors.external_shop_id }}</div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="connection_status">Setup status</Label>
                                <select
                                    id="connection_status"
                                    name="connection_status"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                                >
                                    <option v-for="status in statuses" :key="status.value" :value="status.value" :selected="status.value === 'testing'">
                                        {{ status.label }}
                                    </option>
                                </select>
                                <div v-if="errors.connection_status" class="text-sm text-red-600">{{ errors.connection_status }}</div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="webhook_secret">Webhook secret</Label>
                                <Input id="webhook_secret" name="webhook_secret" type="password" autocomplete="off" />
                                <div v-if="errors.webhook_secret" class="text-sm text-red-600">{{ errors.webhook_secret }}</div>
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="access_token">Access token / API key</Label>
                                <Input id="access_token" name="access_token" type="password" autocomplete="off" />
                                <div v-if="errors.access_token" class="text-sm text-red-600">{{ errors.access_token }}</div>
                            </div>

                            <div class="grid gap-2 md:col-span-2">
                                <Label for="refresh_token">Refresh token</Label>
                                <Input id="refresh_token" name="refresh_token" type="password" autocomplete="off" />
                                <div v-if="errors.refresh_token" class="text-sm text-red-600">{{ errors.refresh_token }}</div>
                            </div>

                            <div class="flex justify-end gap-2 md:col-span-2">
                                <Button variant="outline" as-child>
                                    <Link href="/admin/storefront-connections">Cancel</Link>
                                </Button>
                                <Button type="submit" :disabled="processing">
                                    <Plug class="mr-2 h-4 w-4" />
                                    {{ processing ? 'Saving...' : 'Save Connection' }}
                                </Button>
                            </div>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
