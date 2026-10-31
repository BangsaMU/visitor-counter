<?php

namespace Bangsamu\VisitorCounter\Support;

use Illuminate\Support\Facades\File;

/**
 * File JSON kecil di storage untuk saklar dan pengaturan pencatatan: dibaca tanpa query, ditulis atomik (tulis lalu
 * rename) dengan izin 0666 supaya web server dan artisan (user berbeda) sama-sama bisa menimpa.
 */
final class JsonFile
{
    /**
     * @return array<string, mixed>|null null = file tidak ada / bukan JSON objek
     */
    public static function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function write(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));

        $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temp, (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        chmod($temp, 0666);
        rename($temp, $path);
        clearstatcache(true, $path);
    }

    /**
     * storage/framework/visitor-counter-{APP_ENV}-{name}.json: per environment supaya test tidak ikut status situs dev.
     */
    public static function defaultPath(string $name): string
    {
        return storage_path('framework/visitor-counter-'.app()->environment().'-'.$name.'.json');
    }
}
