<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Edition Verification</title>
	<style>body{font-family:Arial,sans-serif;padding:24px;max-width:820px;margin:0 auto} .k{color:#666}</style>
</head>
<body>
	<h1>Edition Verification</h1>
	<p><strong>{{ $edition->product->name }}</strong> by <strong>{{ $edition->product->artist->name }}</strong></p>
	<p>Edition: #{{ $edition->number }} of {{ $edition->product->edition_size }}</p>
	<p>Current owner: {{ $edition->owner?->name ?? 'Unclaimed' }}</p>
	<p>Status: {{ $edition->status->value }}</p>
	@if($edition->chainToken)
		<hr>
		<p class="k">Chain: {{ $edition->chainToken->chain }}</p>
		<p class="k">Contract: {{ $edition->chainToken->contract_address }}</p>
		<p class="k">Token ID: {{ $edition->chainToken->token_id }}</p>
		<p class="k">Mint TX: {{ $edition->chainToken->mint_tx_hash }}</p>
	@endif
</body>
</html>
