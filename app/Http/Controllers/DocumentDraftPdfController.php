<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDraftPdfController extends Controller
{
    public function __invoke(Request $request, DocumentRecord $documentRecord): StreamedResponse
    {
        $this->authorize('downloadDraftPdf', $documentRecord);

        $draftPdfPath = $documentRecord->draft_pdf_path;

        abort_unless(is_string($draftPdfPath), 404);
        abort_unless(Storage::exists($draftPdfPath), 404);

        return Storage::response(
            $draftPdfPath,
            $documentRecord->draft_pdf_original_name ?? $documentRecord->document_number.'.pdf',
            ['Content-Type' => $documentRecord->draft_pdf_mime_type ?? 'application/pdf']
        );
    }
}
