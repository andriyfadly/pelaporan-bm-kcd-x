<?php

function assert_report_unlocked(mysqli $conn, string $idSekolah, int $bulan): void
{
    if ($idSekolah === '' || $bulan < 1 || $bulan > 12) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Identitas sekolah atau bulan tidak valid.']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT 1 FROM `laporan_realisasi` WHERE `id_sekolah` = ? AND `bulan` = ? AND `status` IN ('Menunggu Approval', 'Disetujui') LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'si', $idSekolah, $bulan);
    mysqli_stmt_execute($stmt);
    $locked = mysqli_stmt_get_result($stmt)->num_rows > 0;
    mysqli_stmt_close($stmt);

    if ($locked) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Laporan bulan ini sudah dikirim atau disetujui, data terkunci.']);
        exit;
    }
}
