<?php
declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    public static function load(bool $force = false): void
    {
        if (self::$cache !== null && !$force) {
            return;
        }
        $rows = Database::all('SELECT setting_key, setting_value FROM website_settings');
        self::$cache = [];
        foreach ($rows as $row) {
            self::$cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        $value = self::$cache[$key] ?? null;
        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function set(string $key, string $value, string $group = 'general'): void
    {
        Database::q(
            'INSERT INTO website_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_group = VALUES(setting_group)',
            [$key, $value, $group]
        );
        self::load(true);
    }

    public static function all(): array
    {
        self::load();
        return self::$cache ?? [];
    }
}
