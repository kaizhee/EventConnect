<?php
// config/config.php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'eventConnect';
const DB_USER = 'root';
const DB_PASS = '';
const APP_URL = 'http://localhost/eventConnect/public'; // no trailing slash change to your path

// Secure session
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',
  'secure' => isset($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../src/Support/helpers.php';
require_once __DIR__ . '/../src/Models/Role.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Services/Auth.php';