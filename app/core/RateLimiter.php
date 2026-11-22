<?php
declare(strict_types=1);

final class RateLimiter
{
    /**
     * Returns true when the request is allowed. Each call consumes one attempt.
     */
    public static function hit(string $bucket, string $action, int $maxAttempts, int $windowSeconds): bool
    {
        $key = hash('sha256', $bucket);
        $row = Database::one(
            'SELECT id, attempts, window_start FROM rate_limits WHERE bucket_key = ? AND action = ?',
            [$key, $action]
        );
        $now = time();
        if ($row === null) {
            Database::q(
                'INSERT INTO rate_limits (bucket_key, action, attempts, window_start) VALUES (?, ?, 1, NOW())',
                [$key, $action]
            );
            return true;
        }
        if (strtotime((string) $row['window_start']) < $now - $windowSeconds) {
            Database::q(
                'UPDATE rate_limits SET attempts = 1, window_start = NOW() WHERE id = ?',
                [$row['id']]
            );
            return true;
        }
        if ((int) $row['attempts'] >= $maxAttempts) {
            return false;
        }
        Database::q('UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        return true;
    }

    public static function clear(string $bucket, string $action): void
    {
        Database::q(
            'DELETE FROM rate_limits WHERE bucket_key = ? AND action = ?',
            [hash('sha256', $bucket), $action]
        );
    }

    public static function retryAfter(string $bucket, string $action, int $windowSeconds): int
    {
        $row = Database::one(
            'SELECT window_start FROM rate_limits WHERE bucket_key = ? AND action = ?',
            [hash('sha256', $bucket), $action]
        );
        if ($row === null) {
            return 0;
        }
        return max(0, (int) strtotime((string) $row['window_start']) + $windowSeconds - time());
    }
}
