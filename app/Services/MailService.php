<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Throwable;

class MailService
{
    public function send(string $toEmail, string $toName, string $subject, string $body, bool $isHtml = false): bool
    {
        try {
            $compose = function ($message) use ($toEmail, $toName, $subject): void {
                $message->to($toEmail, $toName)->subject($subject);
            };

            if ($isHtml) {
                Mail::html($body, $compose);
            } else {
                Mail::raw($body, $compose);
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
