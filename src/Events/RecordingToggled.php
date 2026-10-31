<?php

namespace Bangsamu\VisitorCounter\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Pencatatan dinyalakan/dimatikan lewat dashboard atau artisan. App bisa mendengarkannya untuk audit log.
 */
final class RecordingToggled
{
    public const SOURCE_DASHBOARD = 'dashboard';

    public const SOURCE_CONSOLE = 'console';

    public function __construct(
        public readonly bool $enabled,
        public readonly ?Authenticatable $user,
        public readonly string $source,
    ) {}
}
