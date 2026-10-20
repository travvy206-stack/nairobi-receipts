<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

$auth = new AuthService();
$auth->requireAdmin('/login.php');

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

$search = trim($_GET['search'] ?? '');
$query = "SELECT a.*, u.full_name, u.phone_number, u.role 
          FROM audit_log a 
          LEFT JOIN users u ON a.user_id = u.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.action_performed LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

$query .= " ORDER BY a.id DESC LIMIT 100";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d0f12]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forensic Audit Archive | NTA-Pay</title>
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
<body class="min-h-full bg-[#0d0f12] text-slate-100 flex flex-col antialiased">
    
    <!-- Top Navigation -->
    <header class="bg-[#14171d] border-b border-zinc-800/80 sticky top-0 z-40 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="/admin/dashboard.php" class="px-3 py-1.5 rounded-xl bg-[#1a1e26] hover:bg-[#232834] text-xs font-semibold text-slate-300 border border-zinc-800 transition flex items-center space-x-1.5">
                    <span>&larr; Return to Dashboard</span>
                </a>
                <span class="text-zinc-700">|</span>
                <span class="text-xs font-bold uppercase tracking-wider text-white">Forensic Audit Log Archive</span>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-xs text-slate-400 mono">Entries: <b class="text-white"><?= count($logs) ?></b></span>
                <a href="/logout.php" class="text-xs text-rose-400 hover:underline font-semibold">Sign Out</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-white">Security & Operational Logs</h1>
                <p class="text-xs text-slate-400 mt-1">Immutable forensic tracking of staff logins, STK push attempts, receipt minting, and system actions</p>
            </div>
            <form method="GET" class="flex items-center space-x-2 w-full sm:w-auto">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Filter by action, user, or IP..."
                    class="px-3.5 py-2 bg-[#14171d] border border-zinc-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:ring-1 focus:ring-neon-lime w-full sm:w-72">
                <button type="submit" class="px-4 py-2 bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-bold rounded-xl text-xs uppercase tracking-wider transition">
                    Search
                </button>
            </form>
        </div>

        <div class="bg-[#14171d] border border-zinc-800 rounded-3xl overflow-hidden shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#0d0f12] border-b border-zinc-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <th class="py-3.5 px-4">Event ID</th>
                            <th class="py-3.5 px-4">Timestamp</th>
                            <th class="py-3.5 px-4">Staff Member</th>
                            <th class="py-3.5 px-4">IP Address</th>
                            <th class="py-3.5 px-4">Action Recorded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60 text-xs">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="py-10 text-center text-slate-500 font-mono">
                                    No audit entries match the query.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-[#1a1e26]/30 transition">
                                    <td class="py-3 px-4 mono text-slate-500 font-semibold">
                                        #<?= $log['id'] ?>
                                    </td>
                                    <td class="py-3 px-4 mono text-slate-400 text-[11px]">
                                        <?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <?php if ($log['full_name']): ?>
                                            <span class="font-bold text-white"><?= htmlspecialchars($log['full_name']) ?></span>
                                            <span class="text-[10px] text-neon-cyan block uppercase mono font-bold"><?= htmlspecialchars($log['role']) ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-500 italic">System Kernel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 mono text-neon-lime text-[11px]">
                                        <?= htmlspecialchars($log['ip_address']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-slate-200 font-medium">
                                        <?= htmlspecialchars($log['action_performed']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
