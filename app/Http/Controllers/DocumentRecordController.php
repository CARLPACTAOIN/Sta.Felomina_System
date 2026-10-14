<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentRecord;
use App\Models\DocumentType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentRecordController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DocumentRecord::class);

        $filters = array_merge([
            'q' => '',
            'type' => '',
            'category' => '',
            'status' => '',
            'year' => '',
            'sort' => 'newest',
        ], $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'integer', Rule::exists('document_types', 'id')],
            'category' => ['nullable', 'integer', Rule::exists('document_categories', 'id')],
            'status' => ['nullable', 'string', Rule::in(array_column(DocumentStatus::cases(), 'value'))],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sort' => ['nullable', 'string', Rule::in(array_keys($this->sortOptions()))],
        ]));
        $filters = collect($filters)
            ->map(fn (mixed $value): mixed => $value === null ? '' : $value)
            ->all();

        $documents = DocumentRecord::query()
            ->with(['type', 'category', 'creator'])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = mb_strtolower((string) $filters['q']);

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->whereRaw('LOWER(document_number) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(title) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(subject) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(keywords) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(project_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(project_location) LIKE ?', ['%'.$search.'%'])
                        ->orWhereHas('type', function (Builder $query) use ($search): void {
                            $query
                                ->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                                ->orWhereRaw('LOWER(code) LIKE ?', ['%'.$search.'%']);
                        })
                        ->orWhereHas('category', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']));
                });
            })
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('document_type_id', (int) $filters['type']))
            ->when($filters['category'] !== '', fn (Builder $query) => $query->where('document_category_id', (int) $filters['category']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['year'] !== '', fn (Builder $query) => $query->where('document_year', (int) $filters['year']));

        $this->applySort($documents, (string) $filters['sort']);

        $documents = $documents
            ->paginate(15)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'documentTypes' => DocumentType::query()->orderBy('name')->get(),
            'documentCategories' => DocumentCategory::query()->orderBy('name')->get(),
            'statuses' => DocumentStatus::cases(),
            'filters' => $filters,
            'sortOptions' => $this->sortOptions(),
        ]);
    }

    /**
     * @param  Builder<DocumentRecord>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('document_date')->oldest('id'),
            'document_number' => $query->orderBy('document_number')->orderByDesc('id'),
            'title' => $query->orderBy('title')->orderByDesc('id'),
            'approval_date' => $query->orderByDesc('approved_at')->orderByDesc('id'),
            default => $query->latest('document_date')->latest('id'),
        };
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(): array
    {
        return [
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'document_number' => 'Document number',
            'title' => 'Title',
            'approval_date' => 'Approval date',
        ];
    }
}
