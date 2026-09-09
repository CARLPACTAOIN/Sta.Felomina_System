<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use Illuminate\Contracts\View\View;

class DocumentRecordController extends Controller
{
    public function index(): View
    {
        $documents = DocumentRecord::query()
            ->with(['type', 'category', 'creator'])
            ->latest()
            ->latest('id')
            ->paginate(15);

        return view('documents.index', [
            'documents' => $documents,
        ]);
    }
}
