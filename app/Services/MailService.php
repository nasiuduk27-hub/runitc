<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Throwable;

class MailService
{
    /**
     * @param array<int, string|array{path: string, name?: string, as?: string, mime?: string}> $attachments
     */
    public function send(string $toEmail, string $toName, string $subject, string $body, bool $isHtml = false, array $attachments = []): bool
    {
        try {
            $compose = function ($message) use ($toEmail, $toName, $subject, $attachments): void {
                $message->to($toEmail, $toName)->subject($subject);

                foreach ($attachments as $attachment) {
                    if (is_string($attachment) && is_file($attachment)) {
                        $message->attach($attachment);
                    } elseif (is_array($attachment) && ! empty($attachment['path']) && is_file($attachment['path'])) {
                        $options = [];
                        if (! empty($attachment['name'])) {
                            $options['as'] = $attachment['name'];
                        } elseif (! empty($attachment['as'])) {
                            $options['as'] = $attachment['as'];
                        }
                        if (! empty($attachment['mime'])) {
                            $options['mime'] = $attachment['mime'];
                        }
                        $message->attach($attachment['path'], $options);
                    }
                }
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
