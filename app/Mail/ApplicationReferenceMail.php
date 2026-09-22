<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationReferenceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application)
    {
        $this->application->loadMissing('applicant', 'programOffering.scholarshipProgram');
    }

    public function build(): self
    {
        return $this->subject('Your OSDW application reference number')
            ->markdown('emails.application-reference');
    }
}
