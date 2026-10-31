@php
    $statusClass = fn (?int $status): string => match (true) {
        $status === null => 'text-bg-secondary',
        $status >= 500 => 'text-bg-danger',
        $status >= 400 => 'text-bg-warning',
        $status >= 300 => 'text-bg-info',
        default => 'text-bg-success',
    };
    $userLabel = fn ($userId): string => $userId === null ? 'Tamu' : ($userLabels[(int) $userId] ?? '#'.$userId);
    $filterUrl = fn (array $changes): string => request()->fullUrlWithQuery($changes + ['page' => null]);
@endphp

<div class="container-fluid" id="visitorCounterDashboard">
    @if (session('visitor_counter_status'))
        <div class="alert alert-success py-2">{{ session('visitor_counter_status') }}</div>
    @endif
    @if (session('visitor_counter_error'))
        <div class="alert alert-danger py-2">{{ session('visitor_counter_error') }}</div>
    @endif

    <div class="card shadow-sm mb-3" id="visitorCounterSwitch">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            @if (! $recording['config_enabled'])
                <span class="badge text-bg-secondary">Nonaktif</span>
                <span class="small text-secondary">Pencatatan dimatikan lewat konfigurasi (<code>VISITOR_COUNTER_ENABLED=false</code>); nyalakan dari .env.</span>
            @elseif ($recording['enabled'])
                <span class="badge text-bg-success">Pencatatan aktif</span>
                <span class="small text-secondary">Setiap request disimpan ke database.</span>
                <form method="POST" action="{{ route('visitor-counter.toggle') }}" class="ms-auto"
                      onsubmit="return confirm('Nonaktifkan pencatatan? Request tidak disimpan sampai diaktifkan lagi.');">
                    @csrf
                    <input type="hidden" name="enabled" value="0">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Nonaktifkan pencatatan</button>
                </form>
            @else
                @php
                    $disabledAt = $recording['disabled']['at'] ?? null;
                    $disabledBy = $recording['disabled']['by'] ?? null;
                    $disabledNote = trim(
                        ($disabledAt ? 'sejak '.\Illuminate\Support\Carbon::parse($disabledAt)->format('d-m-Y H:i') : '')
                        .($disabledBy ? ' oleh '.$disabledBy : '')
                    );
                @endphp
                <span class="badge text-bg-warning">Pencatatan nonaktif</span>
                <span class="small text-secondary">
                    Tidak ada request yang disimpan ke database{{ $disabledNote !== '' ? ' '.$disabledNote : '' }}. Data lama tetap bisa dilihat.
                </span>
                <form method="POST" action="{{ route('visitor-counter.toggle') }}" class="ms-auto">
                    @csrf
                    <input type="hidden" name="enabled" value="1">
                    <button type="submit" class="btn btn-success btn-sm">Aktifkan pencatatan</button>
                </form>
            @endif
        </div>
    </div>

    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="vcFrom">Dari</label>
                    <input type="date" class="form-control form-control-sm" id="vcFrom" name="from" value="{{ $filters->from->toDateString() }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="vcTo">Sampai</label>
                    <input type="date" class="form-control form-control-sm" id="vcTo" name="to" value="{{ $filters->to->toDateString() }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small mb-1" for="vcUser">User</label>
                    <input type="text" class="form-control form-control-sm" id="vcUser" name="user" value="{{ $filters->user }}" placeholder="nama, email, atau id">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small mb-1" for="vcTarget">Halaman / route</label>
                    <input type="text" class="form-control form-control-sm" id="vcTarget" name="target" value="{{ $filters->target }}" placeholder="path atau nama route">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="vcIp">IP</label>
                    <input type="text" class="form-control form-control-sm font-monospace" id="vcIp" name="ip" value="{{ $filters->ip }}" placeholder="IP server / klien">
                </div>
                <div class="col-3 col-md-1">
                    <label class="form-label small mb-1" for="vcMethod">Method</label>
                    <select class="form-select form-select-sm" id="vcMethod" name="method">
                        <option value="">Semua</option>
                        @foreach (\Bangsamu\VisitorCounter\Support\VisitFilters::methods() as $method)
                            <option value="{{ $method }}" @selected($filters->method === $method)>{{ $method }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-3 col-md-1">
                    <label class="form-label small mb-1" for="vcStatus">Status</label>
                    <select class="form-select form-select-sm" id="vcStatus" name="status">
                        <option value="">Semua</option>
                        @foreach (\Bangsamu\VisitorCounter\Support\VisitFilters::statuses() as $status)
                            <option value="{{ $status }}" @selected($filters->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
                <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                <span class="small text-secondary align-self-center ms-auto">
                    Rentang maksimal {{ (int) config('visitor-counter.dashboard.max_range_days', 92) }} hari
                </span>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Request', number_format((int) ($summary->hits ?? 0))],
            ['User unik', number_format((int) ($summary->users ?? 0))],
            ['IP unik', number_format((int) ($summary->ips ?? 0))],
            ['Rata-rata durasi', $summary->avg_ms === null ? '–' : number_format((float) $summary->avg_ms).' ms'],
        ] as [$label, $value])
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-secondary">{{ $label }}</div>
                        <div class="fs-4 fw-semibold">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Halaman paling sering dibuka</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            @forelse ($topTargets as $row)
                                <tr>
                                    <td class="text-break"><a href="{{ $filterUrl(['target' => $row->target]) }}">{{ $row->target ?? '–' }}</a></td>
                                    <td class="text-end text-nowrap">{{ number_format((int) $row->hits) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-secondary">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">User paling aktif</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            @forelse ($topUsers as $row)
                                <tr>
                                    <td class="text-break"><a href="{{ $filterUrl(['user' => (string) $row->user_id]) }}">{{ $userLabel($row->user_id) }}</a></td>
                                    <td class="text-nowrap small text-secondary">terakhir {{ \Illuminate\Support\Carbon::parse($row->last_seen)->format('d-m-Y H:i') }}</td>
                                    <td class="text-end text-nowrap">{{ number_format((int) $row->hits) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-secondary">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Riwayat request</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-nowrap">Waktu</th>
                        <th>User</th>
                        <th>Request</th>
                        <th class="text-nowrap">Status</th>
                        <th class="text-nowrap text-end">Durasi</th>
                        <th class="text-nowrap">IP server</th>
                        <th class="text-nowrap">IP klien</th>
                        <th>Browser</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($visits as $visit)
                        <tr>
                            <td class="text-nowrap small">{{ $visit->created_at?->format('d-m-Y H:i:s') }}</td>
                            <td class="small text-break">
                                @if ($visit->user_id === null)
                                    <span class="text-secondary">Tamu</span>
                                @else
                                    <a href="{{ $filterUrl(['user' => (string) $visit->user_id]) }}">{{ $userLabel($visit->user_id) }}</a>
                                @endif
                            </td>
                            <td class="small text-break">
                                <span class="badge text-bg-light border">{{ $visit->method }}</span>
                                @if ($visit->is_ajax)
                                    <span class="badge text-bg-light border" title="AJAX / JSON">ajax</span>
                                @endif
                                <span class="font-monospace">/{{ ltrim((string) $visit->path, '/') }}</span>
                                @if ($visit->query_string)
                                    <span class="font-monospace text-secondary">?{{ $visit->query_string }}</span>
                                @endif
                                @if ($visit->route_name)
                                    <div class="text-secondary">{{ $visit->route_name }}</div>
                                @endif
                            </td>
                            <td><span class="badge {{ $statusClass($visit->status) }}">{{ $visit->status ?? '–' }}</span></td>
                            <td class="text-end text-nowrap small">{{ $visit->duration_ms === null ? '–' : number_format($visit->duration_ms).' ms' }}</td>
                            <td class="font-monospace small text-nowrap">{{ $visit->ip ?? '–' }}</td>
                            <td class="font-monospace small text-nowrap">
                                @if ($visit->client_ip)
                                    {{ $visit->client_ip }}
                                    <span class="badge {{ $visit->client_ip_source === 'browser' ? 'text-bg-warning' : 'text-bg-success' }}">{{ $visit->client_ip_source }}</span>
                                @else
                                    <span class="text-secondary">–</span>
                                @endif
                            </td>
                            <td class="small text-secondary text-truncate" style="max-width: 16rem;" title="{{ $visit->user_agent }}">{{ $visit->user_agent }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-secondary">Tidak ada request pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($visits->hasPages())
            <div class="card-footer">
                {{ $visits->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
