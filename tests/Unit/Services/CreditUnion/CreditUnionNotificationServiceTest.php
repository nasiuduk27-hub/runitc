<?php

namespace Tests\Unit\Services\CreditUnion;

use App\Services\CreditUnion\CreditUnionNotificationService;
use PHPUnit\Framework\TestCase;

class CreditUnionNotificationServiceTest extends TestCase
{
    public function test_member_and_other_admins_receive_excluding_actor(): void
    {
        $recipients = CreditUnionNotificationService::decisionRecipients([10, 11], 20, 99, 10);

        $this->assertSame([11, 20], $recipients);
    }

    public function test_fallback_used_when_member_not_linked(): void
    {
        $recipients = CreditUnionNotificationService::decisionRecipients([10, 11], 0, 99, 11);

        $this->assertSame([10, 99], $recipients);
    }

    public function test_recipients_are_deduplicated_when_member_is_admin(): void
    {
        $recipients = CreditUnionNotificationService::decisionRecipients([10, 11], 10, 0, 99);

        $this->assertSame([10, 11], $recipients);
    }

    public function test_invalid_and_actor_ids_are_dropped(): void
    {
        $recipients = CreditUnionNotificationService::decisionRecipients([0, 10], 0, 0, 10);

        $this->assertSame([], $recipients);
    }

    public function test_member_is_excluded_when_actor_is_the_member(): void
    {
        $recipients = CreditUnionNotificationService::decisionRecipients([11], 20, 20, 20);

        $this->assertSame([11], $recipients);
    }
}
