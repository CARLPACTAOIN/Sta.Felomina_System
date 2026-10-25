<?php

namespace App\Http\Controllers;

use App\Enums\StaffPermission;
use App\Models\Purok;
use App\Models\PurokResolutionFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurokResolutionFileController extends Controller
{
    public function __invoke(Request $request, Purok $purok, ?PurokResolutionFile $purokResolutionFile = null): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->can(StaffPermission::ManagePuroks->value), 403);

        $resolutionFile = $purokResolutionFile ?? $purok->currentResolutionFile;

        abort_unless(
            $resolutionFile !== null
                && $resolutionFile->purok_id === $purok->id
                && Storage::exists($resolutionFile->path),
            404,
        );

        return Storage::response(
            $resolutionFile->path,
            $resolutionFile->original_name,
            ['Content-Type' => $resolutionFile->mime_type],
        );
    }
}
