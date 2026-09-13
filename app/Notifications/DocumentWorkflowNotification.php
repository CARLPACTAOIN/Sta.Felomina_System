<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $documentRecordId,
        public string $documentNumber,
        public string $title,
        public string $message,
        public string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_record_id' => $this->documentRecordId,
            'document_number' => $this->documentNumber,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
