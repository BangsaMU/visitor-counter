# bangsamu/visitor-counter

Mencatat **semua request web** aplikasi Laravel: siapa (user/tamu, sesi) membuka apa (method, path, query, nama route),
kapan, hasilnya (status), berapa lama (durasi), dan dari IP mana. Pelengkap activity log (perubahan data): paket ini
mencatat jejak navigasi, termasuk halaman yang hanya dibaca.

| Versi | Laravel | PHP |
|---|---|---|
| `^1.0` | 13 | 8.3+ |
| `0.0.x` | 8–12 | 8.0+ (versi lama, tidak dikembangkan lagi) |

App di bawah Laravel 13 tetap memakai `0.0.x`; composer menolak memasang `1.x` di sana.

## Pasang

```bash
composer require bangsamu/visitor-counter:^1.0
```

Selesai. Tanpa langkah lain:

- Middleware `RecordVisit` masuk ke grup `web`. Baris ditulis di `terminate()`, setelah respons terkirim ke browser.
- Tabel `visitors` **dibuat otomatis bila belum ada**. Bila sudah ada (buatan app atau `0.0.x`), hanya kolom/index yang
  kurang yang ditambahkan, tanpa error dan tanpa menyentuh data lama. Ini dijalankan oleh `php artisan migrate`
  (migration paket dimuat otomatis) dan, bila migrate belum sempat dijalankan, sekali sebelum insert pertama (hasilnya
  ditandai di cache per versi skema, jadi tidak ada cek skema di setiap request). Manual:
  `php artisan visitor-counter:sync-schema`.
- Dashboard di `/visitor-counter` (route `visitor-counter.index`) dengan filter tanggal, user, halaman/route, IP,
  method, dan status.
- Baris lebih tua dari 180 hari dihapus `model:prune` setiap hari pukul 02:30 (butuh scheduler berjalan).

## IP klien (server di belakang NAT)

Kolom `ip` selalu IP yang dilihat server (`$request->ip()`, mengikuti TrustProxies). Bila server hanya melihat IP
private (router/NAT tanpa header `X-Forwarded-For`), IP asli diambil dari browser:

1. Halaman HTML yang dibuka disisipi skrip kecil sebelum `</body>` (hanya selama IP belum diketahui atau sudah lebih
   dari 30 menit).
2. Browser bertanya IP publiknya ke `https://api.ipify.org?format=json`, lalu mengirimnya ke
   `POST /visitor-counter/client-ip` (route `visitor-counter.client-ip`, CSRF, throttle 10/menit). Nilainya disimpan
   di session, tidak ada tulis ke database.
3. Request berikutnya mencatat `client_ip` = IP itu dengan `client_ip_source` = `browser`.

IP dari browser **tidak terverifikasi** (bisa dipalsukan klien): pakai sebagai petunjuk, bukan bukti. User di satu LAN
kantor akan terlihat dengan IP publik kantor yang sama; pembeda orangnya adalah `user_id` dan `session_hash`.

Bila app sudah punya pelapor IP sendiri yang menyimpan `['ip' => ..., 'at' => timestamp]` di session, cantumkan key-nya
di `client_ip.fallback_session_keys` (bawaan: `reported_client_ip`). Layout yang tidak ingin disisipi otomatis: set
`VISITOR_COUNTER_INJECT_SCRIPT=false` lalu pasang `<x-visitor-counter::client-ip />` sebelum `</body>`.

## Akses dashboard

Gate `viewVisitorCounter`. Bawaan paket: user yang punya salah satu ability di `dashboard.abilities` (bawaan `is_root`;
`Gate::before` milik app, mis. untuk ROOT, tetap berlaku). Aturan lain cukup didefinisikan di app:

```php
Gate::define('viewVisitorCounter', fn (User $user) => $user->can('read_report_audit'));
```

Layout `auto`: tema tabler (`app.themes = _tabler`) → `adminlte::page` → layout paket (Bootstrap 5). Tampilan bisa
diganti dengan `php artisan vendor:publish --tag=visitor-counter-views`.

## Konfigurasi `.env` (semua opsional)

| Variabel | Bawaan | Arti |
|---|---|---|
| `VISITOR_COUNTER_ENABLED` | `true` | Matikan pencatatan |
| `VISITOR_COUNTER_RETENTION_DAYS` | `180` | Umur data; `0` = simpan selamanya |
| `VISITOR_COUNTER_RECORD_AJAX` | `true` | `false` = request AJAX/JSON tidak dicatat |
| `VISITOR_COUNTER_CLIENT_IP` | `auto` | `auto` / `server` / `browser` |
| `VISITOR_COUNTER_INJECT_SCRIPT` | `true` | Sisipkan skrip pelapor IP otomatis |
| `VISITOR_COUNTER_IP_LOOKUP_URL` | ipify | Layanan IP publik untuk browser (JSON `{"ip": ...}`) |
| `VISITOR_COUNTER_PATH` | `visitor-counter` | Path dashboard |
| `VISITOR_COUNTER_LAYOUT` | `auto` | Nama view layout dashboard |
| `VISITOR_COUNTER_TABLE` | `visitors` | Nama tabel |
| `VISITOR_COUNTER_DB_CONNECTION` | default | Koneksi database lain untuk log |
| `VISITOR_COUNTER_AUTO_SCHEMA` | `true` | `false` = skema hanya lewat migrate / `sync-schema` |

Pengecualian path/route, method, parameter query yang disembunyikan (`password`, `token`, ...), gate, dan lainnya:
`php artisan vendor:publish --tag=visitor-counter-config`. Bawaan sudah melewatkan polling sesi app bangsamu
(`session.status`, `session.keep-alive`, `session.client-ip`), Telescope, Debugbar, dan `robots.txt`.

## Angka ringkas

```php
use Bangsamu\VisitorCounter\Facades\VisitorCounter;

VisitorCounter::total();        // semua request
VisitorCounter::today();        // request hari ini
VisitorCounter::monthly();      // request bulan ini
VisitorCounter::unique();       // IP unik (IP klien, atau IP server bila kosong)
VisitorCounter::uniqueToday();
VisitorCounter::daily($from, $to); // per hari: hits, unique_ips, unique_users
```

JSON untuk widget: `GET /visitor-counter/stats?from=Y-m-d&to=Y-m-d` dan `GET /visitor-counter/today` (gate sama).

## Performa

Satu `INSERT` per request, dijalankan setelah respons terkirim (PHP-FPM), jadi user tidak menunggu. Tidak ada panggilan
HTTP dari server (IP publik diminta browser) dan tidak ada cek skema per request. Volume perkiraan: 100 user aktif ×
300 request/hari ≈ 30 ribu baris/hari; retensi 180 hari menjaga tabel tetap terbatas. Index: `created_at`,
`(user_id, created_at)`, `(visit_date, ip)`.

## Upgrade dari 0.0.x

- Butuh Laravel 13 dan PHP 8.3.
- Middleware `CountVisitor` / `RecordVisitor` diganti `RecordVisit` (otomatis di grup `web`, bukan middleware global).
- Tidak ada lagi pengambilan IP publik **server** dari Cloudflare/ipify: `client_ip` = IP user (server publik atau
  laporan browser), kosong bila belum diketahui.
- Mode `unique_daily` dan jeda 10 menit per path dihapus: setiap request dicatat.
- Config dipindah dari key `Visitor-counter` ke `visitor-counter`.
- Dashboard pindah ke `/visitor-counter` dan butuh gate `viewVisitorCounter` (dulu cukup login).
- Tabel lama otomatis ditambah kolom `session_hash`, `method`, `query_string`, `route_name`, `status`, `duration_ms`,
  `is_ajax`, `client_ip`, `client_ip_source` dan index baru; data lama tetap.

## Test

```bash
composer install
vendor/bin/phpunit
```
