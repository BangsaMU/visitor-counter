<?php

namespace Bangsamu\VisitorCounter\Support;

/**
 * Layout dashboard: `auto` memakai layout tema app bangsamu (tabler / adminlte::page) bila ada, selain itu layout
 * paket. Nama section bisa diganti lewat config `dashboard.sections`.
 */
final class DashboardLayout
{
    /**
     * @return array{view: string, title: string, header: string|null, content: string}
     */
    public static function resolve(): array
    {
        $layout = (string) config('visitor-counter.dashboard.layout', 'auto');
        [$view, $sections] = $layout === 'auto' || $layout === ''
            ? self::detect()
            : [$layout, ['title' => 'title', 'header' => 'header', 'content' => 'content']];

        $configured = (array) config('visitor-counter.dashboard.sections', []);
        if ($configured !== []) {
            $sections = array_merge($sections, array_intersect_key($configured, $sections));
        }

        return [
            'view' => $view,
            'title' => (string) ($sections['title'] ?: 'title'),
            'header' => is_string($sections['header']) && $sections['header'] !== '' ? $sections['header'] : null,
            'content' => (string) ($sections['content'] ?: 'content'),
        ];
    }

    /**
     * @return array{0: string, 1: array{title: string, header: string|null, content: string}}
     */
    private static function detect(): array
    {
        if (config('app.themes') === '_tabler') {
            foreach (['layouts.tabler', 'master::layouts.tabler'] as $view) {
                if (view()->exists($view)) {
                    return [$view, ['title' => 'title', 'header' => null, 'content' => 'content']];
                }
            }
        }

        if (view()->exists('adminlte::page')) {
            return ['adminlte::page', ['title' => 'title', 'header' => 'content_header', 'content' => 'content']];
        }

        return ['visitor-counter::layout', ['title' => 'title', 'header' => 'header', 'content' => 'content']];
    }
}
