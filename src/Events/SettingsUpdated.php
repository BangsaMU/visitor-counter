<?php

namespace Bangsamu\VisitorCounter\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Pengaturan pencatatan (jadwal, jam kerja, retensi) diubah dari dashboard. App bisa mendengarkannya untuk audit log.
 */
final class SettingsUpdated
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly array $before,
        public readonly array $after,
        public readonly ?Authenticatable $user,
    ) {}
}
