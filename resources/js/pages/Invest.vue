<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const LOGO_SVG = '/images/logo-sm.svg';
const DL_IMG = '/images/dl-img.jpg';

const INVESTOR_GUIDE_URL = '/downloads/Investor-Guide.pdf';

const page = usePage();

const userName = computed(function () {
	const p: any = page.props;
	return (p && p.auth && p.auth.user && p.auth.user.name) ? p.auth.user.name : 'Guest';
});

const guide = {
	url: INVESTOR_GUIDE_URL,
	label: 'INVESTOR GUIDE',
	subtitle: 'DOWNLOADABLE PDF',
	enabled: true,
};

const otherDownloads = [
	{
		url: '#',
		label: 'FULL FINANCIAL SUMMARY',
		subtitle: 'AVAILABLE SOON',
		enabled: false,
	},
	{
		url: '#',
		label: 'INVESTOR PITCHDECK',
		subtitle: 'AVAILABLE SOON',
		enabled: false,
	},
];
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

		<!-- Top nav
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
		-->

		<!-- Main -->
		<main class="relative z-10 mx-auto w-full max-w-7xl flex-1 px-6 pb-8 pt-10 lg:px-8 lg:pb-20 lg:pt-14">
			<!-- Guide: top on mobile, hidden on lg (shown in sidebar instead) -->
			<div class="mx-auto mb-8 mt-4 flex w-full max-w-sm justify-center lg:hidden">
				<a :href="guide.url" target="_blank" class="group block">
					<div class="relative flex aspect-square w-64 items-center justify-center overflow-hidden border-4 border-white/80">
						<img :src="DL_IMG" :alt="guide.label" class="absolute inset-0 h-full w-full object-cover" />
					</div>
					<div class="mt-3 flex items-center gap-2 text-xs font-bold tracking-wider text-white">
						<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
						</svg>
						<div>
							<div>{{ guide.label }}</div>
							<div class="font-normal text-white/60">{{ guide.subtitle }}</div>
						</div>
					</div>
				</a>
			</div>

			<div class="grid items-start gap-14 lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-20">
				<!-- Left: copy -->
				<section class="max-w-3xl font-mono text-sm leading-relaxed tracking-wide">
					<h1 class="font-sans text-3xl font-semibold tracking-tight text-white sm:text-4xl">
						WELCOME TO LTD/EDN&hellip;
					</h1>

					<p class="mt-8 text-white/85">
						LTD/EDN is a premium, artist-first creator-commerce platform enabling artists and designers to create and sell authenticated, limited-edition art, apparel, and homeware directly to their audiences.
					</p>

					<p class="mt-6 text-white/75">
						The platform solves two structural problems in the creator economy...
					</p>

					<ul class="mt-6 space-y-4 text-white/85">
						<li>
							&bull; <strong class="font-bold text-white">Artists lack scalable, high-margin routes to monetise luxury physical products without operational burden and cost.</strong>
						</li>
						<li>
							&bull; <strong class="font-bold text-white">Collectors increasingly demand authentic, high-quality limited editions rather than mass-produced merchandise.</strong>
						</li>
					</ul>

					<p class="mt-6 text-white/75">
						LTD/EDN combines luxury-grade manufacturing, edition control, and innovative blockchain-backed QR code authentication, allowing artists to retain pricing power, customer ownership, and brand control.
					</p>

					<p class="mt-6 text-white/75">
						Each edition is produced on a cost-plus basis, ensuring predictable unit economics and strong gross margins (50%+), while limiting inventory risk through capped runs and made-to-order production.
					</p>

					<p class="mt-6 text-white/75">
						The business is led by a founding team with over 25 years' experience in premium print, manufacturing, and artist-led editions, including prior execution of artist-led limited editions at scale. This expertise materially reduces execution risk compared to typical early-stage creator platforms.
					</p>

					<p class="mt-6 text-white/75">
						Launching with 10-15 established artists, scaling through repeatable monthly capsules to 200+ SKUs and £2-3m annual revenue by Year 3.
					</p>

					<p class="mt-6 text-white/75">
						The company is raising an initial £50,000 for 10% equity under SEIS, with Advance Assurance prepared via <a href="https://seedlegals.com/resources/seis/#seis-introduction" target="_blank" class="font-semibold text-white underline decoration-white/60 underline-offset-2 hover:decoration-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50">SeedLegals</a>, to fund platform deployment, initial artist-led launches, and early revenue validation.
					</p>

					<h2 class="mt-10 font-sans text-xl font-semibold uppercase tracking-wide text-white">
						OUR COMPREHENSIVE INVESTOR GUIDE IS AVAILABLE TO DOWNLOAD, ALONGSIDE FURTHER INVESTOR SUPPORT DOCUMENTS.
					</h2>

					<h2 class="mt-6 font-sans text-xl font-semibold uppercase tracking-wide text-white">
						IF YOU REQUIRE FURTHER INFORMATION OR PRINTED VERSIONS OF THESE DOCUMENTS PLEASE REACH OUT AT INVEST@LTDEDN.COM
					</h2>
				</section>

				<!-- Right: all downloads on lg, only other downloads on mobile -->
				<aside class="mx-auto w-full max-w-sm lg:mx-0 lg:max-w-none">
					<div class="flex flex-col items-center gap-6 lg:items-end">
						<!-- Guide: only visible on lg (mobile version is above) -->
						<a :href="guide.url" target="_blank" class="group hidden lg:block">
							<div class="relative flex aspect-square w-64 items-center justify-center overflow-hidden border-4 border-white/80">
								<img :src="DL_IMG" :alt="guide.label" class="absolute inset-0 h-full w-full object-cover" />
							</div>
							<div class="mt-3 flex items-center gap-2 text-xs font-bold tracking-wider text-white">
								<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
								</svg>
								<div>
									<div>{{ guide.label }}</div>
									<div class="font-normal text-white/60">{{ guide.subtitle }}</div>
								</div>
							</div>
						</a>

						<!-- Other downloads: always visible -->
						<component
							v-for="dl in otherDownloads"
							:key="dl.label"
							:is="dl.enabled ? 'a' : 'div'"
							v-bind="dl.enabled ? { href: dl.url, target: '_blank' } : {}"
							:class="['group block', { 'cursor-default opacity-50': !dl.enabled }]"
						>
							<div
								:class="[
									'relative flex aspect-square w-64 items-center justify-center overflow-hidden border-4 border-white/80',
									{ 'grayscale': !dl.enabled },
								]"
							>
								<img
									:src="DL_IMG"
									:alt="dl.label"
									class="absolute inset-0 h-full w-full object-cover"
								/>
							</div>
							<div class="mt-3 flex items-center gap-2 text-xs font-bold tracking-wider text-white">
								<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
								</svg>
								<div>
									<div>{{ dl.label }}</div>
									<div class="font-normal text-white/60">{{ dl.subtitle }}</div>
								</div>
							</div>
						</component>
					</div>
				</aside>
			</div>
		</main>

		<!-- Footer -->
		<footer class="relative z-10">
			<div class="mx-auto flex max-w-7xl items-center justify-center px-6 py-8 text-xs font-semibold tracking-wide text-white/80 lg:px-8 lg:justify-end">
				<p>&copy; {{ new Date().getFullYear() }} LTD/EDN</p>
			</div>
		</footer>
	</div>
</template>
