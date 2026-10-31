<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pencatatan
    |--------------------------------------------------------------------------
    | Setiap request di grup middleware `middleware_groups` dicatat satu baris (method, path, route, status, durasi,
    | user, sesi, IP). Penulisan dilakukan di terminate(), setelah respons terkirim ke browser.
    */
    'enabled' => (bool) env('VISITOR_COUNTER_ENABLED', true),

    /*
     * Saklar dari dashboard (tombol Nonaktifkan/Aktifkan) dan `php artisan visitor-counter:disable|enable`: file penanda,
     * ada = pencatatan mati. null = storage/framework/visitor-counter-{APP_ENV}-disabled.json. Lebih dari satu server
     * aplikasi: arahkan ke folder bersama.
     */
    'switch_path' => env('VISITOR_COUNTER_SWITCH_PATH'),

    /* null = koneksi default app */
    'connection' => env('VISITOR_COUNTER_DB_CONNECTION'),

    'table' => env('VISITOR_COUNTER_TABLE', 'visitors'),

    /*
     * Tabel dibuat bila belum ada dan kolom/index yang kurang ditambahkan, oleh migration paket maupun otomatis
     * sebelum insert pertama (sekali per versi skema, ditandai di cache). false = hanya lewat migrate /
     * `php artisan visitor-counter:sync-schema`.
     */
    'auto_schema' => (bool) env('VISITOR_COUNTER_AUTO_SCHEMA', true),

    /* Grup middleware yang otomatis ditambah RecordVisit. [] = pasang sendiri (\Bangsamu\VisitorCounter\Http\Middleware\RecordVisit). */
    'middleware_groups' => ['web'],

    /* Guard untuk user_id; null = guard default */
    'guard' => null,

    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],

    /* false = request AJAX/JSON (DataTables, select2, polling) tidak dicatat */
    'record_ajax' => (bool) env('VISITOR_COUNTER_RECORD_AJAX', true),

    /*
     * Refresh berulang: GET (termasuk AJAX) dengan alamat + query, status, dan user yang sama dari sesi yang sama dicatat
     * sekali per sekian detik (dihitung dari saat terakhir dicatat). POST/PUT/PATCH/DELETE selalu dicatat. Penanda
     * disimpan di session, tanpa query/cache tambahan. 0 = setiap request dicatat.
     */
    'repeat_window_seconds' => (int) env('VISITOR_COUNTER_REPEAT_WINDOW', 60),

    'except' => [
        /* Pola $request->is() */
        'paths' => ['_debugbar/*', 'telescope', 'telescope/*', 'horizon/*', 'livewire/*', 'up', 'favicon.ico', 'robots.txt'],

        /* Pola $request->routeIs(): pelaporan IP dan polling sesi (app bangsamu) */
        'routes' => ['visitor-counter.client-ip', 'session.status', 'session.keep-alive', 'session.client-ip', 'sanctum.csrf-cookie'],
    ],

    /* Parameter query yang tidak ikut disimpan (huruf besar/kecil diabaikan) */
    'hidden_query_keys' => ['password', 'password_confirmation', 'token', '_token', 'signature', 'secret', 'api_key', 'access_token'],

    /*
     * Baris lebih tua dari sekian hari dihapus `model:prune` terjadwal. 0 = simpan selamanya. Bawaan; bisa diubah dari
     * dashboard (Pengaturan pencatatan).
     */
    'retention_days' => (int) env('VISITOR_COUNTER_RETENTION_DAYS', 180),

    /*
     * Jadwal pencatatan bawaan; bisa diubah dari dashboard (disimpan di `settings_path`). Mode `users` (sudah login) dan
     * `guests` (belum login): always | outside_work_hours | never. Jam kerja = `work_days` (ISO, 1 = Senin) pukul
     * `work_start`–`work_end` (zona waktu app), bukan hari libur bila `holidays` = true (kalender dari binding
     * \Bangsamu\VisitorCounter\Contracts\HolidayCalendar milik app; bawaan tanpa hari libur).
     */
    'schedule' => [
        'users' => env('VISITOR_COUNTER_USERS', 'always'),
        'guests' => env('VISITOR_COUNTER_GUESTS', 'always'),
        'work_days' => [1, 2, 3, 4, 5],
        'work_start' => env('VISITOR_COUNTER_WORK_START', '08:00'),
        'work_end' => env('VISITOR_COUNTER_WORK_END', '17:00'),
        'holidays' => true,
    ],

    /* File pengaturan dari dashboard. null = storage/framework/visitor-counter-{APP_ENV}-settings.json */
    'settings_path' => env('VISITOR_COUNTER_SETTINGS_PATH'),

    'prune' => [
        'schedule' => true,
        'at' => '02:30',
    ],

    /*
    |--------------------------------------------------------------------------
    | IP klien
    |--------------------------------------------------------------------------
    | Kolom `ip` selalu IP yang dilihat server ($request->ip(), mengikuti TrustProxies). Kolom `client_ip`:
    | - auto    : IP server bila publik; bila private (server di belakang NAT/router) pakai IP yang dilaporkan browser
    | - server  : selalu IP server
    | - browser : selalu IP yang dilaporkan browser
    | IP dari browser berasal dari klien (bisa dipalsukan): `client_ip_source` = browser.
    */
    'client_ip' => [
        'mode' => env('VISITOR_COUNTER_CLIENT_IP', 'auto'),

        'session_key' => 'visitor_counter_client_ip',

        /* Key session lain berformat ['ip' => ..., 'at' => timestamp] yang juga dibaca (pelapor IP milik app) */
        'fallback_session_keys' => ['reported_client_ip'],

        /* Browser melapor ulang setelah sekian detik (IP bisa berganti, mis. pindah jaringan) */
        'refresh_seconds' => 1800,

        /* Dipanggil dari browser, harus mengembalikan JSON {"ip": "..."} */
        'lookup_url' => env('VISITOR_COUNTER_IP_LOOKUP_URL', 'https://api.ipify.org?format=json'),

        /* Sisipkan skrip pelapor otomatis sebelum </body> halaman HTML; false = pasang <x-visitor-counter::client-ip /> sendiri */
        'inject_script' => (bool) env('VISITOR_COUNTER_INJECT_SCRIPT', true),

        'report_route' => [
            'enabled' => true,
            'path' => 'visitor-counter/client-ip',
            'middleware' => ['web', 'throttle:10,1'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    | Gate `viewVisitorCounter`: bawaan paket = user yang punya salah satu ability `abilities`. Definisikan ulang
    | Gate::define('viewVisitorCounter', ...) di app untuk aturan lain.
    */
    'dashboard' => [
        'enabled' => true,
        'path' => env('VISITOR_COUNTER_PATH', 'visitor-counter'),
        'middleware' => ['web', 'auth'],
        'gate' => 'viewVisitorCounter',
        'abilities' => ['is_root'],

        /* auto = tabler (app.themes _tabler) → adminlte::page → layout paket. Atau nama view layout. */
        'layout' => env('VISITOR_COUNTER_LAYOUT', 'auto'),

        /* Nama section layout; [] = sesuai layout auto. Contoh: ['title' => 'title', 'header' => null, 'content' => 'content'] */
        'sections' => [],

        'per_page' => 50,
        'max_range_days' => 92,

        /* null = auth.providers.users.model */
        'user_model' => null,
        'user_label_attributes' => ['name', 'email'],
    ],
];
