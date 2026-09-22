<?php

namespace App\Services;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use App\Models\Application;
use App\Models\EmailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    public function send(Application $application, EmailType $type, Mailable $mailable, string $recipient): EmailLog
    {
        $mailable->build();

        $log = EmailLog::create([
            'application_id' => $application->id,
            'recipient' => $recipient,
            'subject' => $mailable->subject ?? $type->label(),
            'type' => $type,
            'status' => EmailStatus::Queued,
        ]);

        try {
            Mail::to($recipient)->send($mailable);

            $log->update(['status' => EmailStatus::Sent, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => EmailStatus::Failed, 'error_message' => $e->getMessage()]);
        }

        return $log;
    }
}
