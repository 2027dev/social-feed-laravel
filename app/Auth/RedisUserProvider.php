<?php

namespace App\Auth;

use App\Models\RedisUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?RedisUser
    {
        $data = Redis::hgetall("user:{$identifier}");

        return $data === [] ? null : $this->hydrate($data);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?RedisUser
    {
        $user = $this->retrieveById($identifier);

        if ($user && $user->getRememberToken() && hash_equals((string) $user->getRememberToken(), (string) $token)) {
            return $user;
        }

        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        Redis::hset("user:{$user->getAuthIdentifier()}", 'remember_token', $token);
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?RedisUser
    {
        if (! isset($credentials['email'])) {
            return null;
        }

        return $this->findByEmail($credentials['email']);
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return Hash::check($credentials['password'], $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void
    {
        if ($force || Hash::needsRehash($user->getAuthPassword())) {
            Redis::hset("user:{$user->getAuthIdentifier()}", 'password', Hash::make($credentials['password']));
        }
    }

    public function findByEmail(string $email): ?RedisUser
    {
        $id = Redis::get('user:email:'.$this->normalizeEmail($email));

        return $id ? $this->retrieveById((int) $id) : null;
    }

    public function create(string $name, string $email, string $password): RedisUser
    {
        $email = $this->normalizeEmail($email);
        $id = Redis::incr('users:next_id');

        $attributes = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'remember_token' => '',
        ];

        Redis::hmset("user:{$id}", $attributes);
        Redis::set('user:email:'.$email, $id);

        return $this->hydrate($attributes);
    }

    protected function normalizeEmail(string $email): string
    {
        return Str::lower($email);
    }

    protected function hydrate(array $data): RedisUser
    {
        return new RedisUser(
            id: (int) $data['id'],
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            remember_token: $data['remember_token'] ?: null,
        );
    }
}
