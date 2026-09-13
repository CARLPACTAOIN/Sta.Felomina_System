<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use Illuminate\Contracts\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        $documents = DocumentRecord::query()
            ->with(['type', 'category', 'creator', 'submitter'])
            ->where('status', DocumentStatus::PendingApproval)
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(15);

        return view('approvals.index', [
            'documents' => $documents,
        ]);
    }
}
