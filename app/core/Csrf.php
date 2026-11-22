<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function check(string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    public static function rotate(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
