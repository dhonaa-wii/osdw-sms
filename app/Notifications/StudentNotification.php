<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\Notification;

/**
 * One entry in a student's notification bell. Stored in the database only —
 * emails for the same events (award, renewal) are sent separately and logged
 * in email_logs.
 */
class StudentNotification extends Notification
{
    /**
     * @param  string  $url  a path within the site (no host), so a changed APP_URL never breaks old links
     */
    public function __construct(
        public NotificationKind $kind,
        public string $title,
        public string $body,
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
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind->value,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
