<?php
require_once __DIR__ . '/connection.php';
mysqli_select_db($conn, $dbName);

// Filter: pencarian nama lengkap + predikat via GET
$keyword = trim($_GET['q'] ?? '');
$predikatFilter = trim($_GET['predikat'] ?? '');
$predikatOptions = ['Sangat Bagus', 'Bagus', 'Cukup', 'Buruk', 'Sangat Buruk'];
// Sorting: nilai akhir asc/desc
$sort = strtolower(trim($_GET['sort'] ?? ''));
if (!in_array($sort, ['asc', 'desc'], true)) {
  $sort = '';
}

$conditions = [];
$types = '';
$params = [];
if ($keyword !== '') {
  $conditions[] = 'nama_lengkap LIKE ?';
  $types .= 's';
  $params[] = '%' . $keyword . '%';
}
if (in_array($predikatFilter, $predikatOptions, true)) {
  $conditions[] = 'predikat = ?';
  $types .= 's';
  $params[] = $predikatFilter;
}

$orderBy = $sort !== '' ? "nilai_akhir $sort, nim" : 'nim';

// Pagination: page size 5/10/15, nomor halaman
$perPageOptions = [5, 10, 15];
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, $perPageOptions, true)) {
  $perPage = 10;
}
$pageNum = max(1, (int)($_GET['page'] ?? 1));

$whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

// Hitung total data (untuk nomor halaman)
$totalSql = "SELECT COUNT(*) FROM nilai_mhsw" . $whereSql;
$countStmt = $conn->prepare($totalSql);
if ($countStmt) {
  if ($params) {
    $countStmt->bind_param($types, ...$params);
  }
  $countStmt->execute();
  $totalRows = (int)$countStmt->get_result()->fetch_row()[0];
  $countStmt->close();
} else {
  die("Query failed: " . mysqli_error($conn));
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($pageNum > $totalPages) {
  $pageNum = $totalPages;
}
$offset = ($pageNum - 1) * $perPage;

$sql = "SELECT nim, nama_lengkap, avatar, nilai_akhir, nilai_huruf, predikat, created_at, modified_at FROM nilai_mhsw"
  . $whereSql
  . " ORDER BY $orderBy LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  die("Query failed: " . mysqli_error($conn));
}
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$dataRows = [];
while ($row = $result->fetch_assoc()) {
  $dataRows[] = $row;
}
$stmt->close();

$hasFilter = $keyword !== '' || in_array($predikatFilter, $predikatOptions, true);
$filterSummary = [];
if ($keyword !== '') {
  $filterSummary[] = 'Nama: "' . $keyword . '"';
}
if (in_array($predikatFilter, $predikatOptions, true)) {
  $filterSummary[] = 'Predikat: ' . $predikatFilter;
}
if ($sort !== '') {
  $filterSummary[] = 'Urut nilai akhir: ' . ($sort === 'asc' ? 'kecil ke besar' : 'besar ke kecil');
}
$filterSummary = implode(' · ', $filterSummary);

// URL toggle sorting (mempertahankan filter aktif)
$sortToggle = $sort === 'asc' ? 'desc' : 'asc';
$sortParams = [];
$sortParams['sort'] = $sortToggle;
$sortParams['per_page'] = $perPage;
if ($keyword !== '') {
  $sortParams['q'] = $keyword;
}
if (in_array($predikatFilter, $predikatOptions, true)) {
  $sortParams['predikat'] = $predikatFilter;
}
$sortUrl = 'index.php?' . http_build_query($sortParams);

// URL dengan parameter gabungan + penggantian tertentu (paginasi)
function pageUrl(array $base, array $merge): string
{
  $params = array_filter(array_merge($base, $merge), fn($v) => $v !== '' && $v !== null);
  ksort($params);
  return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}
$pageBaseParams = [];
if ($keyword !== '') {
  $pageBaseParams['q'] = $keyword; // Atur ulang filter: semua kembali default
}
if (in_array($predikatFilter, $predikatOptions, true)) {
  $pageBaseParams['predikat'] = $predikatFilter;
}
if ($sort !== '') {
  $pageBaseParams['sort'] = $sort;
}
if ($perPage !== 10) {
  $pageBaseParams['per_page'] = $perPage;
}

$rangeStart = $totalRows > 0 ? $offset + 1 : 0;
$rangeEnd = min($offset + $perPage, $totalRows);

// Daftar nomor halaman yang dirender (window ±1 + batas atas/bawah, "…" untuk gap)
$pagesList = [];
if ($totalPages <= 7) {
  $pagesList = range(1, $totalPages);
} else {
  $pagesList = array_unique(array_merge(
    [1, 2],
    range(max(1, $pageNum - 1), min($totalPages, $pageNum + 1)),
    [$totalPages - 1, $totalPages]
  ));
  sort($pagesList);
}

// Mode edit: ambil 1 data berdasarkan NIM (via ?edit=nim)
$editNim = trim($_GET['edit'] ?? '');
if ($editNim !== '' && strlen($editNim) <= 10) {
  $editStmt = $conn->prepare("SELECT nim, nama_lengkap, avatar, nilai_akhir FROM nilai_mhsw WHERE nim = ?");
  $editStmt->bind_param('s', $editNim);
  $editStmt->execute();
  $editRow = $editStmt->get_result()->fetch_assoc();
  $editStmt->close();
} else {
  $editRow = null;
}

// Placeholder avatar berupa gambar SVG(person) untuk preview di form
$avatarPlaceholder = 'data:image/svg+xml;charset=utf-8,' . rawurlencode(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">'
  . '<circle cx="8" cy="8" r="8" fill="#adb5bd"/>'
  . '<circle cx="8" cy="5.5" r="2.5" fill="#fff"/>'
  . '<path d="M2.5 14a5.5 5.5 0 0 1 11 0Z" fill="#fff"/></svg>'
);
?>
<!doctype html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Praktikum PHP | Data Nilai</title>

  <!-- Theme Init (prevents flash of incorrect theme on load) -->
  <script>
    (() => {
      'use strict';
      const root = document.documentElement;
      const STORAGE_KEY = 'lte-theme';
      let stored = null;
      try {
        stored = localStorage.getItem(STORAGE_KEY);
      } catch {}
      const authored = root.getAttribute('data-bs-theme');
      let resolved = 'light';
      if (stored === 'dark' || stored === 'light') {
        resolved = stored;
      } else if (authored === 'dark' || authored === 'light') {
        resolved = authored;
      } else if (globalThis.matchMedia('(prefers-color-scheme: dark)').matches) {
        resolved = 'dark';
      }
      root.setAttribute('data-bs-theme', resolved);
      root.style.colorScheme = resolved;
      if (resolved !== authored) {
        root.setAttribute('data-lte-theme-resolved', '');
      }
    })();
  </script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- Font -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.3.0/index.css" crossorigin="anonymous" />
  <!-- OverlayScrollbars CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.16.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
  <!-- AdminLTE -->
  <link rel="stylesheet" href="assets/css/adminlte.min.css" />
</head>
<body class="bg-body-tertiary">
  <div class="app-wrapper">
    <!-- Main -->
    <main class="app-main">
      <!-- Content Header -->
      <div class="app-content-header">
        <div class="container-fluid">
          <div class="row">
            <div class="col-sm-6">
              <h1 class="mb-0 fs-3">Data Nilai</h1>
            </div>
            <div class="col-sm-6">
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item active" aria-current="page">Data Nilai</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>
      </div>
      <!-- /Content Header -->

      <!-- Content -->
      <div class="app-content">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12">
              <div class="card mb-4">
                <div class="card-header">
                  <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-4">
                      <h3 class="card-title">Daftar Nilai Mahasiswa</h3>
                    </div>
                    <div class="col-12 col-md-8">
                      <div class="d-flex flex-wrap justify-content-md-end gap-2">
                        <!-- Filter: cari nama lengkap + predikat -->
                        <form method="get" action="index.php" class="d-flex flex-wrap gap-2 align-items-center">
<?php if ($sort !== ''): ?>
                          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>" />
<?php endif; ?>
                          <div class="input-group input-group-sm w-auto">
                            <span class="input-group-text">
                              <i class="bi bi-search" aria-hidden="true"></i>
                            </span>
                            <input
                              type="search"
                              name="q"
                              class="form-control"
                              placeholder="Cari nama lengkap…"
                              aria-label="Cari nama lengkap"
                              value="<?= htmlspecialchars($keyword) ?>"
                              style="width: 180px"
                            />
                          </div>
                          <select name="predikat" class="form-select form-select-sm w-auto" aria-label="Filter predikat">
                            <option value="">Semua predikat</option>
<?php foreach ($predikatOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $predikatFilter === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
<?php endforeach; ?>
                          </select>
                          <select name="per_page" class="form-select form-select-sm w-auto" aria-label="Data per halaman" onchange="this.form.submit()">
<?php foreach ($perPageOptions as $opt): ?>
                            <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?> / halaman</option>
<?php endforeach; ?>
                          </select>
                          <button type="submit" class="btn btn-sm btn-secondary">Cari</button>
<?php if ($hasFilter): ?>
                          <a href="index.php" class="btn btn-sm btn-outline-secondary">Reset</a>
<?php endif; ?>
                        </form>
                        <!-- /Filter -->
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-tambah-nilai">
                          <i class="bi bi-plus-lg me-1"></i>
                          Tambah Data
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle m-0">
                      <thead>
                        <tr>
                          <th>NIM</th>
                          <th>Nama Lengkap</th>
                          <th>
                            <a href="<?= $sortUrl ?>" class="text-decoration-none fw-normal" title="Klik untuk urutkan">
                              Nilai Akhir
<?php if ($sort === 'asc'): ?>
                              <i class="bi bi-arrow-up" aria-hidden="true"></i>
<?php elseif ($sort === 'desc'): ?>
                              <i class="bi bi-arrow-down" aria-hidden="true"></i>
<?php else: ?>
                              <i class="bi bi-arrow-down-up" aria-hidden="true"></i>
<?php endif; ?>
                            </a>
                          </th>
                          <th>Nilai Huruf</th>
                          <th>Predikat</th>
                          <th>Tgl dibuat</th>
                          <th>Tgl diubah</th>
                          <th class="text-end">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
<?php if (count($dataRows) === 0): ?>
                        <tr>
                          <td colspan="8" class="text-center text-secondary py-4 mb-0">
<?php if ($hasFilter): ?>
                            Tidak ada data yang cocok dengan filter. <a href="index.php">Reset filter</a>
<?php else: ?>
                            Belum ada data. <a href="#" data-bs-toggle="modal" data-bs-target="#modal-tambah-nilai">Tambah data</a>
<?php endif; ?>
                          </td>
                        </tr>
<?php else: ?>
<?php foreach ($dataRows as $row): ?>
                        <tr>
                          <td class="fw-medium"><?= htmlspecialchars($row['nim']) ?></td>
                          <td>
                            <div class="d-flex align-items-center gap-2">
<?php if (!empty($row['avatar'])): ?>
                              <img
                                src="uploads/<?= rawurlencode($row['avatar']) ?>"
                                alt="Avatar <?= htmlspecialchars($row['nama_lengkap']) ?>"
                                class="rounded-circle flex-shrink-0"
                                style="width: 36px; height: 36px; object-fit: cover"
                              />
<?php else: ?>
                              <i class="bi bi-person-circle text-secondary flex-shrink-0" style="font-size: 36px" aria-hidden="true"></i>
<?php endif; ?>
                              <span><?= htmlspecialchars($row['nama_lengkap']) ?></span>
                            </div>
                          </td>
                          <td><?= htmlspecialchars($row['nilai_akhir']) ?></td>
                          <td><?= htmlspecialchars($row['nilai_huruf'] ?? '') ?></td>
                          <td><?= htmlspecialchars($row['predikat'] ?? '') ?></td>
                          <td><?= htmlspecialchars($row['created_at']) ?></td>
                          <td><?= htmlspecialchars($row['modified_at']) ?></td>
                          <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Aksi">
                              <a
                                class="btn btn-outline-secondary"
                                href="<?= pageUrl($pageBaseParams, ['per_page' => $perPage, 'edit' => $row['nim']]) ?>"
                                aria-label="Edit <?= htmlspecialchars($row['nim']) ?>"
                              >
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                              </a>
                              <a
                                class="btn btn-outline-danger"
                                href="delete_data.php?nim=<?= rawurlencode($row['nim']) ?>"
                                onclick="return confirm('Hapus data NIM <?= htmlspecialchars($row['nim']) ?>?')"
                                aria-label="Hapus <?= htmlspecialchars($row['nim']) ?>"
                              >
                                <i class="bi bi-trash" aria-hidden="true"></i>
                              </a>
                            </div>
                          </td>
                        </tr>
<?php endforeach; ?>
<?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer clearfix">
                  <div class="float-start pt-1 fs-7 text-body-secondary">
                    Menampilkan <?= $rangeStart ?>&ndash;<?= $rangeEnd ?> dari <?= $totalRows ?> data
<?php if ($hasFilter): ?>
                    &middot; <?= htmlspecialchars($filterSummary) ?> ( <a href="index.php?per_page=<?= $perPage ?>">reset</a> )
<?php endif; ?>
                  </div>
<?php if ($totalRows > 0): ?>
                  <ul class="pagination pagination-sm m-0 float-end">
                    <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $pageNum <= 1 ? '#' : pageUrl($pageBaseParams, ['per_page' => $perPage, 'page' => 1]) ?>" aria-label="First Page" title="First Page">First</a>
                    </li>
                    <li class="page-item <?= $pageNum <= 1 ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $pageNum <= 1 ? '#' : pageUrl($pageBaseParams, ['per_page' => $perPage, 'page' => $pageNum - 1]) ?>" aria-label="Previous Page" title="Previous Page">Previous</a>
                    </li>
<?php $prevPage = 0; foreach ($pagesList as $p): if ($p - $prevPage > 1): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
<?php endif; ?>
                    <li class="page-item <?= $p === $pageNum ? 'active' : '' ?>">
                      <a class="page-link" href="<?= pageUrl($pageBaseParams, ['per_page' => $perPage, 'page' => $p]) ?>"><?= $p ?></a>
                    </li>
<?php $prevPage = $p; endforeach; ?>
                    <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $pageNum >= $totalPages ? '#' : pageUrl($pageBaseParams, ['per_page' => $perPage, 'page' => $pageNum + 1]) ?>" aria-label="Next Page" title="Next Page">Next</a>
                    </li>
                    <li class="page-item <?= $pageNum >= $totalPages ? 'disabled' : '' ?>">
                      <a class="page-link" href="<?= $pageNum >= $totalPages ? '#' : pageUrl($pageBaseParams, ['per_page' => $perPage, 'page' => $totalPages]) ?>" aria-label="Last Page" title="Last Page">Last</a>
                    </li>
                  </ul>
<?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Tambah Data Modal -->
          <div class="modal fade" id="modal-tambah-nilai" tabindex="-1" aria-labelledby="modal-tambah-nilai-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form action="insert_data.php" method="post" enctype="multipart/form-data">
                  <div class="modal-header">
                    <h5 class="modal-title" id="modal-tambah-nilai-label">Tambah Data Nilai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label for="nim" class="form-label">NIM</label>
                      <input type="text" name="nim" id="nim" class="form-control" maxlength="10" required />
                    </div>
                    <div class="mb-3">
                      <!-- Avatar di sebelah kiri field Nama Lengkap -->
                      <div class="d-flex align-items-center gap-3">
                        <img
                          class="avatar-preview rounded-circle flex-shrink-0"
                          data-avatar-input="avatar"
                          src="<?= $avatarPlaceholder ?>"
                          alt="Avatar mahasiswa"
                          style="width: 64px; height: 64px; object-fit: cover"
                        />
                        <div class="flex-grow-1">
                          <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                          <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control" maxlength="50" required />
                          <input
                            type="file"
                            name="avatar"
                            id="avatar"
                            class="form-control form-control-sm mt-2"
                            accept=".jpg,.jpeg,.png,.webp"
                            required
                          />
                          <div class="form-text">Foto avatar: JPG, PNG, atau WEBP &mdash; maksimal 5 MB.</div>
                        </div>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label for="nilai_akhir" class="form-label">Nilai Akhir</label>
                      <input type="number" name="nilai_akhir" id="nilai_akhir" class="form-control" step="0.01" min="0" max="100" required />
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <!-- /Tambah Data Modal -->

<?php if ($editRow): ?>
          <!-- Edit Data Modal -->
          <div class="modal fade" id="modal-edit-nilai" tabindex="-1" aria-labelledby="modal-edit-nilai-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form action="edit_data.php" method="post" enctype="multipart/form-data">
                  <div class="modal-header">
                    <h5 class="modal-title" id="modal-edit-nilai-label">Edit Data NIM <?= htmlspecialchars($editRow['nim']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="nim_lama" value="<?= htmlspecialchars($editRow['nim']) ?>" />
                    <div class="mb-3">
                      <label for="edit-nim" class="form-label">NIM</label>
                      <input type="text" name="nim" id="edit-nim" class="form-control" maxlength="10" value="<?= htmlspecialchars($editRow['nim']) ?>" required />
                    </div>
                    <div class="mb-3">
                      <!-- Avatar di sebelah kiri field Nama Lengkap -->
                      <div class="d-flex align-items-center gap-3">
                        <img
                          class="avatar-preview rounded-circle flex-shrink-0"
                          data-avatar-input="edit-avatar"
                          src="<?= $editRow['avatar'] !== '' ? 'uploads/' . rawurlencode($editRow['avatar']) : $avatarPlaceholder ?>"
                          alt="Avatar mahasiswa"
                          style="width: 64px; height: 64px; object-fit: cover"
                        />
                        <div class="flex-grow-1">
                          <label for="edit-nama_lengkap" class="form-label">Nama Lengkap</label>
                          <input type="text" name="nama_lengkap" id="edit-nama_lengkap" class="form-control" maxlength="50" value="<?= htmlspecialchars($editRow['nama_lengkap']) ?>" required />
                          <input
                            type="file"
                            name="avatar"
                            id="edit-avatar"
                            class="form-control form-control-sm mt-2"
                            accept=".jpg,.jpeg,.png,.webp"
                          />
                          <div class="form-text">Kosongkan untuk mempertahankan foto saat ini. JPG, PNG, atau WEBP &mdash; maks 5 MB.</div>
                        </div>
                      </div>
                    </div>
                    <div class="mb-3">
                      <label for="edit-nilai_akhir" class="form-label">Nilai Akhir</label>
                      <input type="number" name="nilai_akhir" id="edit-nilai_akhir" class="form-control" step="0.01" min="0" max="100" value="<?= htmlspecialchars($editRow['nilai_akhir']) ?>" required />
                    </div>
                    <div class="form-text">Nilai huruf &amp; predikat dihitung ulang otomatis dari nilai akhir.</div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <!-- /Edit Data Modal -->
<?php endif; ?>
        </div>
      </div>
      <!-- /Content -->
    </main>
    <!-- /Main -->
  </div>
  <!-- /App Wrapper -->

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
  <script src="assets/js/adminlte.min.js"></script>
  <script>
    // Preview avatar realtime saat file dipilih + klik foto untuk membuka pemilih file
    document.querySelectorAll('.avatar-preview').forEach(function (img) {
      const input = document.getElementById(img.getAttribute('data-avatar-input'));
      if (!input) return;
      img.style.cursor = 'pointer';
      img.title = 'Klik untuk memilih foto';
      img.addEventListener('click', function () {
        input.click();
      });
      input.addEventListener('change', function () {
        if (input.files && input.files[0]) {
          img.src = URL.createObjectURL(input.files[0]);
        }
      });
    });
  </script>
<?php if ($editRow): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-edit-nilai')).show();

      // Hapus param ?edit= dari URL saat modal ditutup (X, Cancel, atau klik backdrop),
      // supaya refresh tidak mem-buka modal lagi. Parameter lain (q, predikat, sort,
      // per_page, page) dipertahankan.
      document.getElementById('modal-edit-nilai').addEventListener('hidden.bs.modal', function () {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit');
        window.history.replaceState(null, '', url);
      });
    });
  </script>
<?php endif; ?>
  <!-- /Scripts -->
</body>
</html>
<?php $conn->close(); ?>
