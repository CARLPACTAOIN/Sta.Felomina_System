<?php

namespace App\Http\Controllers;

use App\Models\DocumentRecord;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class DocumentQrCodeController extends Controller
{
    public function __invoke(DocumentRecord $documentRecord): Response
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(420),
            new SvgImageBackEnd
        ));

        $fileName = Str::slug($documentRecord->document_number).'-qr.svg';

        return response($writer->writeString($documentRecord->verificationUrl()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'X-Verification-Route' => $documentRecord->verificationUrl(),
        ]);
    }
}
