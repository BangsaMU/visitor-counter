<?php

namespace Bangsamu\VisitorCounter\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Saklar pencatatan dari dashboard / artisan. Disimpan sebagai file penanda di storage (seperti `php artisan down`):
 * file ada = pencatatan mati. Tetap berlaku setelah cache:clear / deploy, dan tidak menambah query per request, jadi
 * saat mati tidak ada apa pun yang ditulis ke database. Config `enabled` = false tetap mematikan apa pun isi saklar.
 */
final class RecordingSwitch
{
    public function enabled(): bool
    {
        return $this->configEnabled() && ! is_file($this->path());
    }

    public function configEnabled(): bool
    {
        return (bool) config('visitor-counter.enabled', true);
    }

    /**
     * Kapan dan oleh siapa saklar dimatikan; null bila saklar menyala.
     *
     * @return array{at: string|null, by: string|null}|null
     */
    public function disabledInfo(): ?array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return null;
        }

        $data = JsonFile::read($path) ?? [];

        return [
            'at' => is_string($data['at'] ?? null) ? $data['at'] : null,
            'by' => is_string($data['by'] ?? null) ? $data['by'] : null,
        ];
    }

    public function disable(?string $by = null): void
    {
        JsonFile::write($this->path(), ['at' => now()->toIso8601String(), 'by' => $by]);
    }

    public function enable(): void
    {
        $path = $this->path();
        File::delete($path);
        clearstatcache(true, $path);

        if (is_file($path)) {
            throw new RuntimeException("File saklar visitor-counter tidak bisa dihapus: {$path}");
        }
    }

    /**
     * Bawaan per environment (storage/framework/visitor-counter-{env}-disabled.json), supaya test tidak ikut
     * terpengaruh saklar situs dev di folder yang sama.
     */
    public function path(): string
    {
        $path = config('visitor-counter.switch_path');

        return is_string($path) && $path !== '' ? $path : JsonFile::defaultPath('disabled');
    }
}
