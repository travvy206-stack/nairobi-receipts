<?php
require_once __DIR__ . '/../src/Auth/AuthService.php';

$auth = new AuthService();
$error = null;

// Initial System Setup Check: If zero administrators exist, redirect to onboarding
if (!$auth->hasAnyAdmin()) {
    header("Location: /setup.php");
    exit;
}

// If already logged in, redirect to appropriate role portal
if ($auth->isAuthenticated()) {
    if ($auth->isAdmin()) {
        header("Location: /admin/dashboard.php");
    } else {
        header("Location: /index.php");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!$auth->validateCsrfToken($csrf)) {
        $error = "Security token mismatch. Please reload the page.";
    } elseif (empty($identifier) || empty($password)) {
        $error = "Please enter both your registered phone/email and password.";
    } else {
        $result = $auth->login($identifier, $password);
        if ($result['success']) {
            if ($result['role'] === 'admin') {
                header("Location: /admin/dashboard.php");
            } else {
                header("Location: /index.php");
            }
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
    <title>Sign In | NTA-Pay Terminal Portal</title>
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
</head>
<body class="h-full bg-[#0d0f12] text-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        
        <!-- Brand Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#14171d] border border-zinc-800 text-neon-lime mb-4 shadow-xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-white">NTA-Pay</h1>
            <p class="text-xs font-semibold uppercase tracking-wider text-neon-lime mt-1">Digital Receipt Management System</p>
            <p class="text-xs text-slate-400 mt-2">Sign in to authenticate your counter terminal or administrative session</p>
        </div>

        <!-- Authentication Card -->
        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-8 shadow-2xl">
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center space-x-3">
                    <svg class="w-5 h-5 flex-shrink-0 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div>
                    <label for="identifier" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Phone Number or Email
                    </label>
                    <div class="relative">
                        <input type="text" id="identifier" name="identifier" required
                            placeholder="e.g. 0712 345 678 or staff@nairobi-traders.org"
                            class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Password
                    </label>
                    <input type="password" id="password" name="password" required
                        placeholder="••••••••"
                        class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-bold text-xs uppercase tracking-wider shadow-lg shadow-lime-500/10 transition transform active:scale-[0.99] flex items-center justify-center space-x-2">
                        <span>Authenticate Terminal</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-[11px] text-slate-600 mono">
                Nairobi Traders Association &bull; Pay First, Receipt Comes &bull; M-Pesa Enforced
            </p>
        </div>
    </div>
</body>
</html>
