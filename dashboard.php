<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include 'koneksi.php';

$nama_admin = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : (isset($_SESSION['nama_admin']) ? $_SESSION['nama_admin'] : 'Admin');
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Administrator';

// Ambil data kategori untuk filter
$kategori_result = $koneksi->query("SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC");

// Ambil data statistik sentimen global
$stats_sql = "SELECT
    SUM(CASE WHEN label_sentimen = 'Positif' THEN 1 ELSE 0 END) AS positif,
    SUM(CASE WHEN label_sentimen = 'Netral' THEN 1 ELSE 0 END) AS netral,
    SUM(CASE WHEN label_sentimen = 'Negatif' THEN 1 ELSE 0 END) AS negatif
    FROM pengaduan";
$stats_result = $koneksi->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$q_pos = (int)($stats['positif'] ?? 0);
$q_net = (int)($stats['netral'] ?? 0);
$q_neg = (int)($stats['negatif'] ?? 0);

// Nilai filter dari form GET
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$kategori_id = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Query utama dengan filter
$sql = "SELECT p.*, k.nama_kategori
        FROM pengaduan p
        LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
        WHERE 1 = 1";
$params = [];
$types = "";

if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $sql .= " AND (p.nama_pelapor LIKE ? OR p.isi_keluhan LIKE ? OR k.nama_kategori LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if ($kategori_id > 0) {
    $sql .= " AND p.id_kategori = ?";
    $params[] = $kategori_id;
    $types .= "i";
}

if ($status !== '') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($from_date !== '') {
    $sql .= " AND DATE(p.tgl_masuk) >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if ($to_date !== '') {
    $sql .= " AND DATE(p.tgl_masuk) <= ?";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY p.tgl_masuk DESC";

$stmt = $koneksi->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $pengaduan = $stmt->get_result();
} else {
    $pengaduan = $koneksi->query("SELECT p.*, k.nama_kategori FROM pengaduan p LEFT JOIN kategori k ON p.id_kategori = k.id_kategori ORDER BY p.tgl_masuk DESC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Umpan Balik Publik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f3f6fb; }
        .card { border: none; border-radius: 14px; }
        .table-responsive { border-radius: 12px; overflow: hidden; }
        .badge-status { min-width: 120px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">Admin Dashboard</a>
        <div class="d-flex align-items-center text-white gap-3">
            <span>Halo, <strong><?= htmlspecialchars($nama_admin, ENT_QUOTES, 'UTF-8'); ?></strong> (<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>)</span>
            <a href="cetak_laporan.php" class="btn btn-success btn-sm">Cetak Laporan PDF</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Positif</small>
                            <h3 class="mb-0 mt-2"><?= $q_pos; ?></h3>
                        </div>
                        <div class="fs-2">😊</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-white shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Netral</small>
                            <h3 class="mb-0 mt-2"><?= $q_net; ?></h3>
                        </div>
                        <div class="fs-2">😐</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small>Negatif</small>
                            <h3 class="mb-0 mt-2"><?= $q_neg; ?></h3>
                        </div>
                        <div class="fs-2">😠</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm p-3">
                <h6 class="fw-bold mb-3">Grafik Distribusi Sentimen</h6>
                <canvas id="chartSentimen" height="180"></canvas>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <h5 class="mb-0 fw-bold">Daftar Pengaduan</h5>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="dashboard.php" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Cari</label>
                    <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nama / isi keluhan / kategori">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php while ($row_k = $kategori_result->fetch_assoc()): ?>
                            <option value="<?= $row_k['id_kategori']; ?>" <?= ($kategori_id == (int)$row_k['id_kategori']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($row_k['nama_kategori'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Menunggu" <?= ($status === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                        <option value="Diproses" <?= ($status === 'Diproses') ? 'selected' : ''; ?>>Diproses</option>
                        <option value="Selesai" <?= ($status === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Pelapor</th>
                            <th>Kategori</th>
                            <th>Isi Keluhan</th>
                            <th>Sentimen</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pengaduan && $pengaduan->num_rows > 0): ?>
                            <?php while ($row = $pengaduan->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['id_pengaduan'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['tgl_masuk'])); ?></td>
                                    <td><?= htmlspecialchars($row['nama_pelapor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge text-bg-light border"><?= htmlspecialchars($row['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['isi_keluhan'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($row['label_sentimen'] === 'Positif'): ?>
                                            <span class="badge bg-success">Positif</span>
                                        <?php elseif ($row['label_sentimen'] === 'Negatif'): ?>
                                            <span class="badge bg-danger">Negatif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Netral</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badge = 'bg-secondary';
                                        if ($row['status'] === 'Menunggu') $status_badge = 'bg-warning text-dark';
                                        elseif ($row['status'] === 'Diproses') $status_badge = 'bg-primary';
                                        elseif ($row['status'] === 'Selesai') $status_badge = 'bg-success';
                                        ?>
                                        <span class="badge <?= $status_badge; ?> badge-status"><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td>
                                        <a href="tanggapi.php?id=<?= urlencode($row['id_pengaduan']); ?>" class="btn btn-sm btn-outline-primary">Tanggapi</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Data tidak ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('chartSentimen')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Positif', 'Netral', 'Negatif'],
                datasets: [{
                    data: [<?= $q_pos; ?>, <?= $q_net; ?>, <?= $q_neg; ?>],
                    backgroundColor: ['#198754', '#6c757d', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
</script>

</body>
</html>