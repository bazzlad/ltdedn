<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const LOGO_SVG = '/images/logo-sm.svg';

// swap these for your real file URLs (or Inertia routes)
const INVESTOR_GUIDE_URL = '/downloads/investor-guide.pdf';
const FINANCIAL_MODEL_URL = '/downloads/financial-model-and-assumptions.pdf';

const page = usePage();

const userName = computed(function () {
	const p: any = page.props;
	return (p && p.auth && p.auth.user && p.auth.user.name) ? p.auth.user.name : 'Guest';
});

const logoutUrl = '/logout';
</script>

<template>
	<Head title="Invest – LTD/EDN" />

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

				<div class="flex items-center gap-4">
					<Link
						:href="home()"
						class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/20 hover:ring-white/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
					>
						<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M21 3v18" />
						</svg>
						HOME
					</Link>
				</div>
			</nav>
		</header>

		<!-- Main -->
		<main class="relative z-10 mx-auto w-full max-w-7xl flex-1 px-6 pb-16 pt-10 lg:px-8 lg:pb-20 lg:pt-14">
			<div class="grid items-start gap-14 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-20">
				<!-- Left: copy -->
				<section class="mx-auto max-w-3xl text-center md:text-left">
					<h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl">
						WELCOME TO LTD/EDN
					</h1>

					<p class="mt-8 text-lg leading-8 text-white/85">
						LTD/EDN is a premium, artist-first creator-commerce platform enabling artists and designers to create and sell authenticated, limited-edition art, apparel, and homeware directly to their audiences.
					</p>

					<p class="mt-10 text-lg leading-8 text-white/75">
						The platform solves two structural problems in the creator economy...
					</p>

					<div class="mt-10 space-y-10">
						<p class="text-xl font-semibold leading-8 text-white">
							Artists lack scalable, high-margin routes to monetise luxury physical products without operational burden and cost.
						</p>

						<p class="text-xl font-semibold leading-8 text-white">
							Collectors increasingly demand authentic, high-quality limited editions rather than mass-produced merchandise.
						</p>
					</div>

					<div class="mt-12 space-y-10 text-lg leading-8 text-white/85">
						<p>
							LTD/EDN combines luxury-grade manufacturing, edition control, and innovative blockchain-backed QR code authentication, allowing artists to retain pricing power, customer ownership, and brand control. Each edition is produced on a cost-plus basis, ensuring predictable unit economics and strong gross margins (50%+), while limiting inventory risk through capped runs and made-to-order production.
						</p>

						<p>
							The business is led by a founding team with over 25 years’ experience in premium print, manufacturing, and artist-led editions, including prior execution of artist-led limited editions at scale. This expertise materially reduces execution risk compared to typical early-stage creator platforms.
						</p>

						<p>
							Launching with 10-15 established artists, scaling through repeatable monthly capsules to 200+ SKUs and £2-3m annual revenue by Year 3.
						</p>

						<p>
							The company is raising an initial £50,000 for 10% equity under SEIS, with Advance Assurance prepared via SeedLegals, to fund platform deployment, initial artist-led launches, and early revenue validation.
						</p>
					</div>

					<p class="mt-14 text-xl font-extrabold tracking-wide text-white">
						OUR DOWNLOADABLE INVESTOR PACKS ARE AVAILABLE ABOVE
					</p>
				</section>

				<!-- Right: downloads -->
				<aside class="mx-auto w-full max-w-sm lg:mx-0 lg:max-w-none">
					<div class="flex flex-col items-center space-y-16 lg:items-start">
						<a :href="INVESTOR_GUIDE_URL" class="group block">
							<div class="aspect-square w-56 bg-emerald-200/90 shadow-[0_0_0_1px_rgba(255,255,255,0.08)] flex items-center justify-center group-hover:bg-emerald-300/90 transition-colors">
								<svg class="h-16 w-16 text-black/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
									<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
								</svg>
							</div>
							
							<div class="mt-5 text-center text-sm font-extrabold tracking-wide text-white md:text-left">
								INVESTOR<br />GUIDE
							</div>
						</a>

						<a :href="FINANCIAL_MODEL_URL" class="group block">
							<div class="aspect-square w-56 bg-emerald-200/90 shadow-[0_0_0_1px_rgba(255,255,255,0.08)] flex items-center justify-center group-hover:bg-emerald-300/90 transition-colors">
								<svg class="h-16 w-16 text-black/80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
									<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
								</svg>
							</div>
							<div class="mt-5 text-center text-sm font-extrabold tracking-wide text-white md:text-left">
								FINANCIAL MODEL<br />
								&amp; ASSUMPTIONS<br />
								<span class="font-semibold text-white/80">SUPPORT DOCUMENT</span>
							</div>
						</a>
					</div>
				</aside>
			</div>
		</main>

		<!-- Footer -->
		<footer class="relative z-10">
			<div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-8 text-xs font-semibold tracking-wide text-white/80 lg:px-8">
				<p>© {{ new Date().getFullYear() }} LTD/EDN ALL RIGHTS RESERVED</p>
				<div class="flex items-center gap-5">
					<Link href="/privacy" class="hover:text-white">PRIVACY</Link>
					<span class="text-white/40">/</span>
					<Link href="/terms" class="hover:text-white">TERMS</Link>
				</div>
			</div>
		</footer>
	</div>
</template>
