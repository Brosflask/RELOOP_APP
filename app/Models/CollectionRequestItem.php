<?php

namespace App\Models;

use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Developer 2. A plastic type (and rough weight) listed on a collection request. */
class CollectionRequestItem extends Model
{
    use HasFactory;

    protected $fillable = ['collection_request_id', 'material_type', 'estimated_weight_kg'];

    protected function casts(): array
    {
        return [
            'material_type' => MaterialType::class,
            'estimated_weight_kg' => 'decimal:3',
        ];
    }

    public function collectionRequest(): BelongsTo
    {
        return $this->belongsTo(CollectionRequest::class);
    }
}
