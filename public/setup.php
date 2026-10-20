<?php
require_once __DIR__ . '/../src/Auth/AuthService.php';

$auth = new AuthService();

// If an administrator is already established, setup is locked
if ($auth->hasAnyAdmin()) {
    header("Location: /login.php");
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!$auth->validateCsrfToken($csrf)) {
        $error = "Security validation token expired. Please refresh and try again.";
    } elseif (empty($fullName) || empty($phone) || empty($password)) {
        $error = "Full Name, Phone Number, and Password are required.";
    } elseif ($password !== $passwordConfirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters in length.";
    } else {
        $result = $auth->registerFirstAdmin($fullName, $phone, $email, $password);
        if ($result['success']) {
            header("Location: /admin/dashboard.php");
            exit;
        } else {
            $error = $result['message'];
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
    <title>Initial System Setup | NTA-Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
                            cyan: '#06b6d4'
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
</head>
<body class="h-full bg-[#0d0f12] text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        
        <!-- Branding Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#14171d] border border-zinc-800 text-neon-lime mb-4 shadow-xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white">Initial System Setup</h1>
            <p class="text-xs font-semibold uppercase tracking-wider text-neon-lime mt-1.5">NTA-Pay &bull; Digital Receipt Architecture</p>
            <p class="text-xs text-slate-400 mt-2">No administrator detected. Establish the primary Business Owner account to activate the terminal network.</p>
        </div>

        <!-- Setup Card -->
        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-8 shadow-2xl">
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center space-x-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/setup.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Administrator Full Name
                    </label>
                    <input type="text" name="full_name" required placeholder="e.g. Samuel Maina"
                        class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Phone Number (M-Pesa)
                        </label>
                        <input type="text" name="phone_number" required placeholder="0712 345 678"
                            class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 mono focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Email Address <span class="text-slate-500 font-normal lowercase">(optional)</span>
                        </label>
                        <input type="email" name="email" placeholder="admin@nairobi-traders.org"
                            class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Master Password
                        </label>
                        <input type="password" name="password" required placeholder="••••••••" minlength="6"
                            class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Confirm Password
                        </label>
                        <input type="password" name="password_confirm" required placeholder="••••••••" minlength="6"
                            class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-bold text-xs uppercase tracking-wider shadow-lg shadow-lime-500/10 transition transform active:scale-[0.99] flex items-center justify-center space-x-2">
                        <span>Initialize System & Access Dashboard</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-[11px] text-slate-600 mt-6 mono">
            Nairobi Traders Association &bull; Secured with SHA-256 / Bcrypt Hashing
        </p>
    </div>
</body>
</html>
