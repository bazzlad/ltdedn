<script setup lang="ts">
import { home } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const LOGO_SVG = '/images/logo-sm.svg';
const DL_IMG = '/images/dl-img.jpg';

const ARTIST_LOOKBOOK_URL = '/downloads/Artist-Handbook.pdf';
const ARTIST_CONTRACT_URL = '/downloads/Artist-Agreement.pdf';
const ARTIST_STORYTELLING_URL = '/downloads/Artist-Handbook.pdf';

const page = usePage();

const userName = computed(function () {
	const p: any = page.props;
	return (p && p.auth && p.auth.user && p.auth.user.name) ? p.auth.user.name : 'Guest';
});

const artistEmail = computed(function () {
	return ['artist', 'ltdedn.com'].join('@');
});

function openArtistEmail(): void {
	window.location.href = 'mai' + 'lto:' + artistEmail.value;
}

const lookbook = {
	url: ARTIST_LOOKBOOK_URL,
	label: 'ARTIST LOOK BOOK',
	subtitle: 'DOWNLOADABLE PDF',
	enabled: true,
};

const otherDownloads = [
	{
		url: ARTIST_CONTRACT_URL,
		label: 'ARTIST CONTRACT',
		subtitle: 'SUPPORT DOCUMENT',
		enabled: true,
	},
	{
		url: ARTIST_STORYTELLING_URL,
		label: 'ARTIST STORYTELLING',
		subtitle: 'AVAILABLE SOON',
		enabled: false,
	},
];
</script>

<template>
	<Head title="Artists – LTD/EDN" />

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
			<!-- Lookbook: top on mobile, hidden on lg (shown in sidebar instead) -->
			<div class="mx-auto mb-8 mt-4 flex w-full max-w-sm justify-center lg:hidden">
				<a :href="lookbook.url" target="_blank" class="group block">
					<div class="relative flex aspect-square w-64 items-center justify-center overflow-hidden border-4 border-white/80">
						<img :src="DL_IMG" :alt="lookbook.label" class="absolute inset-0 h-full w-full object-cover" />
					</div>
					<div class="mt-3 flex items-center gap-2 text-xs font-bold tracking-wider text-white">
						<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
						</svg>
						<div>
							<div>{{ lookbook.label }}</div>
							<div class="font-normal text-white/60">{{ lookbook.subtitle }}</div>
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
						In 2024, the intellectual property and brand ownership of This is a Limited Edition were restored to its original founders, following a legal resolution.
					</p>

					<p class="mt-6 text-white/75">
						Since its launch in 2007, the brand became known for producing high-quality, artist-led limited editions that connected creators with collectors around the world. Over the years, it collaborated with many of the most respected and loved artists working today, including <strong class="font-bold text-white">Audrey Kawasaki</strong>, <strong class="font-bold text-white">Dan Baldwin</strong>, <strong class="font-bold text-white">INSA</strong>, <strong class="font-bold text-white">James Jean</strong>, <strong class="font-bold text-white">Kozik</strong>, <strong class="font-bold text-white">Mab Graves</strong>, <strong class="font-bold text-white">Mode2</strong>, <strong class="font-bold text-white">Ron English</strong>, <strong class="font-bold text-white">Tara McPherson</strong>, <strong class="font-bold text-white">Tom Lewis</strong>, <strong class="font-bold text-white">tokidoki</strong>, and many more.
					</p>

					<p class="mt-8 text-base font-bold text-white">
						LTD/EDN is a new chapter.
					</p>

					<p class="mt-4 text-white/85">
						Built by the original founders, it carries forward the experience and values of the past while embracing a new era of tools, technologies, and opportunities for artists.
					</p>

					<p class="mt-6 text-white/75">
						Over the past decade, the art &amp; design world has undergone rapid &amp; sweeping change. Advances in AI, the rise and fall of NFT, the growth of blockchain, licensing, e-commerce, digital print, social media, print-on-demand &amp; personalisation have all disrupted how art is created, sold &amp; experienced.
					</p>

					<p class="mt-6 text-white/75">
						LTD/EDN is embracing this evolution, building a next-generation platform that combines the best of these technologies with a groundbreaking authentication system.
					</p>

					<p class="mt-6 text-white/75">
						Also today collectors aren't just buying products, they're connecting with brands and artists differently.
					</p>

					<p class="mt-6 text-white/75">
						LTD/EDN will help artists translate their own stories into physical form, turning products into chapters. By structuring releases as cohesive collections,
					</p>

					<p class="mt-8 text-base font-bold text-white">
						Curate. Elevate. Authenticate.
					</p>

					<h2 class="mt-10 font-sans text-xl font-semibold uppercase tracking-wide text-white">
						OUR COMPREHENSIVE ARTIST LOOK BOOK GUIDE IS AVAILABLE TO DOWNLOAD , ALONGSIDE ARTIST SUPPORT DOCUMENTS.
					</h2>

					<h2 class="mt-6 font-sans text-xl font-semibold uppercase tracking-wide text-white">
						IF YOU REQUIRE FURTHER INFORMATION OR PRINTED VERSIONS OF THESE DOCUMENTS PLEASE REACH OUT AT <a href="#" class="underline decoration-white/60 underline-offset-2 hover:decoration-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50" @click.prevent="openArtistEmail">{{ artistEmail.toUpperCase() }}</a>
					</h2>
				</section>

				<!-- Right: all downloads on lg, only other downloads on mobile -->
				<aside class="mx-auto w-full max-w-sm lg:mx-0 lg:max-w-none">
					<div class="flex flex-col items-center gap-6 lg:items-end">
						<!-- Lookbook: only visible on lg (mobile version is above) -->
						<a :href="lookbook.url" target="_blank" class="group hidden lg:block">
							<div class="relative flex aspect-square w-64 items-center justify-center overflow-hidden border-4 border-white/80">
								<img :src="DL_IMG" :alt="lookbook.label" class="absolute inset-0 h-full w-full object-cover" />
							</div>
							<div class="mt-3 flex items-center gap-2 text-xs font-bold tracking-wider text-white">
								<svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
								</svg>
								<div>
									<div>{{ lookbook.label }}</div>
									<div class="font-normal text-white/60">{{ lookbook.subtitle }}</div>
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
