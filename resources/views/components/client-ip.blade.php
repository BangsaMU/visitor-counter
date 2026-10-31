{{-- Pelapor IP dari browser untuk layout yang mematikan client_ip.inject_script: <x-visitor-counter::client-ip /> --}}
{!! app(\Bangsamu\VisitorCounter\Support\ClientIpResolver::class)->reporterScript(request()) !!}
