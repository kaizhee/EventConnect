<?php
// src/Models/Role.php
declare(strict_types=1);

final class Role
{
    // Role ID constants — keep in sync with DB
    public const STUDENT           = 1;
    public const STUDENT_COUNCIL   = 2;
    public const STUDENT_AFFAIR    = 3;
    public const CLUB_ADMIN        = 4;
    
    public static function all(): array {
        $stmt = Database::pdo()->query('SELECT id, name FROM roles ORDER BY id');
        return $stmt->fetchAll();
    }

    public static function existsById(int $id): bool {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM roles WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}