<?php
// ==========================================
// KEAMANAN & CEK SESI
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek Keamanan Akses Admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("<div class='alert alert-danger m-4'>Akses ditolak. Anda tidak memiliki izin untuk mengelola data user.</div>");
}

// Koneksi Database
if (file_exists("koneksi.php")) {
    include "koneksi.php";
} else {
    die("Error: File 'koneksi.php' tidak ditemukan.");
}

$alert_message = "";

// ==========================================
// PROSES CRUD (POST ACTION)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- 1. EDIT USER (GANTI USERNAME & PASSWORD) ---
    if ($action === 'edit') {
        $id           = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $username     = trim($_POST['username'] ?? '');
        $password     = $_POST['password'] ?? '';
        $role         = $_POST['role'] ?? 'user';
        $id_sekolah   = trim($_POST['id_sekolah'] ?? '0');
        $nama_sekolah = trim($_POST['nama_sekolah'] ?? '');

        if ($id && !empty($username)) {
            if (!empty($password)) {
                // Jika password diisi, update Username & Password baru
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $q_update = "UPDATE users SET username = ?, password = ?, role = ?, id_sekolah = ?, nama_sekolah = ? WHERE id = ?";
                if ($stmt_up = mysqli_prepare($conn, $q_update)) {
                    mysqli_stmt_bind_param($stmt_up, "sssssi", $username, $hashed_password, $role, $id_sekolah, $nama_sekolah, $id);
                    $exec = mysqli_stmt_execute($stmt_up);
                    mysqli_stmt_close($stmt_up);
                }
            } else {
                // Jika password kosong, update data tanpa mengubah password
                $q_update = "UPDATE users SET username = ?, role = ?, id_sekolah = ?, nama_sekolah = ? WHERE id = ?";
                if ($stmt_up = mysqli_prepare($conn, $q_update)) {
                    mysqli_stmt_bind_param($stmt_up, "ssssi", $username, $role, $id_sekolah, $nama_sekolah, $id);
                    $exec = mysqli_stmt_execute($stmt_up);
                    mysqli_stmt_close($stmt_up);
                }
            }

            if (isset($exec) && $exec) {
                $alert_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>Data user berhasil diperbarui!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            } else {
                $alert_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-x-circle-fill me-2"></i>Gagal memperbarui data user.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            }
        }
    }

    // --- 2. HAPUS USER ---
    elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $active_user_id = $_SESSION['user_id'] ?? null;

        if ($id) {
            if ($active_user_id && (int)$id === (int)$active_user_id) {
                $alert_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-slash-circle-fill me-2"></i>Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
            } else {
                $q_del = "DELETE FROM users WHERE id = ?";
                if ($stmt_del = mysqli_prepare($conn, $q_del)) {
                    mysqli_stmt_bind_param($stmt_del, "i", $id);
                    if (mysqli_stmt_execute($stmt_del)) {
                        $alert_message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>User berhasil dihapus!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
                    } else {
                        $alert_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-x-circle-fill me-2"></i>Gagal menghapus user.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>';
                    }
                    mysqli_stmt_close($stmt_del);
                }
            }
        }
    }
}

// ==========================================
// AMBIL ALL USER DATA & HITUNG TOTAL USER
// ==========================================
$users_list = [];
$q_users = "SELECT id, username, role, id_sekolah, nama_sekolah, created_at FROM users ORDER BY id DESC";
$res_users = mysqli_query($conn, $q_users);

if ($res_users) {
    while ($row = mysqli_fetch_assoc($res_users)) {
        $users_list[] = $row;
    }
}

// Total Jumlah User
$total_users = count($users_list);
?>

<div class="container-fluid p-0">
    <!-- NOTIFIKASI ALERT -->
    <div id="user-alert-container">
        <?= $alert_message; ?>
    </div>

    <!-- CARD UTAMA KELOLA USER -->
    <div class="card card-box border-0 shadow-sm mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between pb-3 mb-3 border-bottom gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-people-fill text-primary me-2"></i>Kelola Data User
                    </h5>
                    <!-- INFORMASI JUMLAH USER -->
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5" style="font-size: 12px; font-weight: 700;">
                        <i class="bi bi-person-badge me-1"></i>Total: <?= $total_users; ?> User
                    </span>
                </div>
                <p class="text-secondary small mb-0">Manajemen akun pengguna sistem inventaris dan hak akses.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- INPUT FILTER CARI SEKOLAH REALTIME -->
                <div class="input-group" style="min-width: 250px; max-width: 320px;">
                    <span class="input-group-text bg-light border-end-0" style="border-radius: 10px 0 0 10px;">
                        <i class="bi bi-search text-secondary"></i>
                    </span>
                    <input type="text" id="searchSekolah" class="form-control bg-light border-start-0 ps-0 text-dark small" placeholder="Cari sekolah / username..." style="border-radius: 0 10px 10px 0;">
                </div>
            </div>
        </div>

        <!-- TABEL USER -->
        <div class="table-responsive">
            <table class="table table-custom align-middle w-100 mb-0">
                <thead>
                    <tr class="text-secondary fs-7">
                        <th style="width: 50px;">ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>ID Sekolah</th>
                        <th>Nama Sekolah</th>
                        <th>Dibuat Pada</th>
                        <th class="text-center" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <?php if (!empty($users_list)): ?>
                        <?php foreach ($users_list as $usr): ?>
                            <tr class="user-row">
                                <td class="fw-bold text-secondary">#<?= $usr['id']; ?></td>
                                <td class="fw-bold text-dark col-username">
                                    <i class="bi bi-person-circle text-secondary me-2"></i>
                                    <?= htmlspecialchars($usr['username'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td>
                                    <?php if ($usr['role'] === 'admin'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-2" style="font-size: 11px;">
                                            <i class="bi bi-shield-lock-fill me-1"></i>Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-2" style="font-size: 11px;">
                                            <i class="bi bi-building me-1"></i>User Sekolah
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold text-secondary col-id-sekolah"><?= htmlspecialchars($usr['id_sekolah'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="fw-semibold text-dark col-nama-sekolah"><?= htmlspecialchars($usr['nama_sekolah'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-muted small">
                                    <?= date('d M Y, H:i', strtotime($usr['created_at'])); ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <!-- Tombol Edit -->
                                        <button class="btn btn-sm btn-light-warning text-warning border-0 btn-edit-user" 
                                                title="Edit / Ganti Password"
                                                data-id="<?= $usr['id']; ?>"
                                                data-username="<?= htmlspecialchars($usr['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-role="<?= $usr['role']; ?>"
                                                data-idsekolah="<?= htmlspecialchars($usr['id_sekolah'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-namasekolah="<?= htmlspecialchars($usr['nama_sekolah'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditUser">
                                            <i class="bi bi-pencil-square fs-6"></i>
                                        </button>
                                        
                                        <!-- Tombol Hapus -->
                                        <button class="btn btn-sm btn-light-danger text-danger border-0 btn-delete-user" 
                                                title="Hapus User"
                                                data-id="<?= $usr['id']; ?>"
                                                data-username="<?= htmlspecialchars($usr['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalHapusUser">
                                            <i class="bi bi-trash fs-6"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-people fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                <span>Belum ada data user tersimpan.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL EDIT USER & GANTI PASSWORD
========================================== -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-pencil-square text-warning me-2"></i>Edit User & Ganti Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="kelola_user.php" class="user-form-submit">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control fw-semibold" style="border-radius: 10px;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small">Password Baru</label>
                        <input type="password" name="password" id="edit_password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password" style="border-radius: 10px;">
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">*Kosongkan kolom ini jika tidak ada perubahan password.</small>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">Role / Hak Akses</label>
                            <select name="role" id="edit_role" class="form-select fw-semibold" style="border-radius: 10px;" required>
                                <option value="user">User (Sekolah)</option>
                                <option value="admin">Admin (Dinas Pusat)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-secondary small">ID Sekolah</label>
                            <input type="text" name="id_sekolah" id="edit_idsekolah" class="form-control fw-semibold" style="border-radius: 10px;" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold text-secondary small">Nama Sekolah / Instansi</label>
                        <input type="text" name="nama_sekolah" id="edit_namasekolah" class="form-control fw-semibold" style="border-radius: 10px;" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light px-4 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius: 10px;">Batal</button>
                    <button type="submit" class="btn btn-warning text-white px-4 py-2 fw-semibold" style="border-radius: 10px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL HAPUS USER
========================================== -->
<div class="modal fade" id="modalHapusUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <form method="POST" action="kelola_user.php" class="user-form-submit">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">

                <div class="modal-body p-4 text-center">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 55px;"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Konfirmasi Hapus User</h5>
                    <p class="text-secondary mb-4 small">Apakah Anda yakin ingin menghapus user <strong id="delete_username_text" class="text-dark"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                    
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light px-4 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius: 10px;">Batal</button>
                        <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold" style="border-radius: 10px;">Ya, Hapus</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     JAVASCRIPT INTERAKSI MODAL, AJAX & SEARCH REALTIME
========================================== -->
<script>
(function() {
    // 1. FILTER PENCARIAN REALTIME (NAMA SEKOLAH, USERNAME, ID SEKOLAH)
    const searchInput = document.getElementById('searchSekolah');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#userTableBody tr.user-row');
            let matchCount = 0;

            rows.forEach(row => {
                const namaSekolah = row.querySelector('.col-nama-sekolah')?.textContent.toLowerCase() || '';
                const username    = row.querySelector('.col-username')?.textContent.toLowerCase() || '';
                const idSekolah   = row.querySelector('.col-id-sekolah')?.textContent.toLowerCase() || '';

                if (namaSekolah.includes(query) || username.includes(query) || idSekolah.includes(query)) {
                    row.style.display = '';
                    matchCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Tampilkan pesan jika data tidak ditemukan
            let noMatchRow = document.getElementById('noMatchRow');
            if (matchCount === 0 && rows.length > 0) {
                if (!noMatchRow) {
                    noMatchRow = document.createElement('tr');
                    noMatchRow.id = 'noMatchRow';
                    noMatchRow.innerHTML = '<td colspan="7" class="text-center py-4 text-muted"><i class="bi bi-search me-1"></i> Data sekolah atau user tidak ditemukan.</td>';
                    document.getElementById('userTableBody').appendChild(noMatchRow);
                } else {
                    noMatchRow.style.display = '';
                }
            } else if (noMatchRow) {
                noMatchRow.style.display = 'none';
            }
        });
    }

    // 2. POPULATE DATA KE MODAL EDIT USER
    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_username').value = this.dataset.username;
            document.getElementById('edit_role').value = this.dataset.role;
            document.getElementById('edit_idsekolah').value = this.dataset.idsekolah;
            document.getElementById('edit_namasekolah').value = this.dataset.namasekolah;
            document.getElementById('edit_password').value = ''; 
        });
    });

    // 3. POPULATE DATA KE MODAL HAPUS USER
    document.querySelectorAll('.btn-delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_id').value = this.dataset.id;
            document.getElementById('delete_username_text').innerText = this.dataset.username;
        });
    });

    // 4. PENANGANAN SUBMIT FORM VIA AJAX
    document.querySelectorAll('.user-form-submit').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Tutup Modal yang aktif
            const modalElement = this.closest('.modal');
            if (modalElement) {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) modalInstance.hide();
            }

            const formData = new FormData(this);
            
            try {
                const loader = document.getElementById('loader');
                if (loader) loader.style.display = 'block';

                const response = await fetch('kelola_user.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Network error');

                const html = await response.text();
                
                // Render kembali konten kelola_user ke ajax-container
                const ajaxContainer = document.getElementById('ajax-container');
                if (ajaxContainer) {
                    ajaxContainer.innerHTML = html;
                }

            } catch (err) {
                alert('Terjadi kesalahan saat menyimpan data.');
            } finally {
                const loader = document.getElementById('loader');
                if (loader) loader.style.display = 'none';
            }
        });
    });
})();
</script>