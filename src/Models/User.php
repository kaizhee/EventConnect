<?php
// src/Models/User.php
declare(strict_types=1);

final class User
{
    public int $id;
    public string $name;
    public string $email;
    public string $password_hash;
    public int $role_id;
    public ?int $club_id; // NEW: link to clubs table

    public static function findByEmail(string $email): ?self {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    public static function findById(int $id): ?self {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    public static function create(
        string $name,
        string $email,
        string $password_hash,
        int $role_id,
        ?int $club_id = null
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (name, email, password_hash, role_id, club_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $password_hash, $role_id, $club_id]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function roleName(int $roleId): string {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            foreach (Role::all() as $r) {
                $cache[(int)$r['id']] = $r['name'];
            }
        }
        return $cache[$roleId] ?? 'unknown';
    }

    private static function fromRow(array $row): self {
        $u = new self();
        $u->id = (int) $row['id'];
        $u->name = $row['name'];
        $u->email = $row['email'];
        $u->password_hash = $row['password_hash'];
        $u->role_id = (int) $row['role_id'];
        $u->club_id = isset($row['club_id']) ? (int)$row['club_id'] : null;
        return $u;
    }

    // Helper: check if user is a Club Admin
    public function isClubAdmin(): bool {
        $stmt = Database::pdo()->prepare("
            SELECT 1
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.slug = 'club_admin'
            LIMIT 1
        ");
        $stmt->execute([$this->id]);
        return (bool) $stmt->fetchColumn();
    }
}