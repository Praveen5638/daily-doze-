<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::q($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        foreach ($columns as $column) {
            self::assertIdentifier((string) $column);
        }
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`{$c}`", $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );
        self::q($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdentifier($table);
        $sets = [];
        foreach (array_keys($data) as $column) {
            self::assertIdentifier((string) $column);
            $sets[] = "`{$column}` = ?";
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::q($sql, array_merge(array_values($data), $whereParams))->rowCount();
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new InvalidArgumentException('Invalid database identifier.');
        }
    }
}
