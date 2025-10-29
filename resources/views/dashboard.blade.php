@php
    if (config('app.themes') == '_tabler') {
        // Cek apakah view "layouts.tabler" ada
        $themeLayout = view()->exists('layouts.tabler')
            ? 'layouts.tabler'
            : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }
@endphp
@extends($themeLayout)

@section('title', 'Visitor Dashboard')

@section('content')
<div class="container">
    <h1>Visitor Dashboard</h1>
    <p>Total Visitors: <strong>{{ $totalVisitors }}</strong></p>
    <p>Unique Visitors: <strong>{{ $uniqueVisitors }}</strong></p>

    <canvas id="visitorChart" height="100"></canvas>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('visitorChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($stats->pluck('visit_date')->reverse()->values()) !!},
            datasets: [{
                label: 'Visitors per Day',
                data: {!! json_encode($stats->pluck('total')->reverse()->values()) !!},
                backgroundColor: 'rgba(54, 162, 235, 0.5)'
            }]
        }
    });
</script>

@endpush
