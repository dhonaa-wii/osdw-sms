<?php

namespace App\Mail;

use App\Models\Award;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AwardNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Award $award)
    {
        $this->award->loadMissing('application.applicant', 'programOffering.scholarshipProgram');
    }

    public function build(): self
    {
        return $this->subject($this->award->application->isRenewal() ? 'Your scholarship has been renewed' : 'You have been awarded a scholarship')
            ->markdown('emails.award-notice');
    }
}
