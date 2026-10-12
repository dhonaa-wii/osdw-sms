<x-mail::message>
@if ($purpose === \App\Enums\OtpPurpose::Register)
# Confirm your email

Enter this code to create your OSDW Scholarships account.
@else
# Reset your password

Enter this code to set a new password for your OSDW Scholarships account.
@endif

<x-mail::panel>
<span style="font-family: monospace; font-size: 26px; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

The code expires in {{ $minutes }} minutes. If you didn't ask for it, you can ignore this email.

Thanks,<br>
OSDW &mdash; Office of Student Development and Welfare
</x-mail::message>
