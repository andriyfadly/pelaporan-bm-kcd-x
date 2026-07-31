<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dashboard Admin</title></head>
<body>
<main>
    <h1>Dashboard Admin</h1>
    <p>Periode: {{ $month }}/{{ $year }}</p>
    <p>Total Sekolah: {{ $total }}</p>
    <p>Selesai: {{ $completed }}</p>
    <p>Belum: {{ $pending }}</p>
</main>
</body>
</html>