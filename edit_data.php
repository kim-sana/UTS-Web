<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/nilai_helpers.php';
require_once __DIR__ . '/avatar_helpers.php';
mysqli_select_db($conn, $dbName);

$nimLama = trim($_POST['nim_lama'] ?? '');
$nim = trim($_POST['nim'] ?? '');
$nama = trim($_POST['nama_lengkap'] ?? '');
$nilai = $_POST['nilai_akhir'] ?? '';

if ($nimLama === '' || strlen($nimLama) > 10 || $nim === '' || strlen($nim) > 10 || $nama === '' || !is_numeric($nilai)) {
  die("Invalid input");
}
$nilai = (float) $nilai;

// Nilai huruf & predikat dihitung ulang dari nilai akhir terbaru
list($huruf, $predikat) = hitungNilai($nilai);

// Cek duplikat jika NIM diubah
if ($nim !== $nimLama) {
  $dup = $conn->prepare("SELECT 1 FROM nilai_mhsw WHERE nim = ?");
  $dup->bind_param("s", $nim);
  $dup->execute();
  if ($dup->get_result()->num_rows > 0) {
    $dup->close();
    die("Error: NIM $nim sudah terpakai. <a href=\"index.php\">Kembali</a>");
  }
  $dup->close();
}

// Avatar: jika diupload baru -> validasi lalu ganti (hapus file lama);
// jika tidak diupload -> pertahankan file lama (rename jika NIM berubah)
$oldStmt = $conn->prepare("SELECT avatar FROM nilai_mhsw WHERE nim = ?");
$oldStmt->bind_param("s", $nimLama);
$oldStmt->execute();
$oldAvatar = $oldStmt->get_result()->fetch_assoc()['avatar'] ?? '';
$oldStmt->close();

if (array_key_exists('avatar', $_FILES) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
  hapusAvatar($oldAvatar);
  $avatar = simpanAvatar($nim, $_FILES['avatar']);
} else {
  $avatar = ($nim !== $nimLama) ? renameAvatar($oldAvatar, $nim) : $oldAvatar;
}

$stmt = $conn->prepare("UPDATE nilai_mhsw SET nim = ?, nama_lengkap = ?, avatar = ?, nilai_akhir = ?, nilai_huruf = ?, predikat = ? WHERE nim = ?");
if (!$stmt) {
  die("Query failed: " . mysqli_error($conn));
}
$stmt->bind_param("sssdsss", $nim, $nama, $avatar, $nilai, $huruf, $predikat, $nimLama);

if ($stmt->execute()) {
  $stmt->close();
  $conn->close();
  header("Location: index.php");
  exit;
}
echo "Error: " . $stmt->error . '<p><a href="index.php">Lihat Data</a></p>';

$stmt->close();
$conn->close();
