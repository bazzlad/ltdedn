<script setup lang="ts">
import { store } from '@/routes/password-gate';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    intended: string;
    gate: string;
    error?: string | null;
}

const props = defineProps<Props>();

const form = useForm({
    password: '',
    intended: props.intended,
    gate: props.gate,
});

const showPassword = ref(false);

const submit = () => {
    form.post(store.url(), {
        preserveState: false,
    });
};

const LOGO_LG = '/images/logo-lg.svg';
</script>

<template>
    <Head title="Password Required – LTD/EDN" />

    <div class="main-bg relative flex min-h-screen flex-col bg-black text-white antialiased">
        <!-- Background gradient overlay -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-b from-black via-black/50 to-black"></div>
        </div>

        <!-- Top password bar -->
        <header class="relative z-10 px-4 pt-4 sm:px-6 sm:pt-6">
            <form @submit.prevent="submit" class="mx-auto flex max-w-2xl items-center gap-3 rounded bg-neutral-900/80 px-4 py-3 backdrop-blur-sm">
                <input
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    required
                    class="flex-1 border-0 bg-transparent text-xs font-bold tracking-widest text-white uppercase placeholder:text-neutral-400 focus:ring-0 focus:outline-none"
                    placeholder="ENTER PASSWORD"
                    autofocus
                />

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="flex size-7 shrink-0 items-center justify-center rounded-full border border-white/30 text-white/60 hover:text-white focus:outline-none"
                >
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                </button>

                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="flex size-7 shrink-0 items-center justify-center rounded-full border border-white/30 text-white/60 hover:text-white focus:outline-none"
                >
                    <svg v-if="showPassword" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 11-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                    <svg v-else class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </button>
            </form>

            <div v-if="error" class="mx-auto mt-2 max-w-2xl rounded bg-red-500/20 px-4 py-2 text-center text-sm font-semibold text-red-300">
                {{ error }}
            </div>
        </header>

        <!-- Centered logo -->
        <main class="relative z-10 flex flex-1 items-center justify-center px-6">
            <img :src="LOGO_LG" alt="I AM / LTD EDN" class="w-full max-w-md sm:max-w-lg lg:max-w-xl" />
        </main>

        <!-- Footer -->
        <footer class="relative z-10 py-6 text-center text-xs font-bold tracking-wider text-white/60 uppercase">
            &copy; {{ new Date().getFullYear() }} LTD/EDN
        </footer>
    </div>
</template>
