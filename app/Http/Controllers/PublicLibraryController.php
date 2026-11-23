<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use App\Models\DocumentType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicLibraryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'type' => ['nullable', 'integer'], 'document' => ['nullable', 'string', 'max:48']]);
        $query = DocumentRecord::query()->publicLibrary()->with('type');
        if (filled($input['q'] ?? null)) {
            $search = $input['q'];
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('document_number', 'like', '%'.$search.'%'));
        }
        if (filled($input['type'] ?? null)) {
            $query->where('document_type_id', $input['type']);
        }
        $selected = isset($input['document']) ? DocumentRecord::query()->publicLibrary()->with('type')->where('verification_token', $input['document'])->first() : null;
        $version = $selected?->currentFileVersion();
        if ($selected && (! $version || ! Storage::exists($version->file_path))) {
            $selected = null;
        }

        return view('welcome', [
            'documents' => $query->orderByDesc('approved_at')->orderByDesc('id')->paginate(10)->withQueryString(),
            'types' => DocumentType::query()->whereIn('id', DocumentRecord::query()->publicLibrary()->select('document_type_id'))->orderBy('name')->get(),
            'selected' => $selected,
        ]);
    }
}
