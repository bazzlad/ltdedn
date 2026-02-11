<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import { login, register } from '@/routes';
import { claim as qrClaim, show as qrShow, transfer as qrTransfer } from '@/routes/qr';
import { cancel as transfersCancel } from '@/routes/transfers';
import { Form, Link } from '@inertiajs/vue3';

interface Props {
	edition: {
		id: number;
		number: number;
		status: string;
		qr_code: string;
		created_at: string;
		product: {
			id: number;
			name: string;
			slug: string;
			description: string | null;
			cover_image: string | null;
			edition_size: number;
			artist: {
				id: number;
				name: string;
			};
		};
		owner: null;
	};
	isClaimed: boolean;
	isOwnedByCurrentUser: boolean;
	canClaim: boolean;
	activeTransfer?: {
		token: string;
		recipient: {
			name: string;
		};
	} | null;
}

const props = defineProps<Props>();

const handleCancelTransfer = (submit: (e?: Event) => void) => {
	if (confirm('Are you sure you want to cancel this transfer? The recipient will be notified.')) {
		submit();
	}
};

const editionNumber = String(props.edition.number).padStart(3, '0');
const editionSize = String(props.edition.product.edition_size ?? '???').padStart(3, '0');

const statusLabel = props.edition.status.replace('_', ' ').replace(/\b\w/g, (l: string) => l.toUpperCase());
</script>

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

		<!-- Content -->
		<main class="relative z-10 flex flex-1 items-center justify-center px-4 py-8 sm:py-12">
			<div class="w-full max-w-md">
				<!-- Product Image -->
				<div v-if="edition.product.cover_image" class="border-4 border-white/80 bg-neutral-900">
					<img
						:src="edition.product.cover_image"
						:alt="edition.product.name"
						class="h-auto w-full object-contain"
					/>
				</div>
				<div v-else class="flex aspect-square items-center justify-center border-4 border-white/80 bg-neutral-900">
					<div class="text-center text-neutral-600">
						<svg class="mx-auto mb-2 h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
							<path d="M4 4h16v12H4V4zm2 2v8h12V6H6zm2 2h8v4H8V8z" />
						</svg>
						<p class="text-sm font-medium">No image available</p>
					</div>
				</div>

				<!-- Title & Artist -->
				<div class="mt-4 space-y-1 sm:mt-5">
					<p class="text-sm tracking-widest">
						<span class="text-neutral-500">TITLE</span>
						<span class="mx-2 text-neutral-600">/</span>
						<span class="font-bold text-white">{{ edition.product.name.toUpperCase() }}</span>
					</p>
					<p class="text-sm tracking-widest">
						<span class="text-neutral-500">ARTIST</span>
						<span class="mx-1 text-neutral-600">/</span>
						<span class="font-bold text-white">{{ edition.product.artist.name.toUpperCase() }}</span>
					</p>
				</div>

				<!-- Edition Details Bar -->
				<div class="mt-4 grid grid-cols-2 gap-4 rounded-sm bg-neutral-900/80 p-4 ring-1 ring-white/10">
					<div>
						<p class="mb-1 text-xs font-semibold tracking-wider text-neutral-500">EDITION NUMBER</p>
						<p class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl">
							{{ editionNumber }} / {{ editionSize }}
						</p>
					</div>
					<div>
						<p class="mb-1 text-xs font-semibold tracking-wider text-neutral-500">STATUS</p>
						<span
							class="mt-1 inline-flex items-center rounded-sm px-3 py-1 text-xs font-bold tracking-wider"
							:class="{
								'bg-green-600 text-white': edition.status === 'available',
								'bg-blue-600 text-white': edition.status === 'sold',
								'bg-yellow-600 text-white': edition.status === 'pending_transfer',
								'bg-neutral-600 text-white': !['available', 'sold', 'pending_transfer'].includes(edition.status),
							}"
						>
							{{ statusLabel.toUpperCase() }}
						</span>
					</div>
				</div>

				<!-- Ownership Status -->
				<div
					v-if="isClaimed"
					class="mt-4 rounded-sm border border-blue-500/30 bg-blue-500/10 p-3 ring-1 ring-blue-500/20"
				>
					<div class="flex items-center gap-2">
						<svg class="h-4 w-4 shrink-0 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
							<path
								fill-rule="evenodd"
								d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
								clip-rule="evenodd"
							/>
						</svg>
						<p class="text-xs font-bold tracking-wider text-blue-300">
							{{ isOwnedByCurrentUser ? 'YOU OWN THIS EDITION' : 'ALREADY CLAIMED' }}
						</p>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="mt-4 space-y-4">
					<!-- Claim Button -->
					<div v-if="canClaim">
						<Form
							v-if="$page.props.auth.user"
							:action="qrClaim(edition.qr_code).url"
							method="post"
							class="w-full"
							#default="{ processing }"
						>
							<button
								type="submit"
								:disabled="processing"
								class="flex h-14 w-full items-center justify-center gap-2 border border-white/20 bg-neutral-900/80 text-base font-extrabold tracking-wider text-white transition-all hover:bg-white hover:text-black focus:outline-none focus-visible:ring-2 focus-visible:ring-white active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 sm:h-16 sm:text-lg"
							>
								<svg v-if="processing" class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								{{ processing ? 'CLAIMING...' : 'CLAIM THIS EDITION' }}
							</button>
						</Form>

						<div v-else class="text-center">
							<div class="mb-4 rounded-sm border border-yellow-500/30 bg-yellow-500/10 p-3 ring-1 ring-yellow-500/20">
								<p class="text-sm font-semibold text-yellow-200">
									You need to be logged in to claim this edition
								</p>
							</div>
							<div class="flex flex-col gap-2.5 sm:flex-row sm:gap-3">
								<Link
									:href="login({ query: { intended: qrShow(edition.qr_code).url } }).url"
									class="inline-flex flex-1 items-center justify-center border border-white bg-white px-4 py-3 text-sm font-bold tracking-wider text-black transition-all hover:bg-neutral-100 active:scale-[0.98] sm:px-6 sm:text-base"
								>
									LOG IN TO CLAIM
								</Link>
								<Link
									:href="register({ query: { intended: qrShow(edition.qr_code).url } }).url"
									class="inline-flex flex-1 items-center justify-center border border-white/30 px-4 py-3 text-sm font-bold tracking-wider text-white transition-all hover:bg-white/10 hover:border-white/50 active:scale-[0.98] sm:px-6 sm:text-base"
								>
									SIGN UP
								</Link>
							</div>
						</div>
					</div>

					<!-- Pending Transfer State -->
					<div v-if="isOwnedByCurrentUser && activeTransfer" class="rounded-sm border border-yellow-500/30 bg-yellow-500/10 p-4 ring-1 ring-yellow-500/20">
						<div class="mb-3 text-center">
							<div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-yellow-500/20 text-yellow-400">
								<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
								</svg>
							</div>
							<h3 class="text-base font-bold text-yellow-200">Transfer Pending</h3>
							<p class="mt-0.5 text-xs text-yellow-200/80">
								Waiting for {{ activeTransfer.recipient.name }} to accept.
							</p>
						</div>
						<Form
							:action="transfersCancel(activeTransfer.token).url"
							method="post"
							#default="{ processing, submit }"
						>
							<Button
								type="button"
								variant="destructive"
								class="w-full justify-center"
								:disabled="processing"
								@click="() => handleCancelTransfer(submit)"
							>
								{{ processing ? 'Cancelling...' : 'Cancel Transfer' }}
							</Button>
						</Form>
					</div>

					<!-- Transfer Button (for owners) -->
					<div v-else-if="isOwnedByCurrentUser">
						<details class="overflow-hidden rounded-sm border border-white/10 bg-neutral-900/50 ring-1 ring-white/5">
							<summary
								class="cursor-pointer p-3 text-sm font-semibold tracking-wider text-neutral-400 transition-colors hover:bg-white/5 hover:text-white"
							>
								TRANSFER EDITION
							</summary>
							<div class="border-t border-white/10 p-3">
								<Form
									:action="qrTransfer(edition.qr_code).url"
									method="post"
									class="space-y-3"
									#default="{ processing }"
								>
									<div>
										<Label for="recipient_email" class="text-xs text-neutral-400">Recipient's Email</Label>
										<Input
											id="recipient_email"
											name="recipient_email"
											type="email"
											placeholder="Enter email address"
											required
											class="mt-1.5"
										/>
										<p class="mt-1.5 text-xs text-neutral-500">
											Recipient must have an existing account.
										</p>
									</div>
									<Button
										type="submit"
										variant="outline"
										class="w-full justify-center text-sm font-semibold"
										:disabled="processing"
									>
										<span v-if="processing" class="mr-2">
											<svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
												<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
												<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
											</svg>
										</span>
										{{ processing ? 'Sending Request...' : 'Transfer Ownership' }}
									</Button>
								</Form>
							</div>
						</details>
					</div>
				</div>
			</div>
		</main>

		<!-- Footer -->
		<footer class="relative z-10">
			<div class="mx-auto flex max-w-7xl items-center justify-center px-6 py-8 text-xs font-semibold tracking-wide text-white/80 lg:px-8">
				<p>&copy; {{ new Date().getFullYear() }} LTD/EDN</p>
			</div>
		</footer>
	</div>
</template>
