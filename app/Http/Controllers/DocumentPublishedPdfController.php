<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPublishedPdfController extends Controller
{
    public function __invoke(Request $request, string $token): StreamedResponse
    {
        $document = DocumentRecord::query()
            ->where('verification_token', $token)
            ->whereIn('status', [DocumentStatus::Published->value, DocumentStatus::Archived->value])
            ->firstOrFail();

        $fileVersion = $document->currentFileVersion();

        abort_unless($fileVersion !== null, 404);

        $filePath = $fileVersion->file_path;

        abort_unless(Storage::exists($filePath), 404);

        if ($request->boolean('download')) {
            return Storage::download(
                $filePath,
                $fileVersion->original_name,
                ['Content-Type' => $fileVersion->mime_type]
            );
        }

        return Storage::response(
            $filePath,
            $fileVersion->original_name,
            ['Content-Type' => $fileVersion->mime_type]
        );
    }
}
