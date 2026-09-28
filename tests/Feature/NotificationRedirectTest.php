<?php

namespace Tests\Feature;

use App\Auth\LegacyUser;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class NotificationRedirectTest extends TestCase
{
    public function test_legacy_cooperative_route_redirects_to_credit_union(): void
    {
        $user = new LegacyUser(1, 'Admin Test', 'admin');

        Auth::setUser($user);

        $response = $this->withSession([
            'user_id' => 1,
            'account_id' => 'admin',
            'account_nm' => 'Admin Test',
        ])->get('/cooperative/savings');

        $response->assertRedirect('/credit-union/savings');
    }
}
