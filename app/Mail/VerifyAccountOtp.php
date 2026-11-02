<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class VerifyAccountOtp extends Mailable {
    use Queueable, SerializesModels;
    public function __construct(public string $otp) {}
    public function envelope(): Envelope { return new Envelope(subject: 'WASLA - رمز تفعيل الحساب'); }
    public function content(): Content { return new Content(text: 'emails.verify-account-otp'); }
    public function attachments(): array { return []; }
}
