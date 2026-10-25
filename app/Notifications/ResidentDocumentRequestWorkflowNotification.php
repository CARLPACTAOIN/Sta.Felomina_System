<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResidentDocumentRequestWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(public int $requestId, public string $reference, public string $message, public string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{resident_document_request_id: int, reference: string, message: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['resident_document_request_id' => $this->requestId, 'reference' => $this->reference, 'message' => $this->message, 'url' => $this->url];
    }
}
