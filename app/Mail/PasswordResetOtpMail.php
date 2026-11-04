<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The 6-digit reset code. Sent synchronously right after the challenge commits; never queued or logged.
 */
class PasswordResetOtpMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly string $otp, public readonly int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã đặt lại mật khẩu Fakestore');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.password-reset-otp');
    }
}
