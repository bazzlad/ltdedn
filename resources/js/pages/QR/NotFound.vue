<template>
    <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
        <!-- background layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div
                class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>

        <!-- Content Wrapper -->
        <div class="relative z-10 flex min-h-screen items-center justify-center">
            <div class="container mx-auto px-4 py-12">
                <div class="mx-auto max-w-md">
                    <div class="rounded-xl border border-neutral-200/50 bg-white/5 p-8 shadow-lg ring-1 ring-neutral-200/50 backdrop-blur-sm dark:border-neutral-800/50 dark:ring-neutral-800/50">
                        <!-- Error Icon -->
                        <div class="mb-8">
                            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/20">
                                <svg class="h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"
                                    />
                                </svg>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <h1 class="mb-4 text-center text-2xl font-extrabold tracking-tight text-white">QR Code Not Found</h1>

                        <p class="mb-6 text-center text-base text-neutral-400">
                            We couldn't find an edition matching this QR code. Please check that you scanned the correct code.
                        </p>

                        <!-- QR Code Display -->
                        <div class="mb-6 rounded-xl border border-neutral-200/50 bg-neutral-900/50 p-4 ring-1 ring-neutral-800/50">
                            <p class="mb-2 text-center text-sm font-semibold text-neutral-400">Scanned Code:</p>
                            <code
                                id="qr-display"
                                class="block break-all rounded-lg bg-neutral-800 px-3 py-2 text-center font-mono text-sm text-neutral-300"
                            >
                                {{ qrCode }}
                            </code>
                        </div>

                        <!-- Help Text -->
                        <div class="mb-6 rounded-xl border border-blue-200/50 bg-blue-500/10 p-5 text-left ring-1 ring-blue-500/20">
                            <h3 class="mb-3 font-bold text-blue-300">Possible reasons:</h3>
                            <ul class="space-y-2 text-sm text-blue-200">
                                <li>• The QR code may be damaged or unclear</li>
                                <li>• This edition might have been removed</li>
                                <li>• The code might be from a different system</li>
                                <li>• There might be a typo in the scanned code</li>
                            </ul>
                        </div>

                        <!-- Actions -->
                        <div class="space-y-3">
                            <Link
                                href="/"
                                class="block w-full rounded-xl bg-white px-5 py-3 text-center font-semibold text-black shadow-sm transition-all hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            >
                                Go to Homepage
                            </Link>
                        </div>

                        <!-- Contact Support -->
                        <div class="mt-8 text-center">
                            <p class="text-sm text-neutral-400">
                                Still having trouble?
                                <a href="mailto:support@ltdedn.com" class="font-medium text-white hover:underline"> Contact Support </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    qrCode: string;
}

defineProps<Props>();
</script>

<style scoped>
#qr-display {
    word-break: break-all;
    display: block;
}
</style>
