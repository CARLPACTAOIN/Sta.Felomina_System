<?php

namespace App\Models;

use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'description', 'is_active'])]
class DocumentCategory extends Model
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<DocumentRecord, $this>
     */
    public function documentRecords(): HasMany
    {
        return $this->hasMany(DocumentRecord::class);
    }

    /**
     * @return HasMany<DocumentRecord, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DocumentRecord::class);
    }

    /**
     * @return HasMany<CategorizationRule, $this>
     */
    public function categorizationRules(): HasMany
    {
        return $this->hasMany(CategorizationRule::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
