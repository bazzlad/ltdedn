<template>

    <div class="flex-grow main-bg">
        <!-- background layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>
        <!-- Content Wrapper -->
        <div class="relative z-10">
            <!--<div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">-->
            <div class="flex min-h-screen items-center justify-center">
                <div class="container mx-auto px-4">
                    <div class="mx-auto max-w-md text-center main-card">
                        <!-- Error Icon -->
                        <div class="mb-8">
                            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20">
                                <svg class="h-12 w-12 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <h1 class="mb-4 text-2xl font-bold text-slate-900 dark:text-slate-100">QR Code Not Found</h1>

                        <p class="mb-6 text-slate-600 dark:text-slate-400">
                            We couldn't find an edition matching this QR code. Please check that you scanned the correct code.
                        </p>

                        <!-- QR Code Display -->
                        <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                            <p class="mb-2 text-sm text-slate-500 dark:text-slate-400">Scanned Code:</p>
                            <code id="qr-display" class="rounded bg-slate-100 px-3 py-2 font-mono text-sm text-slate-800 dark:bg-slate-700 dark:text-slate-200">
                                {{ qrCode }}
                            </code>
                        </div>

                        <!-- Help Text -->
                        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-left dark:border-blue-800 dark:bg-blue-900/20">
                            <h3 class="mb-2 font-medium text-blue-800 dark:text-blue-300">Possible reasons:</h3>
                            <ul class="space-y-1 text-sm text-blue-700 dark:text-blue-400">
                                <li>• The QR code may be damaged or unclear</li>
                                <li>• This edition might have been removed</li>
                                <li>• The code might be from a different system</li>
                                <li>• There might be a typo in the scanned code</li>
                            </ul>
                        </div>

                        <!-- Actions -->
                        <div class="space-y-3">
                            <Link href="/" class="block w-full rounded-lg bg-blue-600 px-4 py-3 font-medium text-white transition-colors hover:bg-blue-700">
                                Go to Homepage
                            </Link>
                        </div>

                        <!-- Contact Support -->
                        <div class="mt-8 text-center">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Still having trouble?
                                <a href="mailto:support@ltdedn.com" class="text-blue-600 hover:underline dark:text-blue-400"> Contact Support </a>
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
