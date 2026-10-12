@php
    $logoUrl = \App\Models\BrandingImage::url(\App\Enums\BrandingImageKey::Logo);
    $scope = collect([
        $offering ? $offering->scholarshipProgram->name.' · '.$offering->displayName() : $program?->name,
        $college?->name,
        $search !== '' ? '“'.$search.'”' : null,
    ])->filter()->implode(' · ');
    $mono = ['Sequence No.', 'Year', 'Amount'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SUC-Levelling report</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700&family=Public+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])

    {{-- A zero page margin keeps the browser from adding its own header and footer (URL, page title, date); the body padding stands in for the margin. --}}
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { padding: 12mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
    </style>
</head>
<body class="bg-white font-body text-ink antialiased">
    <header class="flex items-center gap-4 border-b border-line pb-3">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="OSDW logo" class="size-12 shrink-0 object-contain">
        @endif
        <div class="min-w-0 flex-1">
            <p class="font-body text-[11px] text-slate">Office of Student Development and Welfare</p>
            <h1 class="font-display text-[18px] font-600 text-ink">SUC-Levelling report</h1>
            <p class="font-body text-[12px] text-slate">{{ $scope !== '' ? $scope : 'All scholarship grantees' }}</p>
        </div>
        <div class="shrink-0 text-right font-body text-[11px] text-slate">
            <p>Printed {{ now()->format('F j, Y g:i A') }}</p>
            <p><span class="font-mono text-ink">{{ $rows->count() }}</span> {{ str('grantee')->plural($rows->count()) }}</p>
        </div>
    </header>

    <table class="mt-3 w-full border-collapse font-body text-[10px]">
        <thead>
            <tr>
                @foreach (array_keys($rows->first() ?? []) as $header)
                    <th class="border border-line bg-paper px-1.5 py-1 text-left font-600 text-ink">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $header => $value)
                        <td @class([
                            'border border-line px-1.5 py-0.5 align-top text-ink',
                            'font-mono' => in_array($header, $mono, true),
                            'text-right whitespace-nowrap' => $header === 'Amount',
                        ])>{{ $header === 'Amount' && $value !== null ? '₱'.number_format($value, 2) : $value }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
