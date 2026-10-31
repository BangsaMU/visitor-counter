<?php

namespace Bangsamu\VisitorCounter\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Nama user untuk dashboard dan pencarian user, dari model user app (config dashboard.user_model atau
 * auth.providers.users.model). Model/kolom tidak cocok = dashboard tetap jalan dengan id user saja.
 */
final class UserDirectory
{
    /**
     * @param  iterable<int|string|null>  $ids
     * @return array<int, string>
     */
    public function labels(iterable $ids): array
    {
        $model = $this->model();
        $ids = collect($ids)->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique()->values()->all();
        if ($model === null || $ids === []) {
            return [];
        }

        try {
            return $model::query()->whereKey($ids)->get()
                ->mapWithKeys(fn (Model $user): array => [(int) $user->getKey() => $this->label($user)])
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * Id user yang cocok dengan teks (angka = id user).
     *
     * @return list<int>
     */
    public function search(string $term): array
    {
        if (ctype_digit($term)) {
            return [(int) $term];
        }

        $model = $this->model();
        if ($model === null) {
            return [];
        }

        try {
            $instance = new $model;

            return $model::query()
                ->where(function (Builder $query) use ($term): void {
                    foreach ($this->attributes() as $attribute) {
                        $query->orWhere($attribute, 'like', '%'.$term.'%');
                    }
                })
                ->limit(500)
                ->pluck($instance->getKeyName())
                ->map(fn ($id): int => (int) $id)
                ->all();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @return class-string<Model>|null
     */
    private function model(): ?string
    {
        $model = config('visitor-counter.dashboard.user_model') ?? config('auth.providers.users.model');

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    /**
     * @return list<string>
     */
    private function attributes(): array
    {
        return array_values(array_filter(
            (array) config('visitor-counter.dashboard.user_label_attributes', ['name', 'email']),
            fn ($attribute): bool => is_string($attribute) && $attribute !== '',
        ));
    }

    private function label(Model $user): string
    {
        $parts = array_filter(
            array_map(fn (string $attribute): string => trim((string) $user->getAttribute($attribute)), $this->attributes()),
            fn (string $value): bool => $value !== '',
        );

        return $parts === [] ? '#'.$user->getKey() : implode(' · ', array_unique($parts));
    }
}
