<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RenewalNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application)
    {
        $this->application->loadMissing('applicant', 'programOffering.scholarshipProgram');
    }

    public function build(): self
    {
        return $this->subject('Hand in your requirements to renew your scholarship')
            ->markdown('emails.renewal-notice');
    }
}
