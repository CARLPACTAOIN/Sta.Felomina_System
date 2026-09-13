<?php

namespace App\Models;

use Database\Factories\CategorizationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $document_category_id
 * @property int|null $document_type_id
 * @property string $name
 * @property array<int, string> $keywords
 * @property int $priority
 * @property bool $is_active
 * @property-read DocumentCategory $category
 * @property-read DocumentType|null $type
 */
#[Fillable([
    'document_category_id',
    'document_type_id',
    'name',
    'keywords',
    'priority',
    'is_active',
])]
class CategorizationRule extends Model
{
    /** @use HasFactory<CategorizationRuleFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<DocumentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'document_category_id');
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    /**
     * @return HasMany<DocumentRecord, $this>
     */
    public function suggestedDocumentRecords(): HasMany
    {
        return $this->hasMany(DocumentRecord::class, 'category_suggestion_rule_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
