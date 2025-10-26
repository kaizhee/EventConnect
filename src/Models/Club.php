<?php
declare(strict_types=1);

final class Club
{
    public static function all(): array {
        $stmt = Database::pdo()->query('SELECT id, name FROM clubs ORDER BY name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array {
        $stmt = Database::pdo()->prepare('SELECT id, name FROM clubs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function idByName(string $name): ?int {
        $stmt = Database::pdo()->prepare('SELECT id FROM clubs WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public static function create(string $name): int {
        $stmt = Database::pdo()->prepare('INSERT INTO clubs (name) VALUES (?)');
        $stmt->execute([$name]);
        return (int)Database::pdo()->lastInsertId();
    }
}