<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Certificate of Ownership</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; padding: 28px; color: #111; }
		h1 { margin: 0 0 8px; }
		.small { color: #555; font-size: 12px; }
		.box { border: 1px solid #ddd; padding: 16px; margin-top: 16px; }
	</style>
</head>
<body>
	<h1>Certificate of Ownership</h1>
	<div class="small">LTD/EDN Dynamic Certificate</div>

	<div class="box">
		<p><strong>Owner:</strong> {{ $owner->name }}</p>
		<p><strong>Product:</strong> {{ $edition->product->name }}</p>
		<p><strong>Artist:</strong> {{ $edition->product->artist->name }}</p>
		<p><strong>Edition:</strong> #{{ $edition->number }} of {{ $edition->product->edition_size }}</p>
		<p><strong>Minted:</strong> {{ optional($edition->chainToken?->minted_at)->toDateTimeString() ?? 'Not minted' }}</p>
		<p><strong>Contract:</strong> {{ $edition->chainToken?->contract_address ?? 'N/A' }}</p>
		<p><strong>Token ID:</strong> {{ $edition->chainToken?->token_id ?? 'N/A' }}</p>
		<p><strong>Mint TX:</strong> {{ $edition->chainToken?->mint_tx_hash ?? 'N/A' }}</p>
	</div>

	<div class="box">
		<p><strong>Verify URL:</strong> {{ $verifyUrl }}</p>
		<img src="data:image/png;base64,{{ $qrBase64 }}" width="180" height="180" alt="Verification QR" />
	</div>
</body>
</html>
