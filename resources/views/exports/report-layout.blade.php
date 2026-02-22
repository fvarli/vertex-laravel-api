<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        tr:nth-child(even) { background: #fafafa; }
        .totals { margin-bottom: 16px; }
        .totals span { display: inline-block; margin-right: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        Generated: {{ now()->format('Y-m-d H:i') }}
        @if(isset($report['filters']))
            | Period: {{ $report['filters']['date_from'] }} — {{ $report['filters']['date_to'] }}
        @elseif(isset($report['period']))
            | Period: {{ $report['period']['from'] }} — {{ $report['period']['to'] }}
        @endif
    </div>
    @yield('content')
</body>
</html>
