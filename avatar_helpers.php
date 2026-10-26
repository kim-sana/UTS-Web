<?php
// Helper fitur upload avatar mahasiswa.
// File avatar disimpan di folder praktikum/uploads/ dengan nama {nim}.{ekstensi}
// (mis. "18111001.jpg"), database hanya menyimpan nama file tersebut (kolom avatar).

define('AVATAR_DIR', __DIR__ . '/uploads');
const AVATAR_EKSTENSI = [
  'jpg'  => 'image/jpeg',
  'jpeg' => 'image/jpeg',
  'png'  => 'image/png',
  'webp' => 'image/webp',
];
const AVATAR_MAX_UKURAN = 5 * 1024 * 1024; // 5 MB

// Validasi file upload avatar, kembalikan ekstensi normalisasi ("jpg"/"png"/"webp").
// Hentikan eksekusi dengan pesan error jika file tidak valid.
function validasiAvatar(array $file): string
{
  if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    die("Error: avatar belum dipilih atau upload gagal. <a href=\"index.php\">Kembali</a>");
  }
  if ($file['size'] > AVATAR_MAX_UKURAN) {
    die("Error: ukuran avatar maksimal 5 MB. <a href=\"index.php\">Kembali</a>");
  }
  $ekstensi = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
  if (!isset(AVATAR_EKSTENSI[$ekstensi])) {
    die("Error: format avatar harus JPG, PNG, atau WEBP. <a href=\"index.php\">Kembali</a>");
  }
  // Cek MIME asli file, bukan hanya ekstensi nama.
  // getimagesize() bagian dari PHP core, jadi tidak bergantung ekstensi fileinfo.
  $info = @getimagesize($file['tmp_name']);
  $mime = is_array($info) ? ($info['mime'] ?? '') : '';
  if ($mime !== AVATAR_EKSTENSI[$ekstensi]) {
    die("Error: isi file bukan gambar JPG/PNG/WEBP yang valid. <a href=\"index.php\">Kembali</a>");
  }
  return $ekstensi === 'jpeg' ? 'jpg' : $ekstensi;
}

// Simpan file avatar yang di-upload untuk NIM tertentu, kembalikan nama file yang tersimpan.
function simpanAvatar(string $nim, array $file): string
{
  $ekstensi = validasiAvatar($file);
  if (!is_dir(AVATAR_DIR) && !mkdir(AVATAR_DIR, 0755, true)) {
    die("Error: gagal membuat folder uploads/. <a href=\"index.php\">Kembali</a>");
  }
  $namaFile = $nim . '.' . $ekstensi;
  if (!move_uploaded_file($file['tmp_name'], AVATAR_DIR . '/' . $namaFile)) {
    die("Error: gagal menyimpan file avatar. <a href=\"index.php\">Kembali</a>");
  }
  return $namaFile;
}

// Nama file avatar aman diproses? (tanpa slash, cegah path traversal)
function avatarNamaValid(?string $namaFile): bool
{
  return $namaFile !== null && $namaFile !== ''
    && strpos($namaFile, '/') === false
    && strpos($namaFile, '\\') === false;
}

// Hapus file avatar dari folder uploads (abaikan jika tidak ada / tidak valid)
function hapusAvatar(?string $namaFile): void
{
  if (!avatarNamaValid($namaFile)) {
    return;
  }
  $path = AVATAR_DIR . '/' . $namaFile;
  if (is_file($path)) {
    unlink($path);
  }
}

// Ganti nama file avatar agar mengikuti NIM baru (mis. 18111001.jpg -> 18111002.jpg)
function renameAvatar(?string $namaFile, string $nimBaru): ?string
{
  if (!avatarNamaValid($namaFile)) {
    return $namaFile;
  }
  $path = AVATAR_DIR . '/' . $namaFile;
  if (!is_file($path)) {
    return $namaFile;
  }
  $ekstensi = strtolower(pathinfo($namaFile, PATHINFO_EXTENSION));
  $namaBaru = $nimBaru . '.' . $ekstensi;
  return @rename($path, AVATAR_DIR . '/' . $namaBaru) ? $namaBaru : $namaFile;
}
