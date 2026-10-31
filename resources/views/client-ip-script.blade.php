<script @if ($nonce) nonce="{{ $nonce }}" @endif>
    /* visitor-counter: IP publik menurut browser, dipakai bila server hanya melihat IP private (NAT/router).
       Gagal (layanan IP diblokir/offline) diabaikan tanpa mengganggu halaman. */
    (function () {
        if (!window.fetch) {
            return;
        }
        fetch(@json($lookupUrl), { cache: 'no-store' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !data.ip) {
                    return;
                }
                return fetch(@json($reportUrl), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @json($token)
                    },
                    body: JSON.stringify({ ip: data.ip })
                });
            })
            .catch(function () {});
    })();
</script>
