<?php

namespace App\Mail;

use App\Enums\OtpPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public OtpPurpose $purpose, public int $minutes) {}

    public function build(): self
    {
        return $this->subject($this->purpose->subject($this->code))
            ->markdown('emails.otp');
    }
}
