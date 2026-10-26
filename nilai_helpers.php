<?php
// Hitung nilai huruf & predikat otomatis dari nilai akhir:
// A (80.51-100 Sangat Bagus), B (65.51-80.50 Bagus), C (50.51-65.50 Cukup),
// D (34.51-50.50 Buruk), E (0.00-34.50 Sangat Buruk)
function hitungNilai(float $nilai): array
{
  if ($nilai > 100 || $nilai < 0) {
    die("Nilai akhir harus di antara 0.00 sampai 100.00");
  }
  if ($nilai >= 80.51) {
    return ['A', 'Sangat Bagus'];
  } elseif ($nilai >= 65.51) {
    return ['B', 'Bagus'];
  } elseif ($nilai >= 50.51) {
    return ['C', 'Cukup'];
  } elseif ($nilai >= 34.51) {
    return ['D', 'Buruk'];
  } else {
    return ['E', 'Sangat Buruk'];
  }
}
