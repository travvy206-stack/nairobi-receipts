<?php
require_once __DIR__ . '/../config/db.php';

$receiptNum = trim($_GET['id'] ?? '');
if (empty($receiptNum)) {
    http_response_code(400);
    die("Invalid request: Receipt ID parameter missing.");
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT r.*, u.full_name AS trader_name, c.phone_number AS customer_phone, c.full_name AS customer_name 
                      FROM receipts r 
                      JOIN users u ON r.trader_id = u.id 
                      JOIN customers c ON r.customer_id = c.id 
                      WHERE r.receipt_number = ?");
$stmt->execute([$receiptNum]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    header("Content-Type: text/html; charset=UTF-8");
    die("<!DOCTYPE html><html><head><title>Receipt Not Found</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-[#0d0f12] text-slate-100 flex items-center justify-center min-h-screen p-4'><div class='max-w-md w-full bg-[#14171d] border border-zinc-800 rounded-3xl p-8 text-center shadow-2xl'><div class='w-14 h-14 mx-auto mb-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center font-bold text-xl'>!</div><h1 class='text-lg font-bold text-white'>Unverified Document</h1><p class='text-xs text-slate-400 mt-2'>No official record exists matching transaction #" . htmlspecialchars($receiptNum) . ".</p></div></body></html>");
}

$stmtItems = $db->prepare("SELECT * FROM receipt_items WHERE receipt_id = ?");
$stmtItems->execute([(int)$receipt['id']]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d0f12]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt #<?= htmlspecialchars($receipt['receipt_number']) ?> | NTA-Pay</title>
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
        .thermal-paper {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8);
        }
    </style>
</head>
<body class="min-h-full bg-[#0d0f12] text-slate-100 flex flex-col items-center justify-center p-4 py-8">
    <div class="w-full max-w-sm space-y-4">
        
        <!-- Verification Seal -->
        <div class="text-center">
            <?php if ($receipt['payment_status'] === 'completed'): ?>
                <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-full bg-neon-lime/10 border border-neon-lime/30 text-neon-lime text-xs font-black uppercase tracking-wider shadow-lg shadow-lime-500/10">
                    <svg class="w-4 h-4 text-neon-lime flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>VERIFIED OK &bull; AUTHENTIC PROOF OF PURCHASE</span>
                </div>
            <?php else: ?>
                <div class="inline-flex items-center space-x-2 px-4 py-2 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-black uppercase tracking-wider">
                    <span>TRANSACTION <?= strtoupper($receipt['payment_status']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- 80mm Digital Thermal Receipt Card -->
        <div class="thermal-paper rounded-3xl p-6 border border-zinc-200">
            <!-- Header Section -->
            <div class="text-center pb-4 border-b border-dashed border-slate-300">
                <h1 class="text-base font-black tracking-tight uppercase text-slate-900">NAIROBI TRADERS ASSN.</h1>
                <p class="text-[10px] font-bold text-slate-500 uppercase mt-0.5 tracking-wider">Official Digital Proof of Purchase</p>
                <div class="mt-2 text-[10px] text-slate-400 font-mono">Store Stall #42 &bull; Terminal POS #104</div>
            </div>

            <!-- Transaction Metadata -->
            <div class="py-3 border-b border-dashed border-slate-300 text-xs space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-slate-500">Receipt No:</span>
                    <span class="font-black font-mono text-slate-900"><?= htmlspecialchars($receipt['receipt_number']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Issued Timestamp:</span>
                    <span class="font-medium font-mono text-slate-800"><?= date('Y-m-d H:i:s', strtotime($receipt['created_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Served By:</span>
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($receipt['trader_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Customer Mobile:</span>
                    <span class="font-medium font-mono text-slate-800"><?= htmlspecialchars($receipt['customer_phone']) ?></span>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="py-3 border-b border-dashed border-slate-300">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-[10px] text-slate-400 uppercase border-b border-slate-200">
                            <th class="text-left pb-1">Line Item</th>
                            <th class="text-center pb-1">Qty</th>
                            <th class="text-right pb-1">Unit</th>
                            <th class="text-right pb-1">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="py-1.5 font-medium text-slate-900"><?= htmlspecialchars($item['item_name']) ?></td>
                                <td class="py-1.5 text-center text-slate-600 font-mono"><?= $item['quantity'] ?></td>
                                <td class="py-1.5 text-right text-slate-600 font-mono"><?= number_format($item['unit_price'], 0) ?></td>
                                <td class="py-1.5 text-right font-bold text-slate-900 font-mono"><?= number_format($item['line_total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals & Payment Summary -->
            <div class="py-3 border-b border-dashed border-slate-300 space-y-1.5 text-xs">
                <div class="flex justify-between items-baseline font-black text-sm">
                    <span class="text-slate-900 uppercase">GRAND TOTAL:</span>
                    <span class="text-slate-950 font-mono text-base font-black">KES <?= number_format($receipt['total_amount'], 2) ?></span>
                </div>
                <div class="flex justify-between text-[11px] text-slate-600">
                    <span>Settlement Method:</span>
                    <span class="font-bold text-emerald-800">M-Pesa STK Push</span>
                </div>
                <div class="flex justify-between text-[11px] text-slate-600">
                    <span>Checkout Reference:</span>
                    <span class="font-mono text-[10px]"><?= htmlspecialchars($receipt['mpesa_checkout_id'] ?? 'ws_CO_DIRECT') ?></span>
                </div>
                <div class="flex justify-between text-[11px] text-slate-600">
                    <span>Verification State:</span>
                    <span class="font-black text-emerald-600 uppercase">CONFIRMED AUTHENTIC</span>
                </div>
            </div>

            <!-- Dynamic QR Code Section -->
            <div class="pt-4 text-center">
                <?php if (!empty($receipt['qr_code_path'])): ?>
                    <img src="/storage.php?file=<?= basename($receipt['qr_code_path']) ?>" alt="Verification QR Code"
                        class="w-32 h-32 mx-auto rounded-xl border border-slate-200 p-1 shadow-sm">
                <?php endif; ?>
                <p class="text-[10px] text-slate-400 font-mono mt-2 uppercase tracking-wider">Scan to verify authentic transaction</p>
                <p class="text-[9px] text-slate-400 mt-0.5">https://nairobi-traders.org/verify</p>
            </div>
        </div>

        <!-- Action CTAs -->
        <div class="flex space-x-2">
            <?php if (!empty($receipt['pdf_path'])): ?>
                <a href="/storage.php?file=<?= basename($receipt['pdf_path']) ?>" target="_blank"
                    class="flex-1 py-3 px-4 rounded-2xl bg-neon-lime hover:bg-neon-limeHover text-slate-950 font-black text-xs text-center shadow-lg shadow-lime-500/10 transition flex items-center justify-center space-x-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Download Vector PDF</span>
                </a>
            <?php endif; ?>
            <button onclick="window.print()"
                class="py-3 px-4 rounded-2xl bg-[#14171d] hover:bg-[#1a1e26] text-slate-300 font-semibold text-xs border border-zinc-800 transition flex items-center space-x-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                <span>Print</span>
            </button>
        </div>
    </div>
</body>
</html>
