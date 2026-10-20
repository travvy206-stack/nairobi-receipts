<?php
/**
 * Production Authentication & Access Control Service
 * Nairobi Traders Association (NTA-Pay)
 * Enforces Strict RBAC, Bcrypt Password Verification, and Forensic Audit Trails
 */

require_once __DIR__ . '/../../config/db.php';

class AuthService {
    private PDO $db;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure cookie flags
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            session_start();
        }
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Check if at least one Administrator account exists in the database
     */
    public function hasAnyAdmin(): bool {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            return (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Initial System Onboarding: Register the primary Business Owner (Admin)
     * Permitted ONLY when zero administrators currently exist in the database.
     */
    public function registerFirstAdmin(string $fullName, string $phoneNumber, ?string $email, string $password): array {
        if ($this->hasAnyAdmin()) {
            return ['success' => false, 'message' => 'System initialization already completed. An administrator already exists.'];
        }

        $fullName = trim($fullName);
        $phoneNumber = preg_replace('/[^0-9+]/', '', trim($phoneNumber));
        $email = !empty($email) ? strtolower(trim($email)) : null;

        if (empty($fullName) || empty($phoneNumber) || empty($password)) {
            return ['success' => false, 'message' => 'Full Name, Phone Number, and Password are required.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $this->db->prepare("INSERT INTO users (full_name, phone_number, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
            $stmt->execute([$fullName, $phoneNumber, $email, $passwordHash]);
            $adminId = (int)$this->db->lastInsertId();

            // Establish secure session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $adminId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_phone'] = $phoneNumber;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'admin';
            $_SESSION['logged_in_at'] = time();

            $this->logAudit($adminId, "Initial System Setup: Primary Administrator Registered ($fullName)", $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

            return ['success' => true, 'message' => 'Primary Administrator created successfully.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error during setup: ' . $e->getMessage()];
        }
    }

    /**
     * Production User Authentication (by Phone Number or Email)
     */
    public function login(string $identifier, string $password): array {
        $identifier = trim($identifier);
        $cleanPhone = preg_replace('/[^0-9+]/', '', $identifier);

        $stmt = $this->db->prepare("SELECT id, full_name, phone_number, email, password_hash, role, status 
                                    FROM users 
                                    WHERE phone_number = ? OR phone_number = ? OR (email IS NOT NULL AND LOWER(email) = LOWER(?))");
        $stmt->execute([$identifier, $cleanPhone, $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials. Please verify your phone/email and password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account access has been deactivated. Please contact your system administrator.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->logAudit((int)$user['id'], "Failed login attempt (invalid password) for identifier: $identifier", $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            return ['success' => false, 'message' => 'Invalid credentials. Please verify your phone/email and password.'];
        }

        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_phone'] = $user['phone_number'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in_at'] = time();

        $this->logAudit((int)$user['id'], "Staff authenticated to terminal ({$user['role']} session established)", $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        return [
            'success' => true,
            'role' => $user['role'],
            'user' => [
                'id' => (int)$user['id'],
                'full_name' => $user['full_name'],
                'phone_number' => $user['phone_number'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    }

    /**
     * Check if user is logged in
     */
    public function isAuthenticated(): bool {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Current authenticated user array
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return [
            'id' => (int)$_SESSION['user_id'],
            'full_name' => $_SESSION['user_name'] ?? 'Staff',
            'phone_number' => $_SESSION['user_phone'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'trader'
        ];
    }

    public function isAdmin(): bool {
        return $this->isAuthenticated() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public function isTrader(): bool {
        return $this->isAuthenticated() && (($_SESSION['user_role'] ?? '') === 'trader' || ($_SESSION['user_role'] ?? '') === 'admin');
    }

    public function requireAuth(string $redirectUrl = '/login.php'): void {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    public function requireAdmin(string $redirectUrl = '/login.php'): void {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectUrl");
            exit;
        }
        if (!$this->isAdmin()) {
            http_response_code(403);
            header("Content-Type: text/html; charset=UTF-8");
            die("<!DOCTYPE html><html><head><title>Access Forbidden</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-[#0d0f12] text-slate-100 flex items-center justify-center min-h-screen p-4'><div class='max-w-md w-full bg-[#14171d] border border-zinc-800 rounded-2xl p-8 text-center shadow-2xl'><div class='w-14 h-14 mx-auto mb-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center font-bold text-xl'>!</div><h1 class='text-lg font-bold text-white'>Access Denied (403)</h1><p class='text-xs text-slate-400 mt-2'>Executive Administrator privileges are required to access this resource.</p><div class='mt-6'><a href='/index.php' class='px-4 py-2.5 rounded-xl bg-lime-500 text-slate-950 font-bold text-xs hover:bg-lime-400 transition'>Return to POS Terminal</a></div></div></body></html>");
        }
    }

    public function requireTrader(string $redirectUrl = '/login.php'): void {
        if (!$this->isAuthenticated()) {
            header("Location: $redirectUrl");
            exit;
        }
    }

    public function logout(): void {
        if (isset($_SESSION['user_id'])) {
            $this->logAudit((int)$_SESSION['user_id'], 'Staff terminated active session (Logout)', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public function logAudit(?int $userId, string $action, ?string $ip = null): void {
        try {
            $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $this->db->prepare("INSERT INTO audit_log (user_id, action_performed, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $action, $ip]);
        } catch (PDOException $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }

    public function getCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
