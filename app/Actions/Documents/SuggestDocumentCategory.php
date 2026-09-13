<?php

namespace App\Actions\Documents;

use App\Models\CategorizationRule;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SuggestDocumentCategory
{
    public function handle(?int $documentTypeId, string $title, string $subject, ?string $keywords): ?DocumentCategorySuggestion
    {
        $documentType = $documentTypeId === null ? null : DocumentType::query()->find($documentTypeId);
        $haystack = $this->normalize(collect([
            $title,
            $subject,
            $keywords,
            $documentType?->name,
            $documentType?->code,
        ])->filter(fn (?string $value): bool => filled($value))->join(' '));

        if ($haystack === '') {
            return null;
        }

        $rules = CategorizationRule::query()
            ->with('category')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($documentTypeId): void {
                $query->whereNull('document_type_id');

                if ($documentTypeId !== null) {
                    $query->orWhere('document_type_id', $documentTypeId);
                }
            })
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $matchedTerms = $this->matchedTerms($haystack, $rule->keywords);

            if ($matchedTerms === []) {
                continue;
            }

            $terms = implode(', ', $matchedTerms);

            return new DocumentCategorySuggestion(
                rule: $rule,
                category: $rule->category,
                matchedTerms: $matchedTerms,
                reason: sprintf('Matched rule "%s" using: %s.', $rule->name, $terms),
            );
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->squish()
            ->lower()
            ->toString();
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function matchedTerms(string $haystack, array $keywords): array
    {
        return collect($keywords)
            ->map(fn (string $keyword): string => $this->normalize($keyword))
            ->filter(fn (string $keyword): bool => $keyword !== '')
            ->unique()
            ->filter(fn (string $keyword): bool => preg_match('/(?<![\pL\pN])'.preg_quote($keyword, '/').'(?![\pL\pN])/u', $haystack) === 1)
            ->values()
            ->all();
    }
}
