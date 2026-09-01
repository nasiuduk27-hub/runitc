<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class LegacyUser implements Authenticatable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name = '',
        public readonly string $accountId = '',
        public readonly string $userRecId = '',
        public readonly string $authDb = 'run',
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
