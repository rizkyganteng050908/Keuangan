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

// Fitur Tambah Saldo Awal / Top Up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_balance'])) {
    $rawJml = str_replace('.', '', $_POST['saldo_awal']);
    $jml    = (float)$rawJml;
    $ket    = "Tambah Saldo / Modal Awal";
    $tgl    = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');

    if ($jml > 0) {
        $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, keterangan, jumlah, tipe, kategori) VALUES (?, ?, ?, 'masuk', 'Top Up')");
        $stmt->bind_param("ssd", $tgl, $ket, $jml);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: index.php");
    exit();
}

// Tambah Transaksi Biasa (Dengan Kategori)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_finance'])) {
    $ket    = trim($_POST['keterangan']);
    $rawJml = str_replace('.', '', $_POST['jumlah']);
    $jml    = (float)$rawJml;
    $tipe   = $_POST['tipe'] === 'keluar' ? 'keluar' : 'masuk';
    $tgl    = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
    $kat    = !empty($_POST['kategori']) ? $_POST['kategori'] : 'Lainnya';

    if (!empty($ket) && $jml > 0) {
        $stmt = $conn->prepare("INSERT INTO transaksi (tanggal, keterangan, jumlah, tipe, kategori) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $tgl, $ket, $jml, $tipe, $kat);
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
// 3. AMBIL DATA DARI DATABASE (DENGAN FILTER)
// ==========================================

// Filter Tanggal / Pencarian
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "";
if (!empty($search)) {
    $searchEsc = $conn->real_escape_string($search);
    $where = "WHERE keterangan LIKE '%$searchEsc%' OR kategori LIKE '%$searchEsc%'";
}

$resFinance = $conn->query("SELECT * FROM transaksi $where ORDER BY tanggal DESC, id DESC");

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

// INDIKATOR SALDO DINAMIS
if ($sisaSaldo < 0) {
    $saldoColorClass = 'text-rose-600';
} elseif ($sisaSaldo < 100000) {
    $saldoColorClass = 'text-amber-600';
} else {
    $saldoColorClass = 'text-emerald-600';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Jurnal Keuangan Harian tiap harinya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen pb-16 antialiased selection:bg-indigo-500 selection:text-white">

    <div class="h-60 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 w-full absolute top-0 left-0 z-0 border-b border-indigo-900/30"></div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-8 relative z-10 space-y-6">
        
        <header class="bg-slate-900/80 backdrop-blur-xl border border-slate-700/50 text-white rounded-3xl p-6 shadow-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-indigo-400"></i> Dashboard Personal
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">Catatan Harian & Keuangan</h1>
            </div>
            
            <div class="bg-slate-800/80 backdrop-blur-md px-4 py-3 rounded-2xl border border-slate-700/60 w-full sm:w-auto text-left sm:text-right shadow-inner">
                <div class="text-base font-bold flex items-center gap-2 sm:justify-end text-slate-100">
                    <i data-lucide="calendar" class="w-4 h-4 text-indigo-400"></i>
                    <span><?= $hari; ?>, <?= $tgl; ?> <?= $bulan; ?></span>
                </div>
                <div class="text-xs text-slate-400 font-medium mt-0.5">Tahun <?= $tahun; ?></div>
            </div>
        </header>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 flex flex-col justify-between hover:shadow-md transition-all duration-200">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Sisa Saldo</span>
                        <p class="text-2xl font-black <?= $saldoColorClass; ?>">Rp <?= number_format($sisaSaldo, 0, ',', '.'); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm">
                        <i data-lucide="wallet" class="w-6 h-6"></i>
                    </div>
                </div>
                <form action="index.php" method="POST" class="pt-3 border-t border-slate-100 flex gap-2">
                    <input type="text" name="saldo_awal" placeholder="Top up saldo..." required 
                        class="input-rupiah w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition">
                    <button type="submit" name="set_balance" title="Tambah Saldo"
                        class="bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1 shrink-0 shadow-sm shadow-indigo-200">
                        <i data-lucide="plus" class="w-4 h-4"></i> Top Up
                    </button>
                </form>
            </div>

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 flex items-center justify-between hover:shadow-md transition-all duration-200">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Pemasukan</span>
                    <p class="text-2xl font-black text-emerald-600">Rp <?= number_format($totalMasuk, 0, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center shadow-sm">
                    <i data-lucide="arrow-down-left" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/80 flex items-center justify-between hover:shadow-md transition-all duration-200">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Pengeluaran</span>
                    <p class="text-2xl font-black text-rose-600">Rp <?= number_format($totalKeluar, 0, ',', '.'); ?></p>
                </div>
                <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center shadow-sm">
                    <i data-lucide="arrow-up-right" class="w-6 h-6"></i>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <section class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-5">
                        <div class="p-2.5 bg-indigo-50 text-indigo-600 rounded-2xl">
                            <i data-lucide="receipt" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-lg font-extrabold text-slate-800">Catat Transaksi</h2>
                    </div>

                    <form action="index.php" method="POST" class="space-y-3.5 mb-6">
                        <div>
                            <input type="text" name="keterangan" placeholder="Keterangan telah membeli sesuatu" required 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition placeholder:text-slate-400">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5 ml-1">Kategori</label>
                                <select name="kategori" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition text-slate-700">
                                    <option value="Makanan & Minuman">🍔 Makanan & Minuman</option>
                                    <option value="Transportasi">🚗 Transportasi</option>
                                    <option value="Belanja">🛍️ Belanja</option>
                                    <option value="Gaji / Utama">💵 Gaji / Utama</option>
                                    <option value="Lainnya">📌 Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5 ml-1">Pilih Tanggal</label>
                                <input type="date" name="tanggal" value="<?= date('Y-m-d'); ?>" required 
                                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition text-slate-700">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="jumlah" placeholder="Nominal (Rp)" required 
                                class="input-rupiah w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition placeholder:text-slate-400">
                            <select name="tipe" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-semibold focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none transition text-slate-700">
                                <option value="masuk">Pemasukan (+)</option>
                                <option value="keluar">Pengeluaran (-)</option>
                            </select>
                        </div>
                        <button type="submit" name="add_finance" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-[0.98] text-white font-bold py-3 rounded-2xl text-sm transition shadow-md shadow-indigo-200 flex items-center justify-center gap-2">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah Transaksi
                        </button>
                    </form>

                    <hr class="border-slate-100 my-4">

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider ml-1">Riwayat Transaksi</h3>
                            <form action="index.php" method="GET" class="flex gap-1">
                                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari..." 
                                    class="px-3 py-1 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:bg-white focus:ring-1 focus:ring-indigo-500">
                                <button type="submit" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-2.5 py-1 rounded-xl text-xs font-bold transition">
                                    <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>

                        <ul class="divide-y divide-slate-100 max-h-80 overflow-y-auto pr-1 space-y-1">
                            <?php if ($resFinance && $resFinance->num_rows > 0): ?>
                                <?php while ($row = $resFinance->fetch_assoc()): ?>
                                    <li class="py-3 px-3.5 hover:bg-slate-50/80 rounded-2xl transition flex justify-between items-center text-sm group">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-2xl flex items-center justify-center shrink-0 <?= $row['tipe'] === 'masuk' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'; ?>">
                                                <i data-lucide="<?= $row['tipe'] === 'masuk' ? 'arrow-down-left' : 'arrow-up-right'; ?>" class="w-4 h-4"></i>
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-800 leading-snug"><?= htmlspecialchars($row['keterangan']); ?></p>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-[10px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md"><?= htmlspecialchars($row['kategori'] ?? 'Umum'); ?></span>
                                                    <span class="text-[11px] font-medium text-slate-400"><?= date('d M Y', strtotime($row['tanggal'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="font-extrabold <?= $row['tipe'] === 'masuk' ? 'text-emerald-600' : 'text-rose-600'; ?>">
                                                <?= $row['tipe'] === 'masuk' ? '+' : '-'; ?> Rp <?= number_format($row['jumlah'], 0, ',', '.'); ?>
                                            </span>
                                            <a href="index.php?delete_finance=<?= $row['id']; ?>" onclick="return confirm('Hapus transaksi ini?')" 
                                                class="text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition p-1 rounded-lg">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-10 text-slate-400">
                                    <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                                    <p class="text-xs font-semibold">Belum ada riwayat transaksi.</p>
                                </div>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-5">
                        <div class="p-2.5 bg-purple-50 text-purple-600 rounded-2xl">
                            <i data-lucide="book-open" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-lg font-extrabold text-slate-800">Jurnal & Catatan Harian</h2>
                    </div>

                    <form action="index.php" method="POST" class="space-y-3.5 mb-6">
                        <div>
                            <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5 ml-1">Pilih Tanggal</label>
                            <input type="date" name="tanggal_jurnal" value="<?= date('Y-m-d'); ?>" required 
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition text-slate-700">
                        </div>
                        <textarea name="isi_catatan" rows="3" placeholder="Tulis catatan, rencana, atau momen harimu..." required 
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-medium focus:bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none transition resize-none placeholder:text-slate-400"></textarea>
                        <button type="submit" name="add_journal" 
                            class="w-full bg-slate-900 hover:bg-slate-800 active:scale-[0.98] text-white font-bold py-3 rounded-2xl text-sm transition shadow-md shadow-slate-300 flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i> Simpan Catatan
                        </button>
                    </form>

                    <hr class="border-slate-100 my-4">

                    <div>
                        <h3 class="text-xs font-bold uppercase text-slate-400 tracking-wider mb-3 ml-1">Catatan Terakhir</h3>
                        <ul class="space-y-3 max-h-80 overflow-y-auto pr-1">
                            <?php if ($resJournal && $resJournal->num_rows > 0): ?>
                                <?php while ($row = $resJournal->fetch_assoc()): ?>
                                    <li class="p-4 bg-slate-50/80 hover:bg-slate-100/80 rounded-2xl border border-slate-100 transition flex justify-between items-start group">
                                        <div class="space-y-1.5 w-full">
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-purple-600 bg-purple-50 px-2.5 py-0.5 rounded-lg">
                                                <i data-lucide="clock" class="w-3 h-3"></i> <?= date('d M Y, H:i', strtotime($row['tanggal'])); ?>
                                            </span>
                                            <p class="text-sm font-medium text-slate-700 leading-relaxed pr-2"><?= nl2br(htmlspecialchars($row['isi_catatan'])); ?></p>
                                        </div>
                                        <a href="index.php?delete_journal=<?= $row['id']; ?>" onclick="return confirm('Hapus catatan ini?')" 
                                            class="text-slate-300 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition p-1 shrink-0">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-10 text-slate-400">
                                    <i data-lucide="file-text" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                                    <p class="text-xs font-semibold">Belum ada catatan harian.</p>
                                </div>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </section>

        </div>

    </div>

    <script>
        lucide.createIcons();

        document.querySelectorAll('.input-rupiah').forEach(input => {
            input.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, '');
                if (val) {
                    this.value = parseInt(val, 10).toLocaleString('id-ID');
                } else {
                    this.value = '';
                }
            });
        });
    </script>
</body>
</html>
