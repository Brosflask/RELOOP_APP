<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Developer 2. Gives a model a readable code such as CR-2026-000015.
 *
 * The row is saved with a temporary unique value, then renamed from its id
 * (the same approach Developer 3 uses for lot codes). The model sets
 * REFERENCE_COLUMN and REFERENCE_PREFIX constants.
 */
trait HasReferenceCode
{
    protected static function bootHasReferenceCode(): void
    {
        static::creating(function ($model) {
            $column = static::REFERENCE_COLUMN;
            $model->{$column} ??= 'TMP-'.Str::ulid(); // 30 characters, fits the column
        });

        static::created(function ($model) {
            $column = static::REFERENCE_COLUMN;
            if (str_starts_with((string) $model->{$column}, 'TMP-')) {
                $model->{$column} = sprintf('%s-%s-%06d', static::REFERENCE_PREFIX, $model->created_at->format('Y'), $model->id);
                $model->saveQuietly();
            }
        });
    }
}
