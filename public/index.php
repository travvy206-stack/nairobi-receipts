<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Auth/AuthService.php';

$auth = new AuthService();
$auth->requireTrader('/login.php');

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Fetch trader's active M-Pesa account configuration
$stmtTrader = $db->prepare("SELECT store_name, mpesa_shortcode, mpesa_type FROM users WHERE id = ?");
$stmtTrader->execute([$currentUser['id']]);
$traderConfig = $stmtTrader->fetch() ?: [];

$activeShortcode = $traderConfig['mpesa_shortcode'] ?? '174379';
$activeType = strtoupper($traderConfig['mpesa_type'] ?? 'till');
$activeStore = $traderConfig['store_name'] ?? 'Stall #104';

// Today's verified sales metrics for this trader
$today = date('Y-m-d');
$stmtCount = $db->prepare("SELECT COUNT(*) FROM receipts WHERE trader_id = ? AND DATE(created_at) = ? AND payment_status = 'completed'");
$stmtCount->execute([$currentUser['id'], $today]);
$todaySalesCount = (int)$stmtCount->fetchColumn();

// Total volume today
$stmtVol = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM receipts WHERE trader_id = ? AND DATE(created_at) = ? AND payment_status = 'completed'");
$stmtVol->execute([$currentUser['id'], $today]);
$todayVolume = (float)$stmtVol->fetchColumn();

// Fetch recent completed receipts for this trader
$stmtRecent = $db->prepare("SELECT r.*, c.phone_number AS customer_phone 
                           FROM receipts r 
                           JOIN customers c ON r.customer_id = c.id 
                           WHERE r.trader_id = ? 
                           ORDER BY r.id DESC LIMIT 8");
$stmtRecent->execute([$currentUser['id']]);
$recentReceipts = $stmtRecent->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d0f12]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal | NTA-Pay</title>
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
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.1); opacity: 0.3; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .pulse-ring { animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    </style>
</head>
<body class="min-h-full bg-[#0d0f12] text-slate-100 flex flex-col antialiased">

    <!-- Header Navigation -->
    <header class="bg-[#14171d] border-b border-zinc-800/80 sticky top-0 z-30 shadow-lg">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-neon-lime/10 border border-neon-lime/30 text-neon-lime flex items-center justify-center font-black text-xs shadow-inner">
                    NTA
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-extrabold text-white tracking-wide">POS TERMINAL</span>
                        <!-- Dynamic Destination Account Badge -->
                        <button onclick="openAccountModal()" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#1a1e26] hover:bg-[#232834] text-neon-lime border border-neon-lime/30 mono flex items-center space-x-1 transition" title="Click to view or edit destination M-Pesa account">
                            <span class="w-1.5 h-1.5 rounded-full bg-neon-lime"></span>
                            <span id="badge-account-info"><?= $activeType ?> #<?= htmlspecialchars($activeShortcode) ?></span>
                            <span class="text-slate-400">✎</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400">Operator: <span class="text-white font-semibold"><?= htmlspecialchars($currentUser['full_name']) ?></span> &bull; <span id="badge-store-name" class="text-slate-300"><?= htmlspecialchars($activeStore) ?></span></p>
                </div>
            </div>

            <div class="flex items-center space-x-2">
                <button onclick="openAccountModal()" class="px-2.5 py-1.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-xs font-semibold text-slate-300 border border-zinc-800 transition flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5 text-neon-lime" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    <span>M-Pesa Setup</span>
                </button>
                <?php if ($auth->isAdmin()): ?>
                    <a href="/admin/dashboard.php" class="px-3 py-1.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-xs font-semibold text-neon-cyan border border-zinc-800 transition flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span>Admin</span>
                    </a>
                <?php endif; ?>
                <a href="/logout.php" class="px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-semibold border border-rose-500/30 transition flex items-center space-x-1">
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Workspace -->
    <main class="flex-1 max-w-6xl w-full mx-auto p-4 sm:p-6 space-y-6">

        <!-- Operational Telemetry Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-[#14171d] border border-zinc-800 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Shift Receipts</span>
                    <p class="text-xl font-black text-white mono mt-1"><?= $todaySalesCount ?> <span class="text-xs font-normal text-slate-400">Settled</span></p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-[#1a1e26] border border-zinc-800 flex items-center justify-center text-neon-lime">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>

            <div class="bg-[#14171d] border border-zinc-800 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Shift Revenue</span>
                    <p class="text-xl font-black text-neon-lime mono mt-1">KES <?= number_format($todayVolume, 2) ?></p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-[#1a1e26] border border-zinc-800 flex items-center justify-center text-neon-cyan">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>

            <div class="bg-[#14171d] border border-zinc-800 rounded-2xl p-4 flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Destination Account</span>
                    <div class="flex items-center space-x-1.5 mt-1">
                        <span class="w-2 h-2 rounded-full bg-neon-lime animate-pulse"></span>
                        <span id="label-destination" class="text-xs font-bold text-white mono"><?= $activeType ?> #<?= htmlspecialchars($activeShortcode) ?></span>
                    </div>
                </div>
                <button onclick="resetCart()" class="px-3 py-1.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-xs font-semibold text-slate-300 border border-zinc-800 transition">
                    Clear Cart
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Quick Catalog Panel (Left 5 cols) -->
            <div class="lg:col-span-5 space-y-4">
                <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 shadow-xl">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-800/80 mb-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300">Fast Catalog</h2>
                        <span class="text-[10px] text-slate-500 font-mono">Tap to add</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <button onclick="addItem('Maize Flour 2kg', 210)" class="p-3 rounded-2xl bg-[#1a1e26] hover:bg-[#232834] border border-zinc-800/80 hover:border-neon-lime/40 text-left transition flex flex-col group">
                            <span class="font-bold text-white group-hover:text-neon-lime transition">Maize Flour 2kg</span>
                            <span class="text-neon-lime font-mono text-[11px] mt-1">KES 210.00</span>
                        </button>
                        <button onclick="addItem('Cooking Oil 1L', 320)" class="p-3 rounded-2xl bg-[#1a1e26] hover:bg-[#232834] border border-zinc-800/80 hover:border-neon-lime/40 text-left transition flex flex-col group">
                            <span class="font-bold text-white group-hover:text-neon-lime transition">Cooking Oil 1L</span>
                            <span class="text-neon-lime font-mono text-[11px] mt-1">KES 320.00</span>
                        </button>
                        <button onclick="addItem('Sugar 1kg', 180)" class="p-3 rounded-2xl bg-[#1a1e26] hover:bg-[#232834] border border-zinc-800/80 hover:border-neon-lime/40 text-left transition flex flex-col group">
                            <span class="font-bold text-white group-hover:text-neon-lime transition">Sugar 1kg</span>
                            <span class="text-neon-lime font-mono text-[11px] mt-1">KES 180.00</span>
                        </button>
                        <button onclick="addItem('Rice 2kg', 350)" class="p-3 rounded-2xl bg-[#1a1e26] hover:bg-[#232834] border border-zinc-800/80 hover:border-neon-lime/40 text-left transition flex flex-col group">
                            <span class="font-bold text-white group-hover:text-neon-lime transition">Rice 2kg</span>
                            <span class="text-neon-lime font-mono text-[11px] mt-1">KES 350.00</span>
                        </button>
                        <button onclick="addItem('Fresh Milk 500ml', 65)" class="p-3 rounded-2xl bg-[#1a1e26] hover:bg-[#232834] border border-zinc-800/80 hover:border-neon-lime/40 text-left transition flex flex-col group">
                            <span class="font-bold text-white group-hover:text-neon-lime transition">Fresh Milk 500ml</span>
                            <span class="text-neon-lime font-mono text-[11px] mt-1">KES 65.00</span>
                        </button>
                        <button onclick="addItem('Tea Leaves 500g', 150)" class="p-3 rounded-2xl bg-[#1a1e26] hover:bg-[#232834] border border-zinc-800/80 hover:border-neon-lime/40 text-left transition flex flex-col group">
                            <span class="font-bold text-white group-hover:text-neon-lime transition">Tea Leaves 500g</span>
                            <span class="text-neon-lime font-mono text-[11px] mt-1">KES 150.00</span>
                        </button>
                    </div>

                    <!-- Custom Item Entry -->
                    <div class="mt-4 pt-3.5 border-t border-zinc-800/80">
                        <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">
                            Add Custom Line Item
                        </label>
                        <div class="flex space-x-2">
                            <input type="text" id="custom_name" placeholder="Item description"
                                class="flex-1 px-3 py-2 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-neon-lime">
                            <input type="number" id="custom_price" placeholder="Price" min="1"
                                class="w-24 px-3 py-2 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 mono focus:outline-none focus:ring-1 focus:ring-neon-lime">
                            <button onclick="addCustomItem()" class="px-3.5 py-2 bg-[#1a1e26] hover:bg-[#232834] text-neon-lime font-bold rounded-xl text-xs border border-zinc-800 transition">
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Order Cart & Settlement (Right 7 cols) -->
            <div class="lg:col-span-7">
                <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 sm:p-6 shadow-2xl flex flex-col h-full justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3.5 border-b border-zinc-800/80">
                            <div>
                                <h2 class="text-sm font-bold text-white uppercase tracking-wider">Current Cart Line Items</h2>
                                <p class="text-[11px] text-slate-500">Routing directly to <span id="cart-destination-label" class="text-neon-lime font-mono"><?= $activeType ?> #<?= htmlspecialchars($activeShortcode) ?></span></p>
                            </div>
                            <span id="cart-item-count" class="text-xs text-neon-cyan font-bold mono px-2.5 py-1 rounded-lg bg-neon-cyan/10 border border-neon-cyan/20">0 items</span>
                        </div>

                        <!-- Line Items List -->
                        <div id="cart-items" class="mt-4 space-y-2 max-h-72 overflow-y-auto pr-1">
                            <!-- Injected dynamically via JavaScript -->
                        </div>
                    </div>

                    <!-- Settlement & Customer Input Section -->
                    <div class="mt-6 pt-5 border-t border-zinc-800/80 space-y-4">
                        <div class="flex items-baseline justify-between bg-[#1a1e26]/60 border border-zinc-800/80 rounded-2xl p-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Payable:</span>
                            <span id="cart-total" class="text-3xl font-black text-neon-lime mono">KES 0.00</span>
                        </div>

                        <div>
                            <label for="customer_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                                Customer Mobile Number (M-Pesa)
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-xs font-bold mono">
                                    +254
                                </span>
                                <input type="text" id="customer_phone" placeholder="0712 345 678"
                                    class="w-full pl-16 pr-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs font-mono placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime focus:border-transparent transition">
                            </div>
                        </div>

                        <!-- Primary Settlement Action Button -->
                        <button onclick="triggerPayment()" id="btn-collect"
                            class="w-full py-4 px-4 rounded-2xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-black text-sm tracking-wide uppercase shadow-xl shadow-lime-500/10 transition transform active:scale-[0.99] flex items-center justify-center space-x-2">
                            <span>Initiate M-Pesa STK Push</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Recent Completed Receipts Feed -->
        <section class="bg-[#14171d] border border-zinc-800 rounded-3xl p-5 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-800/80 mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-300">Recent Completed Receipts</h3>
                <span class="text-[11px] text-slate-500 mono">Verified Authentic</span>
            </div>

            <div class="divide-y divide-zinc-800/60 text-xs">
                <?php if (empty($recentReceipts)): ?>
                    <p class="text-slate-500 py-4 text-center">No receipts issued yet for this shift.</p>
                <?php else: ?>
                    <?php foreach ($recentReceipts as $rec): ?>
                        <div class="py-3 flex items-center justify-between hover:bg-[#1a1e26]/40 px-2 rounded-xl transition">
                            <div class="flex items-center space-x-3">
                                <span class="font-bold text-white mono text-xs">#<?= htmlspecialchars($rec['receipt_number']) ?></span>
                                <span class="text-neon-lime font-bold mono">KES <?= number_format($rec['total_amount'], 2) ?></span>
                                <?php if (!empty($rec['mpesa_receipt_number'])): ?>
                                    <span class="text-[10px] text-neon-cyan mono px-2 py-0.5 rounded-md bg-neon-cyan/10 border border-neon-cyan/20">
                                        <?= htmlspecialchars($rec['mpesa_receipt_number']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="text-[10px] font-semibold text-slate-400 mono"><?= date('H:i:s', strtotime($rec['created_at'])) ?></span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-neon-lime/10 text-neon-lime border border-neon-lime/20 mono">
                                    [ COMPLETED ]
                                </span>
                                <?php if (!empty($rec['pdf_path'])): ?>
                                    <a href="/storage.php?file=<?= basename($rec['pdf_path']) ?>" target="_blank" class="text-slate-400 hover:text-white text-xs underline font-medium">
                                        PDF
                                    </a>
                                <?php endif; ?>
                                <a href="/verify.php?id=<?= urlencode($rec['receipt_number']) ?>" target="_blank" class="text-neon-cyan hover:underline text-xs font-semibold">
                                    Verify &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>

    <!-- MODAL: CONFIGURE TRADER RECEIVING M-PESA ACCOUNT -->
    <div id="modal-account-settings" class="fixed inset-0 z-50 bg-[#0d0f12]/90 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <h3 class="text-lg font-black uppercase tracking-wider text-white">M-Pesa Destination Account</h3>
            <p class="text-xs text-slate-400 mt-1 mb-5">Set your individual Till or Paybill number where customer STK push money will be deposited.</p>

            <form onsubmit="saveAccountSettings(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Account Type
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="p-3 rounded-xl bg-[#0d0f12] border border-zinc-800 hover:border-neon-lime cursor-pointer flex items-center space-x-2">
                            <input type="radio" name="mpesa_type" value="till" checked class="text-neon-lime focus:ring-neon-lime">
                            <span class="text-xs font-bold text-white">Buy Goods Till</span>
                        </label>
                        <label class="p-3 rounded-xl bg-[#0d0f12] border border-zinc-800 hover:border-neon-lime cursor-pointer flex items-center space-x-2">
                            <input type="radio" name="mpesa_type" value="paybill" class="text-neon-lime focus:ring-neon-lime">
                            <span class="text-xs font-bold text-white">Paybill Number</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Business / Shortcode Number
                    </label>
                    <input type="text" id="setting_shortcode" required placeholder="e.g. 174379 or 829104" value="<?= htmlspecialchars($activeShortcode) ?>"
                        class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs font-mono placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                    <p class="text-[10px] text-slate-500 mt-1">This is your 5 to 7 digit Safaricom Till or Paybill number.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Stall / Store Identifier
                    </label>
                    <input type="text" id="setting_store" required placeholder="e.g. Stall #42 - Maina Fresh" value="<?= htmlspecialchars($activeStore) ?>"
                        class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Custom Passkey <span class="text-slate-500 font-normal lowercase">(optional override)</span>
                    </label>
                    <input type="text" id="setting_passkey" placeholder="Leave empty to use association gateway key"
                        class="w-full px-4 py-3 bg-[#0d0f12] border border-zinc-800 rounded-xl text-white text-xs font-mono placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-neon-lime">
                </div>

                <div class="pt-3 flex space-x-2">
                    <button type="button" onclick="closeAccountModal()" class="flex-1 py-3 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-slate-300 text-xs font-semibold border border-zinc-800 transition">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 py-3 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-bold text-xs uppercase tracking-wider shadow-lg shadow-lime-500/10 transition">
                        Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- STRICT MODAL LOCKDOWN: "PAY FIRST, RECEIPT COMES" -->
    <div id="payment-modal" class="fixed inset-0 z-50 bg-[#0d0f12]/90 backdrop-blur-md hidden flex items-center justify-center p-4">
        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            
            <!-- STEP B: LOCKED PAYMENT PENDING STATE -->
            <div id="modal-state-processing" class="space-y-6 text-center">
                
                <!-- Radar Pulse Animation -->
                <div class="relative w-20 h-20 mx-auto flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full bg-neon-lime/20 pulse-ring"></div>
                    <div class="w-12 h-12 rounded-full bg-neon-lime/10 border border-neon-lime/40 text-neon-lime flex items-center justify-center font-bold">
                        <svg class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-black uppercase tracking-wider text-white">Payment In Progress</h3>
                    <p class="text-xs text-neon-lime font-mono mt-1">Awaiting M-Pesa PIN entry on customer phone...</p>
                </div>

                <!-- Transaction Lockdown Telemetry -->
                <div class="space-y-2 text-left bg-[#0d0f12] border border-zinc-800 rounded-2xl p-4 text-xs font-mono">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Amount Due:</span>
                        <span id="modal-amount" class="font-bold text-neon-lime text-sm">KES 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Customer Mobile:</span>
                        <span id="modal-phone" class="font-bold text-white">+254...</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Depositing To:</span>
                        <span id="modal-destination" class="font-bold text-neon-cyan"><?= $activeType ?> #<?= htmlspecialchars($activeShortcode) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Checkout Reference:</span>
                        <span id="modal-checkout-id" class="text-slate-400 text-[11px]">ws_CO_...</span>
                    </div>
                </div>

                <!-- 60-Second Countdown Timer -->
                <div class="bg-[#1a1e26] border border-zinc-800 rounded-2xl p-3 flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">PIN Expiry Window:</span>
                    <span id="modal-countdown" class="text-lg font-black text-rose-400 mono">01:00</span>
                </div>

                <!-- Lockdown Notice -->
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs font-medium flex items-center justify-center space-x-2">
                    <span class="font-bold text-amber-400">&bull;</span>
                    <span>Receipt minting locked until Safaricom settlement confirmation</span>
                </div>

                <!-- Manual Cancellation Control -->
                <div class="pt-2">
                    <button onclick="cancelPayment()" class="w-full py-2.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-slate-300 text-xs font-semibold border border-zinc-800 transition">
                        Cancel Transaction Request
                    </button>
                </div>
            </div>

            <!-- STEP C: PAYMENT VERIFIED & RECEIPT MINTED STATE -->
            <div id="modal-state-success" class="hidden space-y-6 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-neon-lime/10 border border-neon-lime/30 text-neon-lime flex items-center justify-center shadow-lg shadow-lime-500/10">
                    <svg class="w-9 h-9" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                
                <div>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-neon-lime/10 text-neon-lime border border-neon-lime/30 uppercase mono tracking-wider">
                        PAYMENT SETTLED (M-PESA)
                    </span>
                    <h3 class="text-xl font-black text-white mt-2">Official Digital Receipt Minted</h3>
                    <p id="success-receipt-id" class="text-neon-cyan font-mono font-bold text-sm mt-1">#NTA-...</p>
                    <p id="success-mpesa-ref" class="text-slate-400 font-mono text-xs mt-0.5"></p>
                </div>

                <!-- Dynamic QR Code Preview -->
                <div class="bg-white rounded-2xl p-4 inline-block mx-auto border border-zinc-200 shadow-2xl">
                    <img id="success-qr-img" src="" alt="Official Receipt QR" class="w-36 h-36 mx-auto">
                    <p class="text-[9px] text-slate-700 font-mono mt-1 uppercase font-extrabold tracking-wider">Official Verification QR</p>
                </div>

                <!-- Instant Action CTAs -->
                <div class="space-y-2.5">
                    <a id="btn-download-pdf" href="" target="_blank"
                        class="w-full py-3.5 px-4 rounded-xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-bold text-xs uppercase tracking-wider flex items-center justify-center space-x-2 transition shadow-lg shadow-lime-500/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Download 80mm Vector PDF Receipt</span>
                    </a>

                    <div class="grid grid-cols-2 gap-2">
                        <a id="btn-open-verify" href="" target="_blank"
                            class="py-2.5 px-3 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-neon-cyan font-semibold text-xs flex items-center justify-center space-x-1 border border-zinc-800 transition">
                            <span>Verify Online</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                        <button onclick="finishTransaction()" class="py-2.5 px-3 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-white font-semibold text-xs border border-zinc-800 transition">
                            Next Sale &rarr;
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 space-y-2 pointer-events-none"></div>

    <script>
        let cart = [];
        let activePayment = null;
        let pollInterval = null;
        let countdownInterval = null;
        let secondsRemaining = 60;

        function showToast(message, type = 'error') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `p-4 rounded-2xl text-xs font-semibold shadow-2xl flex items-center space-x-2 pointer-events-auto transition transform translate-y-2 opacity-0 ${
                type === 'error' ? 'bg-rose-500/10 border border-rose-500/30 text-rose-300' : 'bg-neon-lime/10 border border-neon-lime/30 text-neon-lime'
            }`;
            toast.innerHTML = `
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>${message}</span>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-2', 'opacity-0');
            });
            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        function openAccountModal() {
            document.getElementById('modal-account-settings').classList.remove('hidden');
        }

        function closeAccountModal() {
            document.getElementById('modal-account-settings').classList.add('hidden');
        }

        async function saveAccountSettings(e) {
            e.preventDefault();
            const shortcode = document.getElementById('setting_shortcode').value.trim();
            const storeName = document.getElementById('setting_store').value.trim();
            const passkey = document.getElementById('setting_passkey').value.trim();
            const type = document.querySelector('input[name="mpesa_type"]:checked').value;

            try {
                const res = await fetch('/api/update_trader_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update',
                        mpesa_shortcode: shortcode,
                        mpesa_type: type,
                        store_name: storeName,
                        mpesa_passkey: passkey
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    closeAccountModal();
                    // Update badges
                    const typeLabel = data.type.toUpperCase();
                    document.getElementById('badge-account-info').textContent = `${typeLabel} #${data.shortcode}`;
                    document.getElementById('label-destination').textContent = `${typeLabel} #${data.shortcode}`;
                    document.getElementById('cart-destination-label').textContent = `${typeLabel} #${data.shortcode}`;
                    document.getElementById('badge-store-name').textContent = data.store_name;
                } else {
                    showToast(data.message);
                }
            } catch (err) {
                showToast('Failed to save settings: ' + err.message);
            }
        }

        function renderCart() {
            const container = document.getElementById('cart-items');
            container.innerHTML = '';

            let total = 0;
            let count = 0;

            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="py-12 text-center text-slate-600 text-xs">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span>Cart is empty. Select items from catalog or add a custom item.</span>
                    </div>
                `;
            } else {
                cart.forEach((item, index) => {
                    const lineTotal = item.quantity * item.unit_price;
                    total += lineTotal;
                    count += item.quantity;

                    const row = document.createElement('div');
                    row.className = 'p-3 bg-[#1a1e26] border border-zinc-800 rounded-2xl flex items-center justify-between text-xs';
                    row.innerHTML = `
                        <div class="flex-1">
                            <div class="font-bold text-white">${escapeHtml(item.name)}</div>
                            <div class="text-[11px] text-slate-400 mono mt-0.5">
                                ${item.quantity} &times; KES ${item.unit_price.toFixed(2)} = <span class="text-neon-lime font-bold">KES ${lineTotal.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="changeQty(${index}, -1)" class="w-6 h-6 rounded-lg bg-[#14171d] hover:bg-[#232834] text-slate-200 font-bold flex items-center justify-center border border-zinc-800">-</button>
                            <span class="w-6 text-center font-bold text-white mono">${item.quantity}</span>
                            <button onclick="changeQty(${index}, 1)" class="w-6 h-6 rounded-lg bg-[#14171d] hover:bg-[#232834] text-slate-200 font-bold flex items-center justify-center border border-zinc-800">+</button>
                            <button onclick="removeItem(${index})" class="w-6 h-6 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 flex items-center justify-center border border-rose-500/20 ml-1">
                                &times;
                            </button>
                        </div>
                    `;
                    container.appendChild(row);
                });
            }

            document.getElementById('cart-total').textContent = `KES ${total.toFixed(2)}`;
            document.getElementById('cart-item-count').textContent = `${count} ${count === 1 ? 'item' : 'items'}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function addItem(name, price) {
            const existing = cart.find(i => i.name === name);
            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({ name, unit_price: price, quantity: 1 });
            }
            renderCart();
        }

        function addCustomItem() {
            const name = document.getElementById('custom_name').value.trim();
            const price = parseFloat(document.getElementById('custom_price').value);
            if (!name || isNaN(price) || price <= 0) {
                showToast('Please enter a valid item name and positive price.');
                return;
            }
            addItem(name, price);
            document.getElementById('custom_name').value = '';
            document.getElementById('custom_price').value = '';
        }

        function changeQty(index, delta) {
            cart[index].quantity += delta;
            if (cart[index].quantity <= 0) {
                cart.splice(index, 1);
            }
            renderCart();
        }

        function removeItem(index) {
            cart.splice(index, 1);
            renderCart();
        }

        function resetCart() {
            cart = [];
            renderCart();
        }

        // STEP A: ORDER & PAYMENT INITIATION
        async function triggerPayment() {
            if (cart.length === 0) {
                showToast('Cart is empty. Please add items before initiating checkout.');
                return;
            }
            const phone = document.getElementById('customer_phone').value.trim();
            if (!phone) {
                showToast('Customer M-Pesa mobile number is required.');
                return;
            }

            const total = cart.reduce((sum, i) => sum + (i.quantity * i.unit_price), 0);

            // Open Lockdown Modal
            document.getElementById('modal-amount').textContent = `KES ${total.toFixed(2)}`;
            document.getElementById('modal-phone').textContent = phone;
            document.getElementById('modal-checkout-id').textContent = 'Connecting Safaricom gateway...';
            document.getElementById('modal-state-processing').classList.remove('hidden');
            document.getElementById('modal-state-success').classList.add('hidden');
            document.getElementById('payment-modal').classList.remove('hidden');

            // Start 60s Countdown Timer
            secondsRemaining = 60;
            updateCountdownDisplay();
            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                secondsRemaining--;
                updateCountdownDisplay();
                if (secondsRemaining <= 0) {
                    handleTimeout();
                }
            }, 1000);

            try {
                const res = await fetch('/api/initiate_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        customer_phone: phone,
                        items: cart
                    })
                });

                const data = await res.json();
                if (data.status !== 'pending') {
                    cleanupModal();
                    showToast(data.message || 'Payment initiation failed.');
                    return;
                }

                activePayment = data;
                document.getElementById('modal-checkout-id').textContent = data.checkout_request_id;
                if (data.destination) {
                    document.getElementById('modal-destination').textContent = `${data.destination.type.toUpperCase()} #${data.destination.shortcode}`;
                }

                // STEP B: REAL-TIME POLLING EVERY 2 SECONDS
                if (pollInterval) clearInterval(pollInterval);
                pollInterval = setInterval(() => {
                    pollSettlement(data.checkout_request_id, data.receipt_id);
                }, 2000);

            } catch (err) {
                cleanupModal();
                showToast('Network error while dispatching STK push: ' + err.message);
            }
        }

        function updateCountdownDisplay() {
            const mins = Math.floor(secondsRemaining / 60);
            const secs = secondsRemaining % 60;
            document.getElementById('modal-countdown').textContent = 
                `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // STEP B: REAL-TIME POLLING
        async function pollSettlement(checkoutId, receiptId) {
            try {
                const res = await fetch('/api/check_payment_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        checkout_request_id: checkoutId,
                        receipt_id: receiptId
                    })
                });

                const data = await res.json();

                // STEP C: PAYMENT CONFIRMATION & RECEIPT MINTING
                if (data.status === 'completed') {
                    if (pollInterval) clearInterval(pollInterval);
                    if (countdownInterval) clearInterval(countdownInterval);

                    // Morph modal into verified receipt view
                    document.getElementById('modal-state-processing').classList.add('hidden');
                    document.getElementById('modal-state-success').classList.remove('hidden');

                    document.getElementById('success-receipt-id').textContent = '#' + data.receipt_number;
                    if (data.mpesa_receipt_number) {
                        document.getElementById('success-mpesa-ref').textContent = 'Safaricom Ref: ' + data.mpesa_receipt_number;
                    }
                    const qrFile = data.qr_url.replace('/storage/', '');
                    const pdfFile = data.pdf_url.replace('/storage/', '');
                    document.getElementById('success-qr-img').src = '/storage.php?file=' + qrFile;
                    document.getElementById('btn-download-pdf').href = '/storage.php?file=' + pdfFile;
                    document.getElementById('btn-open-verify').href = '/verify.php?id=' + encodeURIComponent(data.receipt_number);

                } else if (data.status === 'failed' || data.status === 'cancelled' || data.status === 'timed_out') {
                    cleanupModal();
                    showToast(data.message || 'Payment was unsuccessful. No receipt was issued.');
                }
            } catch (err) {
                console.warn('Polling check error:', err);
            }
        }

        async function cancelPayment() {
            if (!activePayment) {
                cleanupModal();
                return;
            }
            try {
                await fetch('/api/check_payment_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        receipt_id: activePayment.receipt_id,
                        action: 'cancel'
                    })
                });
            } catch (e) {}
            cleanupModal();
            showToast('Transaction cancelled by operator. No receipt issued.');
        }

        async function handleTimeout() {
            if (activePayment) {
                try {
                    await fetch('/api/check_payment_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            receipt_id: activePayment.receipt_id,
                            action: 'timeout'
                        })
                    });
                } catch (e) {}
            }
            cleanupModal();
            showToast('Payment Timed Out: Customer did not enter M-Pesa PIN within 60 seconds. No receipt was issued.');
        }

        function cleanupModal() {
            if (pollInterval) clearInterval(pollInterval);
            if (countdownInterval) clearInterval(countdownInterval);
            document.getElementById('payment-modal').classList.add('hidden');
            activePayment = null;
        }

        function finishTransaction() {
            cleanupModal();
            resetCart();
            location.reload();
        }

        // Initialize default view
        renderCart();
    </script>
</body>
</html>
