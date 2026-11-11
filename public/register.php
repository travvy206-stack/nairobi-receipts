<?php
/**
 * Business Owner (Admin) Registration Portal
 * Allows new business operators to register their administrative account,
 * business/stall name, receiving M-Pesa till/paybill, and access their own portal.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';

$auth = new AuthService();
$db = Database::getInstance()->getConnection();

// If already logged in, redirect to appropriate portal
if ($auth->isAuthenticated()) {
    if ($auth->isAdmin()) {
        header("Location: /admin/dashboard.php");
    } else {
        header("Location: /index.php");
    }
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $storeName = trim($_POST['store_name'] ?? '');
    $mpesaShortcode = trim($_POST['mpesa_shortcode'] ?? '174379');
    $mpesaType = strtolower(trim($_POST['mpesa_type'] ?? 'till'));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

    if (!$auth->validateCsrfToken($csrf)) {
        $error = "Security validation token expired. Please refresh and try again.";
    } elseif (empty($fullName) || empty($cleanPhone) || empty($password)) {
        $error = "Full Name, Mobile Phone Number, and Password are required.";
    } elseif ($password !== $passwordConfirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters in length.";
    } else {
        try {
            // Check if phone or email already registered
            $checkStmt = $db->prepare("SELECT id FROM users WHERE phone_number = ? OR (email IS NOT NULL AND email != '' AND LOWER(email) = LOWER(?))");
            $checkStmt->execute([$cleanPhone, $email]);
            if ($checkStmt->fetch()) {
                $error = "An account with this phone number or email already exists. Please log in instead.";
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $finalStore = !empty($storeName) ? $storeName : ($fullName . "'s Store");
                $finalShortcode = !empty($mpesaShortcode) ? $mpesaShortcode : '174379';

                $stmt = $db->prepare("INSERT INTO users (full_name, phone_number, email, password_hash, role, status, store_name, mpesa_shortcode, mpesa_type) 
                                      VALUES (?, ?, ?, ?, 'admin', 'active', ?, ?, ?)");
                $stmt->execute([
                    $fullName,
                    $cleanPhone,
                    !empty($email) ? strtolower($email) : null,
                    $passwordHash,
                    $finalStore,
                    $finalShortcode,
                    $mpesaType
                ]);
                $newUserId = (int)$db->lastInsertId();

                // Establish login session immediately
                session_regenerate_id(true);
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_phone'] = $cleanPhone;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'admin';
                $_SESSION['logged_in_at'] = time();

                $auth->logAudit($newUserId, "New Business Admin Registered: $fullName ($finalStore)", $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

                header("Location: /admin/dashboard.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Registration error: " . $e->getMessage();
        }
    }
}

$csrfToken = $auth->getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d0f12]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Business Admin | NTA-Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        charcoal: {
                            950: '#0d0f12',
                            900: '#14171d',
                            800: '#1a1e26',
                            700: '#232834',
                        },
                        neon: {
                            lime: '#a3e635',
                            limeHover: '#84cc16',
                            cyan: '#06b6d4',
                            teal: '#14b8a6'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
    </style>
    <link rel="stylesheet" href="/assets/theme.css">
    <script src="/assets/theme.js"></script>
</head>
<body class="min-h-full bg-[#0d0f12] text-slate-100 flex items-center justify-center p-4 py-10 relative">
    
    <!-- Theme Toggle -->
    <div class="fixed top-4 right-4 z-50">
        <button onclick="toggleTheme()" class="theme-toggle-btn p-2 rounded-xl bg-[#14171d] hover:bg-[#1a1e26] text-xs font-semibold text-slate-300 border border-zinc-800 transition flex items-center justify-center shadow-lg" title="Toggle Light/Dark Theme">
            <svg class="w-4 h-4 text-amber-400 theme-icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <svg class="w-4 h-4 text-cyan-400 theme-icon-moon hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
        </button>
    </div>

    <div class="w-full max-w-lg">
        
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-neon-lime/10 border border-neon-lime/30 text-neon-lime mb-3 shadow-xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight">Business Admin Registration</h1>
            <p class="text-xs text-slate-400 mt-1">Register your enterprise or stall to manage sales, POS terminal, and receipts</p>
        </div>

        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <?php if ($error): ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center space-x-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/register.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            Your Full Name <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" name="full_name" required placeholder="e.g. Mark Brandon"
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            Mobile Phone <span class="text-rose-400">*</span>
                        </label>
                        <input type="text" name="phone_number" required placeholder="07XX XXX XXX"
                            value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs font-mono placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Email Address <span class="text-slate-500 font-normal lowercase">(optional)</span>
                    </label>
                    <input type="email" name="email" placeholder="owner@mybusiness.co.ke"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                </div>

                <div class="pt-2 border-t border-zinc-800/80">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                        Business / Store Name
                    </label>
                    <input type="text" name="store_name" placeholder="e.g. Brandon Electronics Stall #104"
                        value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            Receiving M-Pesa Type
                        </label>
                        <select name="mpesa_type" class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-neon-lime">
                            <option value="till">Buy Goods Till</option>
                            <option value="paybill">Paybill Number</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            Till / Paybill Number
                        </label>
                        <input type="text" name="mpesa_shortcode" placeholder="174379"
                            value="<?= htmlspecialchars($_POST['mpesa_shortcode'] ?? '174379') ?>"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs font-mono placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-zinc-800/80">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            Password <span class="text-rose-400">*</span>
                        </label>
                        <input type="password" name="password" required minlength="6" placeholder="Min 6 characters"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                            Confirm Password <span class="text-rose-400">*</span>
                        </label>
                        <input type="password" name="password_confirm" required minlength="6" placeholder="Re-type password"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                    </div>
                </div>

                <button type="submit"
                    class="w-full mt-4 py-3 px-4 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-[#0d0f12] text-xs font-extrabold uppercase tracking-wider transition shadow-lg shadow-lime-500/10 flex items-center justify-center space-x-2">
                    <span>Create Admin Account & Launch</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </button>
            </form>

            <div class="mt-6 text-center pt-4 border-t border-zinc-800/80">
                <p class="text-xs text-slate-400">
                    Already registered? 
                    <a href="/login.php" class="text-neon-lime hover:underline font-bold">Sign in here</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>

