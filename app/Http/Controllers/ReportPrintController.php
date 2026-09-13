<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\ReportEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportPrintController extends Controller
{
    public function show(Request $request, ReportEngine $reportEngine): View
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $type = $request->string('type', 'document')->value();

        if ($user->role === UserRole::PurokChairperson) {
            $type = 'resident';
        }

        if ($type === 'document') {
            abort_unless($user->role->canManageDocuments(), 403, 'Unauthorized to view document reports.');

            $filters = $request->only([
                'year',
                'month',
                'category_id',
                'type_id',
                'status',
                'date_from',
                'date_to',
            ]);

            $reportData = $reportEngine->documentReport($user, $filters, perPage: null);
        } else {
            $filters = $request->only([
                'purok_id',
                'status',
                'voter_status',
                'sex',
                'civil_status',
            ]);

            $reportData = $reportEngine->residentReport($user, $filters, perPage: null);
        }

        return view('reports.print', [
            'type' => $type,
            'report' => $reportData,
            'filters' => $filters,
            'user' => $user,
            'autoPrint' => $request->boolean('auto_print'),
            'generatedAt' => Carbon::now(),
        ]);
    }
}
