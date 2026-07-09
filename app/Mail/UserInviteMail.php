<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public string $resetUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('You have been invited to Lumi')
            ->view('emails.user-invite');
    }
}