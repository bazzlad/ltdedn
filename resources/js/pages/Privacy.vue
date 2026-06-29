<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';
</script>

<template>
    <Head title="Privacy Policy – LTD/EDN" />

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
                <h1>Privacy Policy</h1>
                <p class="text-lg text-neutral-600 dark:text-neutral-400">Last updated: June 29, 2026</p>

                <p>
                    This Privacy Policy explains how LTD/EDN collects, uses, and protects personal information when you use our website, account
                    features, limited edition product tools, QR claiming flows, artist administration tools, and storefront fulfilment integrations.
                </p>

                <h2>Information we collect</h2>
                <p>Depending on how you use LTD/EDN, we may collect and process:</p>
                <ul>
                    <li>account information, such as your name, email address, password, role, and login activity;</li>
                    <li>artist, team, product, edition, QR code, claim, transfer, and ownership information;</li>
                    <li>
                        order and fulfilment information, including customer name, email address, shipping address, order totals, line items, SKUs,
                        carrier, and tracking details;
                    </li>
                    <li>
                        storefront integration data from services such as Shopify and Squarespace, including access tokens, store identifiers, webhook
                        identifiers, order payloads, product/SKU data, and fulfilment status;
                    </li>
                    <li>technical information, such as IP address, browser details, device information, logs, and security events.</li>
                </ul>

                <h2>How we use information</h2>
                <p>We use personal information to:</p>
                <ul>
                    <li>provide, secure, maintain, and improve LTD/EDN;</li>
                    <li>create and manage user accounts, artist access, and team permissions;</li>
                    <li>manage limited edition products, QR claims, ownership records, and transfers;</li>
                    <li>
                        import storefront orders, allocate stock, manage fulfilment, send shipment emails, and push tracking updates back to connected
                        storefronts;
                    </li>
                    <li>investigate errors, prevent misuse, provide support, and comply with legal obligations.</li>
                </ul>

                <h2>Storefront integrations</h2>
                <p>
                    If you connect a Shopify or Squarespace store, LTD/EDN requests access only to the data needed to operate the fulfilment
                    integration. This may include order, product, customer, shipping, inventory, webhook, and fulfilment information. We use this data
                    to process orders, update stock and edition records, notify buyers about shipments, and send fulfilment or tracking information
                    back to the storefront.
                </p>
                <p>
                    We store integration credentials securely and use them only to provide the connected service. You can revoke access through the
                    relevant storefront platform or by contacting LTD/EDN support.
                </p>

                <h2>Sharing information</h2>
                <p>We do not sell personal information. We may share information where needed with:</p>
                <ul>
                    <li>
                        artists, team members, administrators, and fulfilment operators who need access to manage products, orders, claims, or
                        shipments;
                    </li>
                    <li>connected commerce platforms, such as Shopify or Squarespace, to import orders and update fulfilment or tracking status;</li>
                    <li>service providers that help us host, secure, email, maintain, or support LTD/EDN;</li>
                    <li>legal, regulatory, or safety authorities where required by law or necessary to protect rights, safety, or security.</li>
                </ul>

                <h2>Retention</h2>
                <p>
                    We keep personal information for as long as needed to provide LTD/EDN, maintain product and ownership records, complete
                    fulfilment, support connected storefronts, resolve disputes, keep security logs, and meet legal or accounting obligations.
                    Retention periods may vary depending on the type of data and the purpose for which it is held.
                </p>

                <h2>Security</h2>
                <p>
                    We use reasonable technical and organisational measures to protect personal information, including access controls and encrypted
                    storage for sensitive storefront credentials. No online service can be guaranteed to be completely secure.
                </p>

                <h2>Your choices and rights</h2>
                <p>
                    You may ask us to access, correct, delete, restrict, or export personal information where applicable law gives you those rights.
                    Some information may need to be retained for operational, legal, security, or record-keeping reasons.
                </p>

                <h2>International processing</h2>
                <p>
                    LTD/EDN and its service providers may process information in countries other than your own. Where required, we use appropriate
                    safeguards for international transfers.
                </p>

                <h2>Contact</h2>
                <p>For privacy questions or requests, contact us through the support details provided on the site or in your order communications.</p>
            </div>
        </main>

        <footer class="border-t border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
            <div class="mx-auto max-w-7xl px-6 py-6 text-center text-sm text-neutral-600 dark:text-neutral-400">
                <p>© {{ new Date().getFullYear() }} LTD/EDN. All rights reserved.</p>
            </div>
        </footer>
    </div>
</template>
