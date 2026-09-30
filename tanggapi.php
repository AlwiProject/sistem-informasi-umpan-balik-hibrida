<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

$admin_id = (int)$_SESSION['admin_id'];
$admin_nama = isset($_SESSION['nama_admin']) ? $_SESSION['nama_admin'] : 'Admin';

$message = '';
$message_type = '';

$id_pengaduan = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id_pengaduan === '' && isset($_POST['id_pengaduan'])) {
    $id_pengaduan = trim($_POST['id_pengaduan']);
}

if ($id_pengaduan === '') {
    header('Location: dashboard.php');
    exit();
}

// Ambil data pengaduan yang akan ditanggapi
$pengaduan_sql = "SELECT p.*, k.nama_kategori
                 FROM pengaduan p
                 LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
                 WHERE p.id_pengaduan = ?";

$pengaduan_stmt = $koneksi->prepare($pengaduan_sql);
if (!$pengaduan_stmt) {
    die('Gagal menyiapkan query data pengaduan: ' . $koneksi->error);
}

$pengaduan_stmt->bind_param('s', $id_pengaduan);
$pengaduan_stmt->execute();
$pengaduan_result = $pengaduan_stmt->get_result();
$pengaduan = $pengaduan_result->fetch_assoc();

if (!$pengaduan) {
    echo '<script>alert("Data pengaduan tidak ditemukan."); window.location="dashboard.php";</script>';
    exit();
}

// Ambil riwayat tanggapan sebelumnya
$tanggapan_sql = "SELECT t.*, a.nama_lengkap
                 FROM tanggapan t
                 LEFT JOIN admin a ON t.id_admin = a.id_admin
                 WHERE t.id_pengaduan = ?
                 ORDER BY t.tgl_tanggapan DESC";

$tanggapan_stmt = $koneksi->prepare($tanggapan_sql);
if (!$tanggapan_stmt) {
    die('Gagal menyiapkan query riwayat tanggapan: ' . $koneksi->error);
}

$tanggapan_stmt->bind_param('s', $id_pengaduan);
$tanggapan_stmt->execute();
$tanggapan_result = $tanggapan_stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_tanggapan'])) {
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $isi_tanggapan = isset($_POST['isi_tanggapan']) ? trim($_POST['isi_tanggapan']) : '';

    $allowed_status = ['Menunggu', 'Diproses', 'Selesai'];
    if (!in_array($status, $allowed_status, true)) {
        $message = 'Status tidak valid.';
        $message_type = 'danger';
    } else {
        $status = $koneksi->real_escape_string($status);
        $isi_tanggapan = $koneksi->real_escape_string($isi_tanggapan);

        $update_sql = "UPDATE pengaduan SET status = ? WHERE id_pengaduan = ?";
        $update_stmt = $koneksi->prepare($update_sql);
        if (!$update_stmt) {
            die('Gagal menyiapkan query update status: ' . $koneksi->error);
        }
        $update_stmt->bind_param('ss', $status, $id_pengaduan);
        $update_ok = $update_stmt->execute();

        if ($isi_tanggapan !== '') {
            $id_tanggapan = 'T-' . date('YmdHis') . '-' . rand(100, 999);
            $tgl_tanggapan = date('Y-m-d H:i:s');

            $insert_sql = "INSERT INTO tanggapan (id_tanggapan, id_pengaduan, id_admin, tgl_tanggapan, isi_tanggapan)
                           VALUES (?, ?, ?, ?, ?)";

            $insert_stmt = $koneksi->prepare($insert_sql);
            if (!$insert_stmt) {
                die('Gagal menyiapkan query insert tanggapan: ' . $koneksi->error);
            }
            $insert_stmt->bind_param('ssiss', $id_tanggapan, $id_pengaduan, $admin_id, $tgl_tanggapan, $isi_tanggapan);
            $insert_ok = $insert_stmt->execute();
        } else {
            $insert_ok = true;
        }

        if ($update_ok && $insert_ok) {
            $message = 'Status dan tanggapan berhasil disimpan.';
            $message_type = 'success';

            // Refresh data pengaduan setelah update
            $pengaduan_stmt->execute();
            $pengaduan_result = $pengaduan_stmt->get_result();
            $pengaduan = $pengaduan_result->fetch_assoc();

            $tanggapan_stmt->execute();
            $tanggapan_result = $tanggapan_stmt->get_result();
        } else {
            $message = 'Gagal menyimpan data. Silakan coba lagi.';
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respon Pengaduan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f3f6fb;
        }
        .card {
            border: none;
            border-radius: 14px;
        }
        .detail-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">Dashboard Admin</a>
        <div class="d-flex align-items-center text-white gap-3">
            <span>Halo, <strong><?= htmlspecialchars($admin_nama, ENT_QUOTES, 'UTF-8'); ?></strong></span>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Detail Pengaduan</h5>
                </div>
                <div class="card-body">
                    <div class="detail-box mb-3">
                        <div class="mb-2"><strong>ID Pengaduan:</strong> <?= htmlspecialchars($pengaduan['id_pengaduan'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="mb-2"><strong>Tanggal Masuk:</strong> <?= date('d/m/Y H:i', strtotime($pengaduan['tgl_masuk'])); ?></div>
                        <div class="mb-2"><strong>Nama Pelapor:</strong> <?= htmlspecialchars($pengaduan['nama_pelapor'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="mb-2"><strong>Kategori:</strong> <?= htmlspecialchars($pengaduan['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="mb-2"><strong>Sentimen:</strong>
                            <?php if ($pengaduan['label_sentimen'] === 'Positif'): ?>
                                <span class="badge bg-success">Positif</span>
                            <?php elseif ($pengaduan['label_sentimen'] === 'Negatif'): ?>
                                <span class="badge bg-danger">Negatif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Netral</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2"><strong>Status Saat Ini:</strong>
                            <?php
                            $status_badge = 'bg-secondary';
                            if ($pengaduan['status'] === 'Menunggu') $status_badge = 'bg-warning text-dark';
                            elseif ($pengaduan['status'] === 'Diproses') $status_badge = 'bg-primary';
                            elseif ($pengaduan['status'] === 'Selesai') $status_badge = 'bg-success';
                            ?>
                            <span class="badge <?= $status_badge; ?>"><?= htmlspecialchars($pengaduan['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>

                    <div>
                        <label class="form-label fw-bold">Isi Keluhan</label>
                        <div class="border rounded p-3 bg-white">
                            <?= nl2br(htmlspecialchars($pengaduan['isi_keluhan'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Tanggapan & Update Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="tanggapi.php?id=<?= urlencode($id_pengaduan); ?>">
                        <input type="hidden" name="id_pengaduan" value="<?= htmlspecialchars($id_pengaduan, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label class="form-label">Status Pengaduan</label>
                            <select name="status" class="form-select" required>
                                <option value="Menunggu" <?= ($pengaduan['status'] === 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                                <option value="Diproses" <?= ($pengaduan['status'] === 'Diproses') ? 'selected' : ''; ?>>Diproses</option>
                                <option value="Selesai" <?= ($pengaduan['status'] === 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Isi Tanggapan</label>
                            <textarea name="isi_tanggapan" rows="6" class="form-control" placeholder="Tulis balasan atau jawaban dari admin terkait pengaduan ini..." required></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_tanggapan" class="btn btn-primary">Simpan Tanggapan</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Riwayat Tanggapan</h5>
                </div>
                <div class="card-body">
                    <?php if ($tanggapan_result->num_rows > 0): ?>
                        <?php while ($row = $tanggapan_result->fetch_assoc()): ?>
                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong><?= htmlspecialchars($row['nama_lengkap'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($row['tgl_tanggapan'])); ?></small>
                                </div>
                                <div><?= nl2br(htmlspecialchars($row['isi_tanggapan'], ENT_QUOTES, 'UTF-8')); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-muted">Belum ada tanggapan untuk pengaduan ini.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
