<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailService
{
    /**
     * @param string|list<string> $toEmail
     * @param array<int, string|array{path: string, name?: string, as?: string, mime?: string}> $attachments
     * @param list<string> $cc
     * @param list<string> $replyTo
     */
    public function send(
        string|array $toEmail,
        string $toName,
        string $subject,
        string $body,
        bool $isHtml = false,
        array $attachments = [],
        array $cc = [],
        array $replyTo = []
    ): bool {
        try {
            $validRecipients = [];
            $singleMode = is_string($toEmail);

            if ($singleMode) {
                $trimmed = trim($toEmail);
                if ($trimmed === '') {
                    return false;
                }
                $validRecipients[] = $trimmed;
            } else {
                foreach ($toEmail as $email) {
                    $trimmed = trim((string) $email);
                    if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                        $validRecipients[] = $trimmed;
                    }
                }
                $validRecipients = array_values(array_unique($validRecipients));
                if (empty($validRecipients)) {
                    Log::warning('MailService::send gagal: tidak ada alamat penerima (To) yang valid.');

                    return false;
                }
            }

            $validCc = [];
            foreach ($cc as $ccItem) {
                $trimmed = trim((string) $ccItem);
                if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $validCc[] = $trimmed;
                }
            }
            $validCc = array_values(array_unique($validCc));

            $validReplyTo = [];
            foreach ($replyTo as $replyItem) {
                $trimmed = trim((string) $replyItem);
                if ($trimmed !== '' && filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $validReplyTo[] = $trimmed;
                }
            }
            $validReplyTo = array_values(array_unique($validReplyTo));

            $compose = function ($message) use ($singleMode, $toEmail, $toName, $validRecipients, $validCc, $validReplyTo, $subject, $attachments): void {
                if ($singleMode) {
                    $message->to($toEmail, $toName);
                } else {
                    foreach ($validRecipients as $recipient) {
                        $message->to($recipient);
                    }
                }

                $message->subject($subject);

                foreach ($validCc as $ccAddress) {
                    $message->cc($ccAddress);
                }

                foreach ($validReplyTo as $replyAddress) {
                    $message->replyTo($replyAddress);
                }

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

