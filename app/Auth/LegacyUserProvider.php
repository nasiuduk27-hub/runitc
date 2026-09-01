<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\DB;

class LegacyUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        foreach (['run', 'mysql'] as $connection) {
            $row = $this->findUser($connection, (int) $identifier);

            if ($row !== null) {
                return $this->toUser($connection, $row);
            }
        }

        return null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): bool
    {
        return false;
    }

    private function findUser(string $connection, int $userId): ?object
    {
        try {
            return DB::connection($connection)
                ->table('sysitc_users as u')
                ->join('sysitc_login as l', 'l.rec_id', '=', 'u.login_rec_id')
                ->where('u.rec_id', $userId)
                ->select('u.rec_id', 'u.account_nm', 'l.account_id')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toUser(string $connection, object $row): LegacyUser
    {
        return new LegacyUser(
            (int) $row->rec_id,
            (string) $row->account_nm,
            (string) $row->account_id,
            (string) $row->rec_id,
            $connection === 'run' ? 'run' : 'main',
        );
    }
}
