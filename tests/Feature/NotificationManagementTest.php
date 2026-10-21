<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentRecord;
use App\Models\User;
use App\Notifications\DocumentWorkflowNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('notifications index renders empty state when user has no notifications', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertSuccessful()
        ->assertSee('You’re all caught up')
        ->assertSee('Relevant workflow notifications will appear here');
});

test('notifications index displays unread and read notifications with metadata and filter tabs', function () {
    $user = User::factory()->create();
    $doc = DocumentRecord::factory()->create(['title' => 'Barangay Ordinance 2026-01']);

    $user->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Document returned for revision.',
        url: "/documents/{$doc->id}",
    ));

    $user->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Document approved and published.',
        url: "/documents/{$doc->id}",
    ));

    // Mark one as read
    /** @var DatabaseNotification $readNotification */
    $readNotification = $user->notifications()->first();
    $readNotification->markAsRead();

    // Verify all filter
    $response = $this->actingAs($user)->get(route('notifications.index'));
    $response->assertSuccessful()
        ->assertSee('Document returned for revision.')
        ->assertSee('Document approved and published.')
        ->assertSee($doc->document_number)
        ->assertSee($doc->title)
        ->assertSee('Mark all as read')
        ->assertSee('data-flux-navlist-badge')
        ->assertSee('bg-red-500');

    // Verify unread filter
    $unreadResponse = $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']));
    $unreadResponse->assertSuccessful()
        ->assertSee('Document approved and published.')
        ->assertDontSee('Document returned for revision.')
        ->assertDontSee('No unread notifications');

    // Verify read filter
    $readResponse = $this->actingAs($user)->get(route('notifications.index', ['filter' => 'read']));
    $readResponse->assertSuccessful()
        ->assertSee('Document returned for revision.')
        ->assertDontSee('Document approved and published.');
});

test('user can mark an individual notification as read', function () {
    $user = User::factory()->create();
    $doc = DocumentRecord::factory()->create();

    $user->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Document returned for revision.',
        url: "/documents/{$doc->id}",
    ));

    /** @var DatabaseNotification $notification */
    $notification = $user->unreadNotifications()->first();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('notifications.read', $notification->id))
        ->assertRedirect()
        ->assertSessionHas('status', __('Notification marked as read.'));

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('user can mark an individual notification as unread', function () {
    $user = User::factory()->create();
    $doc = DocumentRecord::factory()->create();

    $user->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Document approved and published.',
        url: "/documents/{$doc->id}",
    ));

    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->first();
    $notification->markAsRead();
    expect($notification->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('notifications.unread', $notification->id))
        ->assertRedirect()
        ->assertSessionHas('status', __('Notification marked as unread.'));

    $notification->refresh();
    expect($notification->read_at)->toBeNull();
});

test('user can mark all notifications as read', function () {
    $user = User::factory()->create();
    $doc = DocumentRecord::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $user->notify(new DocumentWorkflowNotification(
            documentRecordId: $doc->id,
            documentNumber: $doc->document_number,
            title: $doc->title,
            message: "Notification update {$i}",
            url: "/documents/{$doc->id}",
        ));
    }

    expect($user->unreadNotifications()->count())->toBe(3);

    $this->actingAs($user)
        ->post(route('notifications.mark-all-read'))
        ->assertRedirect()
        ->assertSessionHas('status', __('All notifications marked as read.'));

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($user->readNotifications()->count())->toBe(3);
});

test('user cannot mark or open another users notification', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $doc = DocumentRecord::factory()->create();

    $userA->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Private notification for User A',
        url: "/documents/{$doc->id}",
    ));

    /** @var DatabaseNotification $notificationA */
    $notificationA = $userA->notifications()->first();

    // User B attempts to mark User A's notification as read
    $this->actingAs($userB)
        ->patch(route('notifications.read', $notificationA->id))
        ->assertNotFound();

    // User B attempts to open User A's notification
    $this->actingAs($userB)
        ->get(route('notifications.open', $notificationA->id))
        ->assertNotFound();
});

test('captain opening awaiting approval notification marks it read and deeplinks to approval workbench card', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create([
        'status' => DocumentStatus::PendingApproval,
        'document_number' => 'RES-2026-099',
    ]);

    $captain->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Document awaiting approval.',
        url: "/documents/{$doc->id}", // seeded/legacy fallback
    ));

    /** @var DatabaseNotification $notification */
    $notification = $captain->unreadNotifications()->first();
    expect($notification->read_at)->toBeNull();

    $response = $this->actingAs($captain)
        ->get(route('notifications.open', $notification->id));

    $expectedUrl = route('approvals.index', ['search' => $doc->document_number], absolute: false).'#pending-approval-'.$doc->id;
    $response->assertRedirect($expectedUrl);

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('secretary opening return or approved notification marks it read and deeplinks to document record', function () {
    $secretary = User::factory()->create();
    $doc = DocumentRecord::factory()->create([
        'status' => DocumentStatus::NeedsRevision,
        'created_by_id' => $secretary->id,
    ]);

    $secretary->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Document returned for revision.',
        url: route('documents.show', $doc, absolute: false),
    ));

    /** @var DatabaseNotification $notification */
    $notification = $secretary->unreadNotifications()->first();
    expect($notification->read_at)->toBeNull();

    $response = $this->actingAs($secretary)
        ->get(route('notifications.open', $notification->id));

    $response->assertRedirect(route('documents.show', $doc, absolute: false));

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('header and sidebar indicator displays unread count badge only when unread notifications exist', function () {
    $user = User::factory()->create();
    $doc = DocumentRecord::factory()->create();

    // Zero unread notifications
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('data-testid="header-unread-badge"', false);

    // Add 2 notifications
    $user->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'First unread notification',
        url: "/documents/{$doc->id}",
    ));

    $user->notify(new DocumentWorkflowNotification(
        documentRecordId: $doc->id,
        documentNumber: $doc->document_number,
        title: $doc->title,
        message: 'Second unread notification',
        url: "/documents/{$doc->id}",
    ));

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertSuccessful()
        ->assertSee('data-testid="header-unread-badge"', false)
        ->assertSee('Notifications');

    // Mark all as read
    $user->unreadNotifications()->update(['read_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertDontSee('data-testid="header-unread-badge"', false);
});

test('pending approval workbench card renders matching anchor id and supports url search query', function () {
    $captain = User::factory()->captain()->create();
    $doc = DocumentRecord::factory()->create([
        'status' => DocumentStatus::PendingApproval,
        'document_number' => 'DOC-TARGET-99',
    ]);

    $this->actingAs($captain)
        ->get(route('approvals.index', ['search' => 'DOC-TARGET-99']))
        ->assertSuccessful()
        ->assertSee('id="pending-approval-'.$doc->id.'"', false)
        ->assertSee('DOC-TARGET-99');

    Livewire::actingAs($captain)
        ->withQueryParams(['search' => 'DOC-TARGET-99'])
        ->test('pages::approvals.index')
        ->assertSet('search', 'DOC-TARGET-99')
        ->assertSee('DOC-TARGET-99');
});

test('document submitForApproval notifies captains with deeplink url to approval workbench card', function () {
    $captain = User::factory()->captain()->create();
    $secretary = User::factory()->create();
    $doc = DocumentRecord::factory()->create([
        'status' => DocumentStatus::Draft,
        'created_by_id' => $secretary->id,
        'draft_pdf_path' => 'documents/test.pdf',
    ]);

    // Create fake draft pdf
    Storage::put('documents/test.pdf', '%PDF-1.4 test');

    $doc->submitForApproval($secretary);

    /** @var DatabaseNotification|null $captainNotification */
    $captainNotification = $captain->notifications()->latest()->first();

    expect($captainNotification)->not->toBeNull();
    $data = $captainNotification->data;
    expect(data_get($data, 'message'))->toBe('Document awaiting approval.')
        ->and(data_get($data, 'url'))->toBe(
            route('approvals.index', ['search' => $doc->document_number], absolute: false).'#pending-approval-'.$doc->id
        );
});
