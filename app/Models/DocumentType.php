<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $number_prefix
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'code', 'number_prefix', 'description', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
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

    public function nextDocumentNumber(int $year): string
    {
        /** @var Collection<int, string> $existingNumbers */
        $existingNumbers = $this->documentRecords()
            ->where('document_year', $year)
            ->where('document_number', 'like', $this->number_prefix.'-'.$year.'-%')
            ->pluck('document_number');

        $prefixPattern = '/^'.preg_quote($this->number_prefix, '/').'-'.$year.'-(\d+)$/';

        $maxSequence = $existingNumbers
            ->map(fn (string $number): ?int => preg_match($prefixPattern, $number, $matches) === 1 ? (int) $matches[1] : null)
            ->filter()
            ->max();

        return sprintf('%s-%d-%03d', $this->number_prefix, $year, ((int) $maxSequence) + 1);
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
