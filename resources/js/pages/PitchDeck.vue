<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

const LOGO_SVG = '/images/logo-sm.svg';
const PITCH_DECK_URL = '/downloads/Pitch-Deck.pdf';
</script>

<template>
	<Head title="Pitch Deck - LTD/EDN" />

	<div class="main-bg relative flex min-h-screen flex-col bg-black text-white antialiased">
		<div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
			<div class="absolute inset-0 bg-cover bg-center opacity-40"></div>
			<div class="absolute inset-0 bg-gradient-to-b from-black via-black/70 to-black"></div>
		</div>

		<header class="relative z-10 shrink-0 border-b border-white/10 bg-black/70 backdrop-blur-sm">
			<nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
				<Link :href="home()" class="flex shrink-0 items-center gap-3">
					<img :src="LOGO_SVG" alt="LTD/EDN" class="h-10 w-auto sm:h-12" />
					<span class="sr-only">LTD/EDN</span>
				</Link>

				<div class="flex min-w-0 items-center gap-3">
					<h1 class="truncate font-sans text-sm font-semibold uppercase tracking-wide text-white/80 sm:text-base">
						Investor Pitch Deck
					</h1>
					<div class="hidden shrink-0 items-center gap-2 sm:flex">
						<a
							:href="PITCH_DECK_URL"
							target="_blank"
							rel="noopener"
							class="inline-flex rounded px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white ring-1 ring-white/25 hover:ring-white/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
						>
							Open PDF
						</a>
						<a
							:href="PITCH_DECK_URL"
							download
							class="inline-flex rounded bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-black hover:bg-white/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
						>
							Download PDF
						</a>
					</div>
				</div>
			</nav>
		</header>

		<main class="relative z-10 flex min-h-0 flex-1 flex-col px-3 py-3 sm:px-4 sm:py-4 lg:px-6">
			<object
				:data="PITCH_DECK_URL"
				type="application/pdf"
				class="min-h-[70vh] flex-1 overflow-hidden rounded border border-white/15 bg-neutral-950"
				aria-label="LTD/EDN investor pitch deck"
			>
				<iframe
					:src="PITCH_DECK_URL"
					title="LTD/EDN investor pitch deck"
					class="h-full min-h-[70vh] w-full rounded border-0 bg-neutral-950"
				></iframe>

				<div class="flex min-h-[70vh] flex-col items-center justify-center gap-4 rounded border border-white/15 bg-neutral-950 px-6 text-center">
					<p class="max-w-md text-sm leading-6 text-white/75">
						This browser cannot display the pitch deck inline.
					</p>
					<div class="flex flex-wrap justify-center gap-3">
						<a
							:href="PITCH_DECK_URL"
							target="_blank"
							rel="noopener"
							class="inline-flex rounded px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white ring-1 ring-white/25 hover:ring-white/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
						>
							Open PDF
						</a>
						<a
							:href="PITCH_DECK_URL"
							download
							class="inline-flex rounded bg-white px-4 py-2 text-sm font-semibold uppercase tracking-wide text-black hover:bg-white/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
						>
							Download PDF
						</a>
					</div>
				</div>
			</object>
		</main>
	</div>
</template>
