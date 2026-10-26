<?php
require_once __DIR__ . '/connection.php';
mysqli_select_db($conn, $dbName);

// sql to create table
$sql = "CREATE TABLE IF NOT EXISTS nilai_mhsw (
nim CHAR(10) PRIMARY KEY,
nama_lengkap VARCHAR(50) NOT NULL,
avatar VARCHAR(255) NOT NULL DEFAULT '',
nilai_akhir DECIMAL(5,2) NOT NULL,
nilai_huruf CHAR(1),
predikat VARCHAR(20),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table created successfully";
} else {
  echo "Error creating table: " . $conn->error;
}

// Migrasi: untuk tabel yang sudah ada sebelum fitur avatar, tambahkan kolom avatar
$kolomAda = $conn->query("SHOW COLUMNS FROM nilai_mhsw LIKE 'avatar'");
if ($kolomAda && $kolomAda->num_rows === 0) {
  if ($conn->query("ALTER TABLE nilai_mhsw ADD COLUMN avatar VARCHAR(255) NOT NULL DEFAULT '' AFTER nama_lengkap") === TRUE) {
    echo "<br>Kolom avatar ditambahkan ke tabel yang sudah ada";
  } else {
    echo "<br>Error menambahkan kolom avatar: " . $conn->error;
  }
}

$conn->close();
