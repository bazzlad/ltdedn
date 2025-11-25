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
        <div class="relative z-10 flex min-h-screen items-center justify-center py-8 sm:py-12">
            <div class="container mx-auto px-4">
                <div class="mx-auto max-w-md">
                    <div class="rounded-xl border border-neutral-200/50 bg-white/5 p-5 shadow-lg ring-1 ring-neutral-200/50 backdrop-blur-sm sm:p-8 dark:border-neutral-800/50 dark:ring-neutral-800/50">
                        <!-- Error Icon -->
                        <div class="mb-6 sm:mb-8">
                            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-500/10 ring-1 ring-red-500/20 sm:h-24 sm:w-24">
                                <svg class="h-10 w-10 text-red-500 sm:h-12 sm:w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <h1 class="mb-3 text-center text-xl font-extrabold tracking-tight text-white sm:mb-4 sm:text-2xl">QR Code Not Found</h1>

                        <p class="mb-5 text-center text-sm text-neutral-400 sm:mb-6 sm:text-base">
                            We couldn't find an edition matching this QR code. Please check that you scanned the correct code.
                        </p>

                        <!-- QR Code Display -->
                        <div class="mb-5 rounded-xl border border-neutral-200/50 bg-neutral-900/50 p-3 ring-1 ring-neutral-800/50 sm:mb-6 sm:p-4">
                            <p class="mb-2 text-center text-xs font-semibold text-neutral-400 sm:text-sm">Scanned Code:</p>
                            <code
                                id="qr-display"
                                class="block break-all rounded-lg bg-neutral-800 px-2.5 py-2 text-center font-mono text-xs text-neutral-300 sm:px-3 sm:text-sm"
                            >
                                {{ qrCode }}
                            </code>
                        </div>

                        <!-- Help Text -->
                        <div class="mb-5 rounded-xl border border-blue-200/50 bg-blue-500/10 p-4 text-left ring-1 ring-blue-500/20 sm:mb-6 sm:p-5">
                            <h3 class="mb-2 text-sm font-bold text-blue-300 sm:mb-3 sm:text-base">Possible reasons:</h3>
                            <ul class="space-y-1.5 text-xs text-blue-200 sm:space-y-2 sm:text-sm">
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
                                class="block w-full rounded-xl bg-white px-5 py-3.5 text-center text-sm font-bold text-black shadow-lg transition-all hover:scale-[1.02] hover:bg-neutral-100 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-[0.98] sm:py-4 sm:text-base"
                            >
                                Go to Homepage
                            </Link>
                        </div>

                        <!-- Contact Support -->
                        <div class="mt-6 text-center sm:mt-8">
                            <p class="text-xs text-neutral-400 sm:text-sm">
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
