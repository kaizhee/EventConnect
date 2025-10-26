<?php
// src/Support/helpers.php
declare(strict_types=1);

function redirect(string $path): never {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function old(string $key): string {
    return htmlspecialchars($_SESSION['old'][$key] ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_errors(): array {
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors'], $_SESSION['old']);
    return $errors;
}

function set_flash(array $errors, array $old = []): void {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = $old;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_check(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}

function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}