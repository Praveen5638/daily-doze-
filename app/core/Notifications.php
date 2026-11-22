<?php
declare(strict_types=1);

final class Notifications
{
    public static function admin(string $type, string $title, ?string $message = null, ?string $link = null): void
    {
        Database::insert('notifications', [
            'type' => $type,
            'title' => mb_substr($title, 0, 190),
            'message' => $message !== null ? mb_substr($message, 0, 500) : null,
            'link' => $link !== null ? mb_substr($link, 0, 255) : null,
        ]);
    }

    public static function unreadCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
    }

    public static function latest(int $limit = 8): array
    {
        $limit = max(1, min(30, $limit));
        return Database::all("SELECT * FROM notifications ORDER BY created_at DESC LIMIT {$limit}");
    }

    public static function markAllRead(): void
    {
        Database::q('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
    }

    public static function audit(string $action, ?string $entityType = null, ?string $entityId = null, ?array $details = null): void
    {
        $admin = Auth::admin();
        Database::insert('audit_logs', [
            'admin_id' => $admin['id'] ?? null,
            'action' => mb_substr($action, 0, 80),
            'entity_type' => $entityType !== null ? mb_substr($entityType, 0, 60) : null,
            'entity_id' => $entityId !== null ? mb_substr($entityId, 0, 60) : null,
            'details' => $details !== null ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'ip' => request_ip(),
        ]);
    }
}
