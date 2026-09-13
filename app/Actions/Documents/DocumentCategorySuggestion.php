<?php

namespace App\Actions\Documents;

use App\Models\CategorizationRule;
use App\Models\DocumentCategory;

class DocumentCategorySuggestion
{
    /**
     * @param  array<int, string>  $matchedTerms
     */
    public function __construct(
        public readonly CategorizationRule $rule,
        public readonly DocumentCategory $category,
        public readonly array $matchedTerms,
        public readonly string $reason,
    ) {}
}
