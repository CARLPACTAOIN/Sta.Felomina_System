<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return view('notifications.index', [
            'notifications' => $user->notifications()
                ->latest()
                ->paginate(15),
        ]);
    }
}
