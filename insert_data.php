<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/nilai_helpers.php';
require_once __DIR__ . '/avatar_helpers.php';
mysqli_select_db($conn, $dbName);

$nim = trim($_POST['nim'] ?? '');
$nama = trim($_POST['nama_lengkap'] ?? '');
$nilai = $_POST['nilai_akhir'] ?? '';

if ($nim === '' || strlen($nim) > 10 || $nama === '' || !is_numeric($nilai)) {
  die("Invalid input");
}
$nilai = (float) $nilai;

// Nilai huruf & predikat dihitung otomatis dari nilai akhir
list($huruf, $predikat) = hitungNilai($nilai);

// Avatar wajib diupload saat tambah data (validasi + simpan file di helper)
$avatar = simpanAvatar($nim, $_FILES['avatar'] ?? []);

$stmt = $conn->prepare("INSERT INTO nilai_mhsw (nim, nama_lengkap, avatar, nilai_akhir, nilai_huruf, predikat, created_at, modified_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
$stmt->bind_param("sssdss", $nim, $nama, $avatar, $nilai, $huruf, $predikat);

if ($stmt->execute()) {
  $stmt->close();
  $conn->close();
  header("Location: index.php");
  exit;
}
echo "Error: " . $stmt->error . '<p><a href="index.php">Lihat Data</a></p>';

$stmt->close();
$conn->close();
?>
