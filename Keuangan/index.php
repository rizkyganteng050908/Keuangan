<?php
// ==========================================
// 1. KONEKSI KE DATABASE MYSQL
// ==========================================
$host = "localhost";
$user = "root"; 
$pass = ""; 
$db   = "db_harian_keuangan";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Set timezone ke WIB
date_default_timezone_set('Asia/Jakarta');

// ==========================================
// 2. PROSES BACKEND (CRUD)
// ==========================================

// Fitur Tambah Saldo Awal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_balance'])) {
    $jml = (float)$_POST['saldo_awal'];
    $ket = "Tambah Saldo / Modal Awal";
    $tgl = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');

    if ($jml > 0) {
        $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, keterangan, jumlah, tipe) VALUES (?, ?, ?, 'masuk')");
        $stmt->bind_param("ssd", $tgl, $ket, $jml);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
}

// Tambah Transaksi Biasa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_finance'])) {
    $ket  = trim($_POST['keterangan']);
    $jml  = (float)$_POST['jumlah'];
    $tipe = $_POST['tipe'] === 'keluar' ? 'keluar' : 'masuk';
    $tgl  = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');

    if (!empty($ket) && $jml > 0) {
        $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, keterangan, jumlah, tipe) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $tgl, $ket, $jml, $tipe);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
}

// Tambah Catatan Jurnal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_journal'])) {
    $isi   = trim($_POST['isi_catatan']);
    $tglIn = !empty($_POST['tanggal_jurnal']) ? $_POST['tanggal_jurnal'] : date('Y-m-d');
    $tgl   = $tglIn . ' ' . date('H:i:s');

    if (!empty($isi)) {
        $stmt = $conn->prepare("INSERT INTO jurnal_harian (tanggal, isi_catatan) VALUES (?, ?)");
        $stmt->bind_param("ss", $tgl, $isi);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
}

// Hapus Transaksi
if (isset($_GET['delete_finance'])) {
    $id = (int)$_GET['delete_finance'];
    $stmt = $conn->prepare("DELETE FROM transaksi WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit();
}

// Hapus Catatan Jurnal
if (isset($_GET['delete_journal'])) {
    $id = (int)$_GET['delete_journal'];
    $stmt = $conn->prepare("DELETE FROM jurnal_harian WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit();
}

// ==========================================
// 3. AMBIL DATA DARI DATABASE
// ==========================================
$resFinance = $conn->query("SELECT * FROM transaksi ORDER BY tanggal DESC, id DESC");

$resTotal = $conn->query("
    SELECT 
        SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END) AS total_masuk,
        SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END) AS total_keluar
    FROM transaksi
")->fetch_assoc();

$totalMasuk  = $resTotal['total_masuk'] ?? 0;
$totalKeluar = $resTotal['total_keluar'] ?? 0;
$sisaSaldo   = $totalMasuk - $totalKeluar;

$resJournal = $conn->query("SELECT * FROM jurnal_harian ORDER BY tanggal DESC, id DESC");

// Helper Tanggal Indonesia
$hariIndo  = ['Sunday'=>'Minggu', 'Monday'=>'Senin', 'Tuesday'=>'Selasa', 'Wednesday'=>'Rabu', 'Thursday'=>'Kamis', 'Friday'=>'Jumat', 'Saturday'=>'Sabtu'];
$bulanIndo = [1=>'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$hari  = $hariIndo[date('l')];
$tgl   = date('j');
$bulan = $bulanIndo[(int)date('n')];
$tahun = date('Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal & Keuangan Harian</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen pb-12 antialiased">

    <div class="h-56 bg-gradient-to-r from-indigo-700 via-purple-700 to-indigo-800 w-full absolute top-0 left-0 z-0"></div>

    <div class="max-w-4xl mx-auto px-4 pt-8 relative z-10 space-y-6">
        
        <header class="bg-slate-900/40 backdrop-blur-md border border-white/20 text-white rounded-2xl p-6 shadow-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white mb-2">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Dashboard Pribadi
                </span>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white drop-shadow-sm">Catatan Harian & Keuangan</h1>
                <p class="text-slate-200 text-sm mt-1">Kelola transaksi dan jurnal harian berbasis MySQL.</p>
            </div>
            
            <div class="bg-black/25 backdrop-blur-md px-4 py-3 rounded-xl border border-white/10 w-full sm:w-auto text-left sm:text-right">
                <div class="text-lg font-bold flex items-center gap-2 sm:justify-end text-white">
                    <i data-lucide="calendar" class="w-4 h-4 text-indigo-200"></i>
                    <span><?= $hari; ?>, <?= $tgl; ?> <?= $bulan; ?></span>
                </div>
                <div class="text-xs text-indigo-200 mt-0.5">Tahun <?= $tahun; ?></div>
            </div>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Sisa Saldo</span>
                        <p class="text-2xl font-bold text-indigo-600">Rp <?= number_format($sisaSaldo, 0, ',', '.'); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                        <i data-lucide="wallet" class="w-6 h-6"></i>
                    </div>
                </div>
                <form action="index.php" method="POST" class="pt-2 border-t border-slate-100 flex gap-1.5">
                    <input type="number" name="saldo_awal" min="1" step="any" placeholder="Top up saldo..." required 
                        class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:bg-white focus:ring-1 focus:ring-indigo-500 outline-none">
                    <button type="submit" name="set_balance" title="Tambah Saldo"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1 shrink-0">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Saldo
                    </button>
                </form>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Pemasukan</span>
                    <p class="text-2xl font-bold text-emerald-600">Rp <?= number_format($totalMasuk, 0, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                    <i data-lucide="arrow-down-left" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Pengeluaran</span>
                    <p class="text-2xl font-bold text-rose-600">Rp <?= number_format($totalKeluar, 0, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                    <i data-lucide="arrow-up-right" class="w-6 h-6"></i>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                        <i data-lucide="receipt" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-lg font-bold text-slate-800">Catat Transaksi</h2>
                </div>

                <form action="index.php" method="POST" class="space-y-3 mb-6">
                    <div>
                        <input type="text" name="keterangan" placeholder="Keterangan (contoh: Beli Kopi)" required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                    </div>
                    <div>
                        <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Pilih Tanggal Transaksi</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d'); ?>" required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition text-slate-600">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" name="jumlah" min="1" step="any" placeholder="Nominal (Rp)" required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                        <select name="tipe" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                            <option value="masuk">Pemasukan (+)</option>
                            <option value="keluar">Pengeluaran (-)</option>
                        </select>
                    </div>
                    <button type="submit" name="add_finance" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white font-semibold py-2.5 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah Transaksi
                    </button>
                </form>

                <hr class="border-slate-100 my-2">

                <div class="flex-1">
                    <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-3">Riwayat Transaksi</h3>
                    <ul class="divide-y divide-slate-100 max-h-72 overflow-y-auto pr-1 space-y-1">
                        <?php if ($resFinance && $resFinance->num_rows > 0): ?>
                            <?php while ($row = $resFinance->fetch_assoc()): ?>
                                <li class="py-2.5 px-3 hover:bg-slate-50 rounded-xl transition flex justify-between items-center text-sm group">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center <?= $row['tipe'] === 'masuk' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'; ?>">
                                            <i data-lucide="<?= $row['tipe'] === 'masuk' ? 'arrow-down-left' : 'arrow-up-right'; ?>" class="w-4 h-4"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-700"><?= htmlspecialchars($row['keterangan']); ?></p>
                                            <span class="text-xs text-slate-400"><?= date('d M Y', strtotime($row['tanggal'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-bold <?= $row['tipe'] === 'masuk' ? 'text-emerald-600' : 'text-rose-600'; ?>">
                                            <?= $row['tipe'] === 'masuk' ? '+' : '-'; ?> Rp <?= number_format($row['jumlah'], 0, ',', '.'); ?>
                                        </span>
                                        <a href="index.php?delete_finance=<?= $row['id']; ?>" onclick="return confirm('Hapus transaksi ini?')" 
                                            class="text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition p-1">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400">
                                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-xs">Belum ada transaksi.</p>
                            </div>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>

            <section class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col">
                <div class="flex items-center gap-2 mb-4">
                    <div class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                        <i data-lucide="book-open" class="w-5 h-5"></i>
                    </div>
                    <h2 class="text-lg font-bold text-slate-800">Jurnal & Catatan Harian</h2>
                </div>

                <form action="index.php" method="POST" class="space-y-3 mb-6">
                    <div>
                        <label class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Pilih Tanggal Catatan</label>
                        <input type="date" name="tanggal_jurnal" value="<?= date('Y-m-d'); ?>" required 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition text-slate-600">
                    </div>
                    <textarea name="isi_catatan" rows="3" placeholder="Tulis catatan, rencana, atau momen harimu..." required 
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition resize-none"></textarea>
                    <button type="submit" name="add_journal" 
                        class="w-full bg-slate-800 hover:bg-slate-900 active:scale-[0.99] text-white font-semibold py-2.5 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i> Simpan Catatan
                    </button>
                </form>

                <hr class="border-slate-100 my-2">

                <div class="flex-1">
                    <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-3">Catatan Terakhir</h3>
                    <ul class="space-y-3 max-h-72 overflow-y-auto pr-1">
                        <?php if ($resJournal && $resJournal->num_rows > 0): ?>
                            <?php while ($row = $resJournal->fetch_assoc()): ?>
                                <li class="p-4 bg-slate-50 hover:bg-slate-100/80 rounded-xl border border-slate-100 transition flex justify-between items-start group">
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-purple-600 bg-purple-50 px-2 py-0.5 rounded-md">
                                            <i data-lucide="clock" class="w-3 h-3"></i> <?= date('d M Y, H:i', strtotime($row['tanggal'])); ?>
                                        </span>
                                        <p class="text-sm text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars($row['isi_catatan'])); ?></p>
                                    </div>
                                    <a href="index.php?delete_journal=<?= $row['id']; ?>" onclick="return confirm('Hapus catatan ini?')" 
                                        class="text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition p-1 ml-2">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-slate-400">
                                <i data-lucide="file-text" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                <p class="text-xs">Belum ada catatan harian.</p>
                            </div>
                        <?php endif; ?>
                    </ul>
                </div>
            </section>

        </div>

    </div>

    <script>
        // Inisialisasi Lucide Icons
        lucide.createIcons();
    </script>
</body>
</html>