<?php

namespace Bangsamu\VisitorCounter\Support;

/**
 * Pengaturan pencatatan yang bisa diubah dari dashboard: kapan user login / tamu dicatat (selalu, hanya di luar jam
 * kerja, atau tidak), jam & hari kerja, hari libur, dan retensi. Bawaan dari config `visitor-counter.schedule` +
 * `retention_days`; perubahan dari dashboard disimpan di file JSON storage (tanpa query per request).
 *
 * @phpstan-type Settings array{users: string, guests: string, work_days: list<int>, work_start: string, work_end: string, holidays: bool, retention_days: int}
 */
final class VisitorSettings
{
    public const ALWAYS = 'always';

    public const OUTSIDE_WORK_HOURS = 'outside_work_hours';

    public const NEVER = 'never';

    public const MODES = [self::ALWAYS, self::OUTSIDE_WORK_HOURS, self::NEVER];

    /** @var array<string, mixed>|null isi file, dibaca sekali per proses/request */
    private ?array $stored = null;

    private bool $loaded = false;

    /**
     * @return Settings
     */
    public function all(): array
    {
        return self::normalize(array_merge($this->defaults(), $this->stored() ?? []));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return Settings yang tersimpan
     */
    public function save(array $settings, ?string $by = null): array
    {
        $normalized = self::normalize(array_merge($this->all(), $settings));
        JsonFile::write($this->path(), $normalized + ['updated_at' => now()->toIso8601String(), 'updated_by' => $by]);
        $this->loaded = false;

        return $normalized;
    }

    /**
     * Kapan dan oleh siapa pengaturan terakhir diubah dari dashboard; null = masih bawaan config.
     *
     * @return array{at: string|null, by: string|null}|null
     */
    public function updatedInfo(): ?array
    {
        $stored = $this->stored();

        return $stored === null ? null : [
            'at' => is_string($stored['updated_at'] ?? null) ? $stored['updated_at'] : null,
            'by' => is_string($stored['updated_by'] ?? null) ? $stored['updated_by'] : null,
        ];
    }

    public function retentionDays(): int
    {
        return $this->all()['retention_days'];
    }

    public function path(): string
    {
        $path = config('visitor-counter.settings_path');

        return is_string($path) && $path !== '' ? $path : JsonFile::defaultPath('settings');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return (array) config('visitor-counter.schedule', []) + ['retention_days' => config('visitor-counter.retention_days', 0)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(): ?array
    {
        if (! $this->loaded) {
            $this->stored = JsonFile::read($this->path());
            $this->loaded = true;
        }

        return $this->stored;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return Settings
     */
    private static function normalize(array $settings): array
    {
        $mode = fn ($value): string => in_array($value, self::MODES, true) ? $value : self::ALWAYS;
        $time = fn ($value, string $fallback): string => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : $fallback;
        $days = array_values(array_unique(array_filter(
            array_map('intval', (array) ($settings['work_days'] ?? [])),
            fn (int $day): bool => $day >= 1 && $day <= 7,
        )));
        sort($days);

        return [
            'users' => $mode($settings['users'] ?? null),
            'guests' => $mode($settings['guests'] ?? null),
            'work_days' => $days,
            'work_start' => $time($settings['work_start'] ?? null, '08:00'),
            'work_end' => $time($settings['work_end'] ?? null, '17:00'),
            'holidays' => (bool) ($settings['holidays'] ?? true),
            'retention_days' => max(0, min(3650, (int) ($settings['retention_days'] ?? 0))),
        ];
    }
}
