<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $filter = $request->query('filter', 'all');

        $query = $user->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->paginate(15)->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();
        $readCount = $user->readNotifications()->count();
        $totalCount = $user->notifications()->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'readCount' => $readCount,
            'totalCount' => $totalCount,
            'currentFilter' => $filter,
        ]);
    }

    public function open(string $id): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->where('id', $id)->firstOrFail();

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        $targetUrl = $this->resolveTargetUrl($notification, $user);

        return redirect()->to($targetUrl);
    }

    public function markAsRead(string $id): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->where('id', $id)->firstOrFail();

        $notification->markAsRead();

        return back()->with('status', __('Notification marked as read.'));
    }

    public function markAsUnread(string $id): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->where('id', $id)->firstOrFail();

        $notification->update(['read_at' => null]);

        return back()->with('status', __('Notification marked as unread.'));
    }

    public function markAllAsRead(): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', __('All notifications marked as read.'));
    }

    public function resolveTargetUrl(DatabaseNotification $notification, User $user): string
    {
        /** @var array<string, mixed> $data */
        $data = (array) $notification->data;
        $url = data_get($data, 'url');
        $message = (string) data_get($data, 'message', '');
        /** @var int|string|null $documentRecordId */
        $documentRecordId = data_get($data, 'document_record_id');

        if ($user->can('documents.review') && (str_contains(strtolower($message), 'awaiting approval') || str_contains(strtolower($message), 'approval'))) {
            if ($documentRecordId !== null) {
                /** @var DocumentRecord|null $doc */
                $doc = DocumentRecord::query()->find($documentRecordId);
                if ($doc instanceof DocumentRecord && $doc->status === DocumentStatus::PendingApproval) {
                    return route('approvals.index', ['search' => $doc->document_number], absolute: false).'#pending-approval-'.$doc->id;
                }
            }
        }

        if (is_string($url) && $url !== '') {
            return $url;
        }

        if ($documentRecordId !== null) {
            return route('documents.show', $documentRecordId, absolute: false);
        }

        return route('notifications.index', absolute: false);
    }
}
