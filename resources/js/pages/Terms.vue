<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';
</script>

<template>
    <Head title="Terms of Service – LTD/EDN" />

    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
        <header class="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
                <Link :href="home()" class="flex items-center gap-3">
                    <img :src="LOGO_SVG" alt="LTD/EDN" class="h-12 w-auto" />
                    <span class="sr-only">LTD/EDN</span>
                </Link>
                <Link
                    :href="home()"
                    class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-neutral-700 ring-1 ring-neutral-300 hover:bg-neutral-50 dark:text-neutral-300 dark:ring-neutral-700 dark:hover:bg-neutral-800"
                >
                    Back to Home
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-12 lg:px-8 lg:py-16">
            <div class="prose prose-neutral dark:prose-invert max-w-none">
                <h1>Terms of Service</h1>
                <p class="text-lg text-neutral-600 dark:text-neutral-400">Last updated: June 29, 2026</p>

                <p>
                    These Terms of Service govern your access to and use of LTD/EDN, including artist administration tools, limited edition product
                    pages, QR code claiming, user accounts, and storefront fulfilment integrations.
                </p>

                <h2>Who we are</h2>
                <p>
                    LTD/EDN provides tools for artists and operators to manage limited edition products, ownership claims, fulfilment workflows, and
                    connected storefront orders. References to "LTD/EDN", "we", "us", or "our" mean the operator of the LTD/EDN service.
                </p>

                <h2>Using the service</h2>
                <p>
                    You must provide accurate account, artist, product, order, and shipping information when using LTD/EDN. You are responsible for
                    keeping your login details secure and for all activity carried out through your account.
                </p>
                <p>
                    Artists, team members, and administrators must only connect storefronts, import orders, manage inventory, or update fulfilment
                    records where they have authority to do so. You must not misuse the service, interfere with its operation, attempt unauthorised
                    access, or use LTD/EDN for unlawful activity.
                </p>

                <h2>Storefront integrations</h2>
                <p>
                    LTD/EDN may connect to third-party commerce platforms such as Shopify and Squarespace so that orders can be imported, stock can be
                    allocated, shipments can be recorded, and fulfilment or tracking updates can be sent back to the storefront.
                </p>
                <p>
                    By connecting a storefront, you confirm that you are authorised to grant LTD/EDN access to that store and to the order, product,
                    inventory, customer, shipping, and fulfilment data needed to provide the integration. Your use of a third-party storefront remains
                    subject to that platform's own terms and policies.
                </p>

                <h2>Orders, fulfilment, and ownership records</h2>
                <p>
                    LTD/EDN helps record product editions, ownership claims, transfers, and fulfilment activity. Artists and operators remain
                    responsible for product descriptions, pricing, taxes, customer support, shipping decisions, and compliance with applicable laws.
                </p>
                <p>
                    We may process test orders and operational fulfilment events to confirm that an integration is working correctly. You are
                    responsible for ensuring that test purchases, refunds, and customer communications are handled appropriately.
                </p>

                <h2>Content and intellectual property</h2>
                <p>
                    You retain ownership of content you provide, including artist information, product details, images, and related materials. You
                    grant LTD/EDN permission to use that content as needed to operate, display, support, and improve the service.
                </p>
                <p>
                    LTD/EDN's software, branding, interface, and underlying systems remain our property or the property of our licensors. You may not
                    copy, reverse engineer, or redistribute the service except as permitted by law or with our written permission.
                </p>

                <h2>Availability and changes</h2>
                <p>
                    We aim to keep LTD/EDN available and reliable, but the service may be interrupted for maintenance, updates, third-party platform
                    issues, or events outside our control. We may update, suspend, or discontinue parts of the service where needed.
                </p>

                <h2>Disclaimers and liability</h2>
                <p>
                    LTD/EDN is provided on an "as is" and "as available" basis. To the fullest extent permitted by law, we do not guarantee that the
                    service will be uninterrupted, error-free, or compatible with every storefront configuration.
                </p>
                <p>
                    Nothing in these terms excludes liability that cannot be excluded by law. Otherwise, LTD/EDN will not be liable for indirect,
                    incidental, special, consequential, or loss-of-profit damages arising from use of the service.
                </p>

                <h2>Contact</h2>
                <p>
                    For questions about these terms or the LTD/EDN service, contact us through the support details provided on the site or in your
                    order communications.
                </p>
            </div>
        </main>

        <footer class="border-t border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <div class="mx-auto max-w-7xl px-6 py-6 text-center text-sm text-neutral-600 dark:text-neutral-400">
                <p>© {{ new Date().getFullYear() }} LTD/EDN. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>
