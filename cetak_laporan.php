<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

// Ambil parameter filter tanggal dari form atau query string
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Jika request adalah ekspor PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Query dengan filter tanggal
    $sql = "SELECT p.id_pengaduan, p.tgl_masuk, p.nama_pelapor, k.nama_kategori, p.isi_keluhan, p.label_sentimen, p.status
            FROM pengaduan p
            LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
            WHERE 1 = 1";

    $params = [];
    $types = "";

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
        $result = $stmt->get_result();
    } else {
        die("Gagal menyiapkan query laporan: " . $koneksi->error);
    }

    // Memanggil file library FPDF secara langsung
    require_once 'fpdf.php';

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Laporan Umpan Balik Layanan Publik', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);

    $periode = 'Semua Data';
    if ($from_date !== '' || $to_date !== '') {
        $periode = 'Periode: ' . ($from_date !== '' ? $from_date : '-') . ' s.d. ' . ($to_date !== '' ? $to_date : '-');
    }
    $pdf->Cell(0, 8, $periode, 0, 1, 'L');
    $pdf->Ln(2);

    // --- PENGATURAN LEBAR KOLOM YANG LEBIH RAPI ---
    $pdf->SetFont('Arial', 'B', 9);
    // Total lebar A4 (tanpa margin) adalah sekitar 190mm
    $pdf->Cell(25, 8, 'ID', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Tanggal', 1, 0, 'C');
    $pdf->Cell(20, 8, 'Pelapor', 1, 0, 'C');
    $pdf->Cell(40, 8, 'Kategori', 1, 0, 'C');
    $pdf->Cell(45, 8, 'Isi Keluhan', 1, 0, 'C');
    $pdf->Cell(18, 8, 'Sentimen', 1, 0, 'C');
    $pdf->Cell(22, 8, 'Status', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 8);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Membatasi panjang teks agar tidak meluber keluar dari kotaknya
            $id_pengaduan = $row['id_pengaduan'];
            $tanggal = date('d-m-Y', strtotime($row['tgl_masuk']));
            $pelapor = substr($row['nama_pelapor'], 0, 12); // Maksimal 12 huruf
            $kategori = substr($row['nama_kategori'] ?? '-', 0, 25); // Cukup untuk Fasilitas & Infrastruktur
            
            // Keluhan dipotong rapi dan diberi "..." jika kepanjangan
            $keluhan_asli = $row['isi_keluhan'];
            $keluhan = substr($keluhan_asli, 0, 30) . (strlen($keluhan_asli) > 30 ? '...' : '');

            $pdf->Cell(25, 8, $id_pengaduan, 1, 0, 'C');
            $pdf->Cell(20, 8, $tanggal, 1, 0, 'C');
            $pdf->Cell(20, 8, $pelapor, 1, 0, 'L');
            $pdf->Cell(40, 8, $kategori, 1, 0, 'L');
            $pdf->Cell(45, 8, $keluhan, 1, 0, 'L');
            $pdf->Cell(18, 8, $row['label_sentimen'] ?? 'Netral', 1, 0, 'C');
            $pdf->Cell(22, 8, $row['status'] ?? 'Menunggu', 1, 1, 'C');
        }
    } else {
        $pdf->Cell(190, 10, 'Tidak ada data laporan untuk periode ini.', 1, 1, 'C');
    }

    $pdf->Output('D', 'laporan_umpan_balik.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Laporan PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">Dashboard Admin</a>
    </div>
</nav>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold">Export Laporan PDF</h5>
                </div>
                <div class="card-body p-4">
                    <form method="GET" action="cetak_laporan.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Dari Tanggal</label>
                                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" name="export" value="pdf" class="btn btn-success w-100">Unduh PDF</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>