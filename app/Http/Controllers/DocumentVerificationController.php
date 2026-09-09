<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use Illuminate\Contracts\View\View;

class DocumentVerificationController extends Controller
{
    public function __invoke(string $token): View
    {
        $document = DocumentRecord::query()
            ->with(['type', 'category'])
            ->where('verification_token', $token)
            ->firstOrFail();

        return view('verification.show', [
            'document' => $document,
        ]);
    }
}
