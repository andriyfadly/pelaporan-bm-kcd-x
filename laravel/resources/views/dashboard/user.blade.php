<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dashboard</title></head>
<body>
<main>
    <h1>{{ $school->nama_sekolah }}</h1>
    <table>
        <thead><tr><th>Bulan</th><th>Acuan</th><th>Realisasi</th><th>Aset</th><th>SPK</th><th>Status</th></tr></thead>
        <tbody>
        @foreach (range(1, 12) as $month)
            <tr>
                <td>{{ $month }}</td>
                <td>{{ number_format((float) ($targets[$month] ?? 0), 0, ',', '.') }}</td>
                <td>{{ number_format((float) ($realizations->get($month)?->total ?? 0), 0, ',', '.') }}</td>
                <td>{{ $realizations->get($month)?->assets ?? 0 }}</td>
                <td>{{ $realizations->get($month)?->documents ?? 0 }}</td>
                <td>{{ $statuses[$month] ?? 'Belum Dikirim' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</main>
</body>
</html>