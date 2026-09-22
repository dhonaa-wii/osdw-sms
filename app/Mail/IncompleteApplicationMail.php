<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IncompleteApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application)
    {
        $this->application->loadMissing('applicant', 'programOffering.scholarshipProgram', 'documents.offeringFormField');
    }

    public function build(): self
    {
        return $this->subject('Action needed on your scholarship application')
            ->markdown('emails.incomplete-application');
    }
}
