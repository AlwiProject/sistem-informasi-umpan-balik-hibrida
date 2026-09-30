<?php
include 'koneksi.php';

$pesan = "";
if (isset($_POST['submit'])) {
    $nama        = !empty($_POST['nama_pelapor']) ? mysqli_real_escape_string($koneksi, $_POST['nama_pelapor']) : 'Anonim';
    $id_kategori = intval($_POST['id_kategori']);
    $isi_keluhan = mysqli_real_escape_string($koneksi, $_POST['isi_keluhan']);
    
    // 1. Kirim Teks ke API Python untuk Analisis Sentimen via cURL
    $url = 'http://localhost:5000/predict';
    $payload = json_encode(['teks' => $isi_keluhan]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    curl_close($ch);

    $label_sentimen = 'Netral';
    $skor_sentimen  = 0.50;

    if ($response) {
        $data_api = json_decode($response, true);
        if (isset($data_api['label_sentimen'])) {
            $label_sentimen = $data_api['label_sentimen'];
            $skor_sentimen  = $data_api['skor_sentimen'];
        }
    }

    // 2. Generate ID Pengaduan Unik (P-YYYYMMDD-XXX)
    $id_pengaduan = "P-" . date("Ymd") . "-" . rand(100, 999);

    // 3. Simpan ke Database
    $query = "INSERT INTO pengaduan (id_pengaduan, nama_pelapor, id_kategori, isi_keluhan, label_sentimen, skor_sentimen) 
              VALUES ('$id_pengaduan', '$nama', '$id_kategori', '$isi_keluhan', '$label_sentimen', '$skor_sentimen')";
    
    if (mysqli_query($koneksi, $query)) {
        $pesan = "<div class='alert alert-success'>Umpan balik berhasil dikirim! ID Laporan Anda: <b>$id_pengaduan</b></div>";
    } else {
        $pesan = "<div class='alert alert-danger'>Gagal mengirim umpan balik: " . mysqli_error($koneksi) . "</div>";
    }
}

// Ambil Kategori Layanan dari tabel yang benar ('kategori')
$kategori_query = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY id_kategori ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Umpan Balik Layanan Publik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">Portal Pengaduan Layanan Publik</a>
        <a href="login.php" class="btn btn-outline-light btn-sm">Login Admin</a>
    </div>
</nav>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold">Formulir Umpan Balik & Keluhan</h5>
                </div>
                <div class="card-body p-4">
                    <?= $pesan; ?>
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nama Pelapor (Opsional)</label>
                            <input type="text" name="nama_pelapor" class="form-control" placeholder="Biarkan kosong jika ingin anonim">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori Layanan <span class="text-danger">*</span></label>
                            <select name="id_kategori" class="form-select" required>
                                <option value="" disabled selected>-- Pilih Kategori --</option>
                                <?php if($kategori_query): ?>
                                    <?php while($k = mysqli_fetch_assoc($kategori_query)): ?>
                                        <option value="<?= $k['id_kategori']; ?>"><?= $k['nama_kategori']; ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Uraian Umpan Balik / Keluhan <span class="text-danger">*</span></label>
                            <textarea name="isi_keluhan" class="form-control" rows="5" placeholder="Tuliskan ulasan atau saran Anda secara rinci..." required></textarea>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary w-100 py-2">Kirim Umpan Balik</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>