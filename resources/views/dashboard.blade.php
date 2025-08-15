@extends((config('app.themes') == '_tabler' ? 'master::layouts.tabler' : 'adminlte::page'))

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
            labels: {!! json_encode($stats->pluck('visit_date')->reverse()) !!},
            datasets: [{
                label: 'Visitors per Day',
                data: {!! json_encode($stats->pluck('total')->reverse()) !!},
                backgroundColor: 'rgba(54, 162, 235, 0.5)'
            }]
        }
    });
</script>
@endpush
