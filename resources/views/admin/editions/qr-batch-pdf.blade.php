<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title>QR Codes</title>
	<style>
		@page { margin: 15mm; }
		body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; }
		.page { page-break-after: always; padding: 10mm 0; text-align: center; }
		.page:last-child { page-break-after: auto; }
		.qr-img { width: 140mm; height: 140mm; display: block; margin: 0 auto 8mm; }
		.title { font-weight: bold; margin-bottom: 2mm; }
		.sub { font-size: 10pt; color: #444; word-wrap: break-word; }
	</style>
</head>
<body>
@foreach($items as $item)
	<div class="page">
		<div class="title">{{ $item['label'] }}</div>
		<img class="qr-img" src="{{ $item['img'] }}" alt="QR">
		@if(!empty($item['sub']))
			<div class="sub">{{ $item['sub'] }}</div>
		@endif
	</div>
@endforeach
</body>
</html>