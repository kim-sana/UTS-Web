<?php
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/avatar_helpers.php';
mysqli_select_db($conn, $dbName);

$nim = trim($_GET['nim'] ?? '');

if ($nim === '' || strlen($nim) > 10) {
  die("Invalid input");
}

// Ambil nama file avatar sebelum data dihapus, agar filenya ikut dihapus
$arvStmt = $conn->prepare("SELECT avatar FROM nilai_mhsw WHERE nim = ?");
if (!$arvStmt) {
  die("Query failed: " . mysqli_error($conn));
}
$arvStmt->bind_param("s", $nim);
$arvStmt->execute();
$avatarFile = $arvStmt->get_result()->fetch_assoc()['avatar'] ?? '';
$arvStmt->close();
hapusAvatar($avatarFile);

$stmt = $conn->prepare("DELETE FROM nilai_mhsw WHERE nim = ?");
if (!$stmt) {
  die("Query failed: " . mysqli_error($conn));
}
$stmt->bind_param("s", $nim);

if ($stmt->execute()) {
  $stmt->close();
  $conn->close();
  header("Location: index.php");
  exit;
}
echo "Error: " . $stmt->error . '<p><a href="index.php">Lihat Data</a></p>';

$stmt->close();
$conn->close();
