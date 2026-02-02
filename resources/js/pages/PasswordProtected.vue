<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    action: string;
    error?: string | null;
}

const props = defineProps<Props>();

const form = useForm({
    password: '',
});

const showPassword = ref(false);

const submit = () => {
    form.post(props.action, {
        preserveState: false,
    });
};

const LOGO_SVG = '/images/logo-sm.svg';
</script>

<template>
    <Head title="Password Required – LTD/EDN" />

    <div class="main-bg relative flex min-h-screen flex-col bg-black text-neutral-200 antialiased">
        <!-- background layers -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
            <div
                class="absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,rgba(255,255,255,.06)_1px,transparent_0)] [background-size:24px_24px] opacity-20 mix-blend-soft-light"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>

        <!-- Top nav -->
        <header class="relative z-10">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8">
                <Link :href="home()" class="flex items-center gap-3">
                    <img :src="LOGO_SVG" alt="LTD/EDN" class="h-16 w-auto" />
                    <span class="sr-only">LTD/EDN</span>
                </Link>
                <Link
                    :href="home()"
                    class="inline-flex items-center rounded-xl px-4 py-2 font-semibold ring-1 ring-white/20 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                >
                    Back to Home
                </Link>
            </nav>
        </header>

        <!-- Main content fills the space between header and footer -->
        <main
            class="relative isolate z-10 mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center px-6 pt-10 pb-16 text-center lg:px-8 lg:pt-14"
        >
            <div class="w-full rounded-2xl bg-white/5 p-8 backdrop-blur-sm ring-1 ring-white/10">
                <div class="text-center">
                    <h1 class="text-2xl font-bold tracking-tight text-white">
                        Password Required
                    </h1>
                    <p class="mt-2 text-sm text-neutral-300">
                        This page requires a password to access.
                    </p>
                </div>

                <form @submit.prevent="submit" class="mt-8 space-y-6">
                    <div>
                        <label for="password" class="block text-sm font-medium text-neutral-200">
                            Password
                        </label>
                        <div class="relative mt-2">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                required
                                class="block w-full rounded-xl border-0 bg-white/10 px-4 py-3 pr-12 text-white placeholder:text-neutral-400 ring-1 ring-inset ring-white/20 focus:bg-white/15 focus:ring-2 focus:ring-inset focus:ring-white/40"
                                placeholder="Enter password"
                                autofocus
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-neutral-400 hover:text-white focus:outline-none"
                            >
                                <svg v-if="showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 11-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-if="error" class="text-sm text-red-300">
                        {{ error }}
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full rounded-xl bg-white/10 px-4 py-3 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white disabled:opacity-50"
                    >
                        <span v-if="form.processing">Verifying...</span>
                        <span v-else>Access Page</span>
                    </button>
                </form>
            </div>
        </main>

        <!-- Footer stays at bottom because parent is flex-col and main is flex-1 -->
        <footer class="relative z-10 border-t border-white/10 bg-black/50">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-xs text-white sm:flex-row lg:px-8">
                <p>© {{ new Date().getFullYear() }} LTD/EDN. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <Link :href="home()" class="hover:text-neutral-200">Home</Link>
                </div>
            </div>
        </footer>
    </div>
</template>