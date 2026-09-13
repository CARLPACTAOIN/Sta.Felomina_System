<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        $auditLogs = AuditLog::query()
            ->with('actor')
            ->latest()
            ->paginate(25);

        return view('audit-logs.index', [
            'auditLogs' => $auditLogs,
        ]);
    }
}
