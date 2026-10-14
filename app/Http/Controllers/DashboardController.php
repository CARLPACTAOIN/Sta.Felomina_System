<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $metricsService): View
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $isPurokChairperson = $user->isPurokChairperson();

        $metrics = $isPurokChairperson
            ? $metricsService->forPurokChairperson($user)
            : $metricsService->forCaptainOrSecretary($user);

        return view('dashboard', [
            'isPurokChairperson' => $isPurokChairperson,
            'metrics' => $metrics,
        ]);
    }
}
