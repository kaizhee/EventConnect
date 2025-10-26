<?php
// src/Services/Auth.php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class Auth
{
    public static function register(array $data): array {
        $errors = [];

        $name = trim($data['name'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $confirm = $data['confirm_password'] ?? '';
        $role_id = (int) ($data['role_id'] ?? 0);
        $csrf = $data['_csrf'] ?? '';
        $consent = $data['consent'] ?? '';
        if ($consent !== 'on') {
            $errors['consent'] = 'You must agree to the terms and conditions.';
        }

        if (!csrf_check($csrf)) {
            $errors['csrf'] = 'Invalid security token. Please try again.';
        }

        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format.';
        } elseif (User::findByEmail($email)) {
            $errors['email'] = 'Email is already registered.';
        }

        $passLen = strlen($password);
        if ($passLen < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'Password must be 8+ chars and include letters and numbers.';
        }
        if ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($role_id <= 0 || !Role::existsById($role_id)) {
            $errors['role_id'] = 'Please select a valid role.';
        }
        
        if (empty($errors)) {
            if ($role_id === Role::STUDENT) {
                $studentDomain = '@gmail.com';
                if (stripos($email, $studentDomain) !== strlen($email) - strlen($studentDomain)) {
                    $errors['email'] = 'Student accounts must use institution email.';
                }
            }
            elseif ($role_id === Role::STUDENT_AFFAIR) {
                $affairDomain = '@student.newinti.edu.my';
                if (stripos($email, $affairDomain) !== strlen($email) - strlen($affairDomain)) {
                    $errors['email'] = 'Student Affair Department accounts must use an organization email.';
                }
            }
            else {
                // Block Student Council & Club Admin from self-registration
                $errors['role_id'] = 'You are not allowed to register directly with this role.';
            }
        }


        if ($errors) {
            return $errors;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = User::create($name, $email, $hash, $role_id);

        // Also insert into user_roles for multi-role support
        $stmtRole = Database::pdo()->prepare(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)"
        );
        $stmtRole->execute([$userId, $role_id]);

        // Generate OTP
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

        $stmt = Database::pdo()->prepare(
            'INSERT INTO email_verifications (user_id, otp_code, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $otp, $expires]);

        // For local testing without email setup:
        $_SESSION['debug_otp'] = $otp; // echo this in verify.php to test

        // Send OTP via Gmail SMTP using PHPMailer
        require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../PHPMailer/SMTP.php';
        require_once __DIR__ . '/../PHPMailer/Exception.php';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tangkaizhe8330@gmail.com'; // Gmail address
            $mail->Password   = 'rowutuweausoqqsp'; // 16-char app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('tangkaizhe8330@gmail.com', 'EventConnect');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Your EventConnect Verification Code';
            $mail->Body    = "<p>Your verification code is: <strong>{$otp}</strong></p><p>It will expire in 10 minutes.</p>";

            $mail->send();
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
        }

        // Store pending user ID and redirect to verification page
        $_SESSION['pending_user_id'] = $userId;
        redirect('verify.php');
    }

    public static function login(array $data): array {
        $errors = [];

        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';
        $csrf = $data['_csrf'] ?? '';

        if (!csrf_check($csrf)) {
            $errors['csrf'] = 'Invalid security token. Please try again.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors) return $errors;

        $user = User::findByEmail($email);
        if (!$user || !password_verify($password, $user->password_hash)) {
            $errors['email'] = 'Invalid credentials.';
            return $errors;
        }

        // Check if email is verified
        $stmt = Database::pdo()->prepare(
            'SELECT verified FROM email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$user->id]);
        $verified = $stmt->fetchColumn();

        if (!$verified) {
            $errors['email'] = 'Please verify your email before logging in.';
            return $errors;
        }

        $_SESSION['user_id'] = $user->id;
        session_regenerate_id(true);
        return [];
    }

    public static function user($fresh = false) {
    if (!isset($_SESSION['user_id'])) return null;

    if ($fresh || !isset($_SESSION['user'])) {
        $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['user'] = $stmt->fetch(PDO::FETCH_OBJ);
    }

    return $_SESSION['user'];
}

    public static function requireLogin(): void {
        if (!self::user()) {
            redirect('login.php');
        }
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login.php');
    }
        public static function hasRole(string $slug): bool {
        $user = self::user();
        if (!$user) return false;

        // Check via user_roles table
        $stmt = Database::pdo()->prepare("
            SELECT COUNT(*) 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.slug = ?
        ");
        $stmt->execute([$user->id, $slug]);
        return $stmt->fetchColumn() > 0;
    }
}