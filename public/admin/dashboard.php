<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$auth = new AuthService();
$auth->requireAdmin('/login.php');

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Telemetry Metrics
$today = date('Y-m-d');

// Revenue Today
$stmtRev = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM receipts WHERE payment_status = 'completed' AND DATE(created_at) = ?");
$stmtRev->execute([$today]);
$revenueToday = (float)$stmtRev->fetchColumn();

// Total Volume (All-Time Completed)
$stmtAllRev = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM receipts WHERE payment_status = 'completed'");
$totalRevenueAll = (float)$stmtAllRev->fetchColumn();

// Transactions Count Today
$stmtCount = $db->prepare("SELECT COUNT(*) FROM receipts WHERE DATE(created_at) = ?");
$stmtCount->execute([$today]);
$txCountToday = (int)$stmtCount->fetchColumn();

// All-Time Completed Transactions
$totalTxCompleted = (int)$db->query("SELECT COUNT(*) FROM receipts WHERE payment_status = 'completed'")->fetchColumn();

// Active Traders Count
$activeTradersCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'trader' AND status = 'active'")->fetchColumn();
$totalTradersCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'trader'")->fetchColumn();

// Filtering Parameters
$filterTrader = $_GET['trader_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDate = $_GET['date'] ?? '';

$query = "SELECT r.*, u.full_name AS trader_name, c.phone_number AS customer_phone 
          FROM receipts r 
          JOIN users u ON r.trader_id = u.id 
          JOIN customers c ON r.customer_id = c.id 
          WHERE 1=1";
$params = [];

if (!empty($filterTrader)) {
    $query .= " AND r.trader_id = ?";
    $params[] = (int)$filterTrader;
}
if (!empty($filterStatus)) {
    $query .= " AND r.payment_status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterDate)) {
    $query .= " AND DATE(r.created_at) = ?";
    $params[] = $filterDate;
}

$query .= " ORDER BY r.id DESC LIMIT 40";
$stmtTx = $db->prepare($query);
$stmtTx->execute($params);
$transactions = $stmtTx->fetchAll();

// Traders List for Dropdown and Staff Manager
$traders = $db->query("SELECT id, full_name, phone_number, email, status, store_name, mpesa_shortcode, mpesa_type, created_at FROM users WHERE role = 'trader' ORDER BY full_name ASC")->fetchAll();

// Audit Logs (Latest 12 entries)
$auditLogs = $db->query("SELECT a.*, u.full_name, u.role FROM audit_log a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 12")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d0f12]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Admin Dashboard | NTA-Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        charcoal: {
                            950: '#0d0f12',
                            900: '#14171d',
                            850: '#171b22',
                            800: '#1a1e26',
                            700: '#232834',
                            600: '#2f3646',
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
<body class="min-h-full bg-[#0d0f12] text-slate-100 flex flex-col antialiased">

    <!-- Top Navigation -->
    <header class="bg-[#14171d] border-b border-zinc-800/80 sticky top-0 z-40 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-neon-lime/10 border border-neon-lime/30 text-neon-lime flex items-center justify-center font-black text-xs shadow-inner">
                    NTA
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-extrabold text-white tracking-wide">EXECUTIVE PORTAL</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neon-cyan/10 text-neon-cyan border border-neon-cyan/20 mono">ADMIN CONTROL</span>
                    </div>
                    <p class="text-[11px] text-slate-400">Owner: <span class="text-white font-semibold"><?= htmlspecialchars($currentUser['full_name']) ?></span></p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <a href="/index.php" class="px-3 py-1.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-xs font-semibold text-neon-lime border border-zinc-800 transition flex items-center space-x-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    <span>Launch POS Terminal</span>
                </a>
                <a href="/admin/audit_logs.php" class="px-3 py-1.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-xs font-semibold text-slate-300 border border-zinc-800 transition">
                    Forensic Logs
                </a>
                <div class="h-4 w-px bg-zinc-800"></div>
                <a href="/logout.php" class="p-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 transition text-xs font-semibold" title="Sign Out">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        <!-- Telemetry Metric Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 shadow-xl relative overflow-hidden">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Shift Revenue (Today)</span>
                <div class="mt-2 flex items-baseline space-x-2">
                    <span class="text-2xl font-black text-neon-lime tracking-tight mono">
                        KES <?= number_format($revenueToday, 2) ?>
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 mt-2 font-mono flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-lime mr-1.5"></span>
                    Lifetime: KES <?= number_format($totalRevenueAll, 2) ?>
                </p>
            </div>

            <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 shadow-xl">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Transactions</span>
                <div class="mt-2 flex items-baseline space-x-2">
                    <span class="text-2xl font-black text-white tracking-tight mono"><?= $txCountToday ?></span>
                    <span class="text-xs text-slate-400">Today</span>
                </div>
                <p class="text-[11px] text-neon-cyan mt-2 font-mono flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-cyan mr-1.5"></span>
                    <?= $totalTxCompleted ?> Verified Receipts Total
                </p>
            </div>

            <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 shadow-xl">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Counter Traders</span>
                <div class="mt-2 flex items-baseline space-x-2">
                    <span class="text-2xl font-black text-white tracking-tight mono"><?= $activeTradersCount ?></span>
                    <span class="text-xs text-slate-400">Active / <?= $totalTradersCount ?> Registered</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-2 font-mono flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-lime mr-1.5"></span>
                    Individual Tills & Paybills
                </p>
            </div>

            <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 shadow-xl">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Safaricom Daraja Status</span>
                <div class="mt-2 flex items-baseline space-x-2">
                    <span class="text-2xl font-black text-neon-lime tracking-tight mono">ACTIVE</span>
                </div>
                <p class="text-[11px] text-slate-500 mt-2 font-mono flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-neon-lime mr-1.5 animate-pulse"></span>
                    STK Push &bull; Real Callback Gateway
                </p>
            </div>
        </section>

        <!-- Real-Time Transaction Monitor -->
        <section class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 shadow-xl">
            <div class="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-zinc-800 gap-4">
                <div>
                    <h2 class="text-base font-extrabold text-white flex items-center space-x-2">
                        <span>REAL-TIME TRANSACTION MONITOR</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neon-lime/10 text-neon-lime border border-neon-lime/20 mono">LIVE TELEMETRY</span>
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">Audit transactions, filter by counter operator, date, or M-Pesa payment status</p>
                </div>

                <!-- Filters -->
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <select name="trader_id" class="bg-[#0d0f12] border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-neon-lime">
                        <option value="">All Traders</option>
                        <?php foreach ($traders as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $filterTrader == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['store_name'] ?? 'Stall') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" class="bg-[#0d0f12] border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-neon-lime">
                        <option value="">All Statuses</option>
                        <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="timed_out" <?= $filterStatus === 'timed_out' ? 'selected' : '' ?>>Timed Out</option>
                    </select>

                    <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
                        class="bg-[#0d0f12] border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-neon-lime">

                    <button type="submit" class="px-3.5 py-2 bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-bold rounded-xl text-xs transition">
                        Filter
                    </button>
                    <?php if (!empty($filterTrader) || !empty($filterStatus) || !empty($filterDate)): ?>
                        <a href="/admin/dashboard.php" class="px-3 py-2 bg-[#1a1e26] hover:bg-[#232834] text-slate-400 rounded-xl text-xs transition">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Transaction Table -->
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-zinc-800/80 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-3">Receipt ID</th>
                            <th class="py-3 px-3">Timestamp</th>
                            <th class="py-3 px-3">Operator & Stall</th>
                            <th class="py-3 px-3">Customer Phone</th>
                            <th class="py-3 px-3 text-right">Amount</th>
                            <th class="py-3 px-3 text-center">Settlement Status</th>
                            <th class="py-3 px-3 text-right">Digital Artifacts</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-xs">
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="7" class="py-10 text-center text-slate-500 font-mono">
                                    No transaction records found matching the current criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr class="hover:bg-[#1a1e26]/30 transition">
                                    <td class="py-3 px-3 font-semibold text-white mono">
                                        <a href="/verify.php?id=<?= urlencode($tx['receipt_number']) ?>" target="_blank" class="hover:text-neon-cyan transition">
                                            #<?= htmlspecialchars($tx['receipt_number']) ?>
                                        </a>
                                        <?php if (!empty($tx['mpesa_receipt_number'])): ?>
                                            <span class="block text-[10px] text-neon-cyan font-mono"><?= htmlspecialchars($tx['mpesa_receipt_number']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-slate-400 mono text-[11px]">
                                        <?= date('M d, H:i:s', strtotime($tx['created_at'])) ?>
                                    </td>
                                    <td class="py-3 px-3 font-medium text-slate-200">
                                        <?= htmlspecialchars($tx['trader_name']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-slate-400 mono">
                                        <?= htmlspecialchars($tx['customer_phone']) ?>
                                    </td>
                                    <td class="py-3 px-3 text-right font-black text-white mono">
                                        KES <?= number_format($tx['total_amount'], 2) ?>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <?php if ($tx['payment_status'] === 'completed'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-neon-lime/10 text-neon-lime border border-neon-lime/30 mono">
                                                [ COMPLETED ]
                                            </span>
                                        <?php elseif ($tx['payment_status'] === 'pending'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30 mono">
                                                [ PENDING ]
                                            </span>
                                        <?php elseif ($tx['payment_status'] === 'timed_out'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30 mono">
                                                [ TIMED OUT ]
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30 mono">
                                                [ <?= strtoupper($tx['payment_status']) ?> ]
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-3 text-right space-x-2">
                                        <?php if (!empty($tx['pdf_path']) && $tx['payment_status'] === 'completed'): ?>
                                            <a href="/storage.php?file=<?= basename($tx['pdf_path']) ?>" target="_blank"
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg bg-[#1a1e26] hover:bg-[#232834] text-[11px] font-semibold text-slate-200 border border-zinc-800 transition">
                                                PDF
                                            </a>
                                            <a href="/verify.php?id=<?= urlencode($tx['receipt_number']) ?>" target="_blank"
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg bg-neon-lime/10 hover:bg-neon-lime/20 text-[11px] font-semibold text-neon-lime border border-neon-lime/20 transition">
                                                QR View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-600 text-[11px] italic font-mono">No Artifact</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Two Columns: Employee Trader Management & Audit Stream -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Trader User Management Section -->
            <section class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                        <div>
                            <h2 class="text-base font-extrabold text-white">TRADER ACCOUNT MANAGEMENT</h2>
                            <p class="text-xs text-slate-400 mt-0.5">Authorize counter employees and bind individual M-Pesa Till / Paybills</p>
                        </div>
                        <button onclick="document.getElementById('modal-add-trader').classList.remove('hidden')"
                            class="px-3.5 py-2 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 text-xs font-bold transition flex items-center space-x-1 shadow-md shadow-lime-500/10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            <span>Add Employee</span>
                        </button>
                    </div>

                    <div class="mt-4 divide-y divide-zinc-800/80">
                        <?php if (empty($traders)): ?>
                            <p class="py-8 text-center text-xs text-slate-500">No trader staff accounts created yet. Click "Add Employee" above.</p>
                        <?php else: ?>
                            <?php foreach ($traders as $trader): ?>
                                <div class="py-3.5 flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-bold text-white text-xs"><?= htmlspecialchars($trader['full_name']) ?></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold mono <?= $trader['status'] === 'active' ? 'bg-neon-lime/10 text-neon-lime border border-neon-lime/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                                <?= strtoupper($trader['status']) ?>
                                            </span>
                                        </div>
                                        <div class="mt-1 flex items-center space-x-2 text-[11px] text-slate-400 mono">
                                            <span><?= htmlspecialchars($trader['store_name'] ?? 'Stall') ?></span>
                                            <span class="text-zinc-600">&bull;</span>
                                            <span class="text-neon-cyan font-bold"><?= strtoupper($trader['mpesa_type'] ?? 'till') ?> #<?= htmlspecialchars($trader['mpesa_shortcode'] ?? '174379') ?></span>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <button onclick="toggleTrader(<?= $trader['id'] ?>, '<?= $trader['status'] === 'active' ? 'disabled' : 'active' ?>')"
                                            class="px-2.5 py-1 text-[11px] font-semibold rounded-lg <?= $trader['status'] === 'active' ? 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-neon-lime/10 hover:bg-neon-lime/20 text-neon-lime border border-neon-lime/30' ?> transition">
                                            <?= $trader['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                        <button onclick="resetPassword(<?= $trader['id'] ?>)"
                                            class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-[#1a1e26] hover:bg-[#232834] text-slate-300 border border-zinc-800 transition">
                                            Reset Password
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Forensic Audit Feed -->
            <section class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 shadow-xl">
                <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                    <div>
                        <h2 class="text-base font-extrabold text-white">FORENSIC AUDIT TRAIL</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Immutable tracking of operator actions and settlement events</p>
                    </div>
                    <a href="/admin/audit_logs.php" class="text-xs text-neon-cyan hover:underline font-semibold transition">
                        Full Archive &rarr;
                    </a>
                </div>

                <div class="mt-4 space-y-2.5">
                    <?php if (empty($auditLogs)): ?>
                        <p class="text-xs text-slate-500 py-6 text-center">No audit trail entries recorded yet.</p>
                    <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <div class="p-3 rounded-2xl bg-[#0d0f12] border border-zinc-800 text-xs">
                                <div class="flex items-center justify-between text-[11px] text-slate-400 mono">
                                    <span><?= date('H:i:s - M d', strtotime($log['timestamp'])) ?></span>
                                    <span class="text-slate-500"><?= htmlspecialchars($log['ip_address']) ?></span>
                                </div>
                                <p class="text-slate-200 font-medium mt-1">
                                    <?= htmlspecialchars($log['action_performed']) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

    <!-- Modal: Register Employee Trader -->
    <div id="modal-add-trader" class="fixed inset-0 z-50 bg-[#0d0f12]/90 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl">
            <h3 class="text-lg font-extrabold text-white mb-1">Create Trader Account</h3>
            <p class="text-xs text-slate-400 mb-5">Provision terminal credentials and configure receiving M-Pesa Till or Paybill.</p>

            <form onsubmit="submitNewTrader(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Full Name</label>
                    <input type="text" id="trader_name" required placeholder="e.g. John Ochieng"
                        class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs focus:ring-2 focus:ring-neon-lime focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Mobile Phone</label>
                        <input type="text" id="trader_phone" required placeholder="0711 223 344"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs mono focus:ring-2 focus:ring-neon-lime focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Store / Stall</label>
                        <input type="text" id="trader_store" required placeholder="Stall #42" value="Stall #104"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs focus:ring-2 focus:ring-neon-lime focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Account Type</label>
                        <select id="trader_type" class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs focus:ring-2 focus:ring-neon-lime focus:outline-none">
                            <option value="till">Buy Goods Till</option>
                            <option value="paybill">Paybill</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Till / Paybill Number</label>
                        <input type="text" id="trader_shortcode" required placeholder="174379" value="174379"
                            class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs mono focus:ring-2 focus:ring-neon-lime focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Initial Password</label>
                    <input type="password" id="trader_pass" required placeholder="••••••••" minlength="6"
                        class="w-full px-3.5 py-2.5 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs focus:ring-2 focus:ring-neon-lime focus:outline-none">
                </div>

                <div class="pt-3 flex space-x-2">
                    <button type="button" onclick="document.getElementById('modal-add-trader').classList.add('hidden')"
                        class="flex-1 py-3 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-slate-300 text-xs font-semibold border border-zinc-800 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 py-3 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 text-xs font-bold uppercase tracking-wider shadow-md shadow-lime-500/10 transition">
                        Register Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function submitNewTrader(e) {
            e.preventDefault();
            const name = document.getElementById('trader_name').value.trim();
            const phone = document.getElementById('trader_phone').value.trim();
            const store = document.getElementById('trader_store').value.trim();
            const type = document.getElementById('trader_type').value;
            const shortcode = document.getElementById('trader_shortcode').value.trim();
            const password = document.getElementById('trader_pass').value;

            try {
                const res = await fetch('/admin/traders_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'create_trader', 
                        full_name: name, 
                        phone_number: phone, 
                        store_name: store,
                        mpesa_type: type,
                        mpesa_shortcode: shortcode,
                        password: password
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert('Trader employee account created successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        }

        async function toggleTrader(id, status) {
            const actionVerb = status === 'active' ? 'activate' : 'deactivate';
            if (!confirm(`Are you sure you want to ${actionVerb} this trader account?`)) return;
            try {
                const res = await fetch('/admin/traders_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'toggle_status', trader_id: id, status})
                });
                const data = await res.json();
                if (data.status === 'success') {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        }

        async function resetPassword(id) {
            const newPass = prompt("Enter new password for this trader (minimum 6 characters):");
            if (!newPass) return;
            if (newPass.length < 6) {
                alert("Password must be at least 6 characters.");
                return;
            }
            try {
                const res = await fetch('/admin/traders_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'reset_password', trader_id: id, new_password: newPass})
                });
                const data = await res.json();
                alert(data.message);
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        }
    </script>
</body>
</html>
