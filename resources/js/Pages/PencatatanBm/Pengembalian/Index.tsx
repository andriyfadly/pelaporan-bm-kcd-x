import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import Modal from '@/Components/Modal';
import { bukaPdfDokumen } from '@/Utils/dokumen';
import { tanggalHariIni, formatTanggalWaktu } from '@/Utils/format';
import { FileText, Undo2 } from 'lucide-react';

interface UnitRegister {
    id: string;
    nomor_register: string;
}

interface Baris {
    unit_ids: string[];
    unit_registers: UnitRegister[];
    kode_barang: string;
    nama_barang: string | null;
    merk_tipe: string | null;
    tahun_perolehan: number;
    nomor_register: string;
    volume: number;
    satuan: string | null;
    harga_satuan: number;
    ruang: string | null;
    pegawai: string | null;
    pegawai_id: string | null;
    nomor_bast_terakhir: string | null;
}

interface Riwayat {
    id: string;
    tanggal: string | null;
    waktu: string | null;
    kode_barang: string | null;
    nomor_register: string | null;
    nama_barang: string | null;
    merk_tipe: string | null;
    tahun_perolehan: number | null;
    asal_pegawai: string | null;
    asal_ruang: string | null;
    volume: number;
    kondisi_akhir: string | null;
    dokumen_id: string | null;
    nomor: string | null;
}

interface Props {
    baris: Baris[];
    riwayatRingkas: Riwayat[];
    riwayat: Riwayat[];
    kondisiList: Record<string, string>;
    nomorBerikutnya: string | null;
    canProsesPengembalian: boolean;
}

const KONDISI_KEMBALI: [string, string][] = [
    ['B', 'B (Baik)'],
    ['RR', 'RR (Rusak Ringan)'],
    ['RB', 'RB (Rusak Berat)'],
];

const BULAN_SINGKAT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const tanggalSingkat = (nilai: string | null): string => {
    if (!nilai) {
        return '-';
    }
    const [tahun, bulan, hari] = nilai.slice(0, 10).split('-');
    return `${Number(hari)} ${BULAN_SINGKAT[Number(bulan) - 1]} ${tahun}`;
};

/** Format acuan riwayat: `25 Nov 2026 - 09:15 WIB` (zona Asia/Jakarta). */
const waktuWib = (nilai: string | null): string => formatTanggalWaktu(nilai);

const nomorTerkecil = (baris: Baris): number => Math.min(...baris.unit_registers.map((u) => Number(u.nomor_register)));

const nomorTerbesar = (baris: Baris): number => Math.max(...baris.unit_registers.map((u) => Number(u.nomor_register)));

const rupiah = (nilai: number): string =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai);

export default function Index({ baris, riwayatRingkas, riwayat, kondisiList, nomorBerikutnya, canProsesPengembalian }: Props) {
    const [target, setTarget] = useState<Baris | null>(null);
    const [mode, setMode] = useState<'semua' | 'sebagian'>('semua');
    const [regDari, setRegDari] = useState('');
    const [regSampai, setRegSampai] = useState('');
    const [kondisi, setKondisi] = useState('B');
    const [tanggal, setTanggal] = useState(() => tanggalHariIni());
    const [keterangan, setKeterangan] = useState('');
    const [galat, setGalat] = useState<string | null>(null);
    const [prosesModal, setProsesModal] = useState(false);
    const [cari, setCari] = useState('');
    const [riwayatTerbuka, setRiwayatTerbuka] = useState(false);
    const [riwayatPenuh, setRiwayatPenuh] = useState(false);
    const [cariRiwayat, setCariRiwayat] = useState('');

    const daftar = baris.filter((unit) => {
        const kunci = `${unit.nama_barang ?? ''} ${unit.merk_tipe ?? ''} ${unit.pegawai ?? ''} ${unit.ruang ?? ''} ${unit.nomor_register}`.toLowerCase();
        return kunci.includes(cari.toLowerCase());
    });

    const riwayatTampil = (riwayatPenuh ? riwayat : riwayatRingkas).filter((item) => {
        const kunci = `${item.nama_barang ?? ''} ${item.merk_tipe ?? ''} ${item.nomor ?? ''} ${item.asal_pegawai ?? ''}`.toLowerCase();
        return kunci.includes(cariRiwayat.toLowerCase());
    });

    const bukaRiwayat = (penuh: boolean) => {
        setRiwayatTerbuka(true);
        setRiwayatPenuh(penuh);
        setCariRiwayat('');
    };

    const tutupRiwayat = () => {
        setRiwayatTerbuka(false);
        setRiwayatPenuh(false);
        setCariRiwayat('');
    };

    const bukaModal = (unit: Baris) => {
        setTarget(unit);
        setMode('semua');
        setRegDari(String(nomorTerkecil(unit)));
        setRegSampai(String(nomorTerbesar(unit)));
        setKondisi('B');
        setTanggal(tanggalHariIni());
        setKeterangan('');
        setGalat(null);
    };

    /** Unit yang ikut dikembalikan: seluruh baris atau yang nomor registernya di rentang. */
    const unitTerpilih = (): string[] | null => {
        if (!target) {
            return null;
        }

        if (mode === 'semua') {
            return target.unit_ids;
        }

        const dari = Number(regDari);
        const sampai = Number(regSampai);
        const batasBawah = nomorTerkecil(target);
        const batasAtas = nomorTerbesar(target);

        if (!Number.isInteger(dari) || !Number.isInteger(sampai) || regDari.trim() === '' || regSampai.trim() === '') {
            setGalat('Nomor registrasi awal dan akhir harus diisi dengan angka yang valid!');
            return null;
        }

        if (dari > sampai) {
            setGalat(`No. Reg awal (${dari}) tidak boleh lebih besar dari No. Reg akhir (${sampai})!`);
            return null;
        }

        if (dari < batasBawah || sampai > batasAtas) {
            setGalat(`Rentang (${dari}-${sampai}) berada di luar rentang paket aktif (${target.nomor_register})!`);
            return null;
        }

        return target.unit_registers
            .filter((u) => Number(u.nomor_register) >= dari && Number(u.nomor_register) <= sampai)
            .map((u) => u.id);
    };

    const proses = () => {
        const unitIds = unitTerpilih();

        if (!unitIds || unitIds.length === 0) {
            return;
        }

        setGalat(null);
        setProsesModal(true);
        router.post(
            '/pencatatan-bm/pengembalian',
            { unit_ids: unitIds, kondisi, tanggal, keterangan: keterangan || null },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    bukaPdfDokumen(page);
                    setTarget(null);
                    setKondisi('B');
                    setTanggal(tanggalHariIni());
                    setKeterangan('');
                },
                onError: (errors) => setGalat(Object.values(errors)[0] ?? 'Gagal memproses pengembalian.'),
                onFinish: () => setProsesModal(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Pengembalian Barang" />

            <div className="space-y-5">
                <header className="flex flex-wrap items-start gap-3">
                    <div className="mr-auto">
                        <h1 className="text-xl font-bold text-slate-800">Pengembalian Barang</h1>
                        <p className="text-sm text-slate-500">Daftar barang yang sedang disalurkan beserta riwayat pengembalian.</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => bukaRiwayat(false)}
                        className="inline-flex min-h-10 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        <FileText className="h-4 w-4" /> History Pengembalian
                    </button>
                </header>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 p-4">
                        <h2 className="mr-auto font-bold text-slate-800">Barang Sedang Disalurkan</h2>
                        <input
                            value={cari}
                            onChange={(e) => setCari(e.target.value)}
                            placeholder="Cari barang, merk, pegawai, atau ruang..."
                            className="min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm sm:w-80"
                        />
                    </div>

                    {daftar.length === 0 ? (
                        <EmptyState
                            title={baris.length === 0 ? 'Tidak ada data barang yang sedang disalurkan.' : 'Data tidak ditemukan berdasarkan kata kunci tersebut.'}
                            description="Barang muncul di sini setelah disalurkan lewat Buku Penyaluran dan BAST-nya terbit."
                        />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {daftar.map((unit, index) => (
                                    <li key={`${unit.kode_barang}-${unit.nomor_register}-${unit.pegawai_id ?? 'tanpa'}`} className="p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-semibold text-slate-800">{unit.nama_barang ?? '-'}</p>
                                                <p className="font-mono text-xs text-slate-500">{unit.kode_barang} · Reg: {unit.nomor_register}</p>
                                            </div>
                                            <span className="text-xs text-slate-500">#{index + 1}</span>
                                        </div>
                                        <dl className="mt-2 grid grid-cols-2 gap-1 text-xs text-slate-600">
                                            <dt className="text-slate-500">Merk &amp; Tipe</dt>
                                            <dd>{unit.merk_tipe ?? '-'}</dd>
                                            <dt className="text-slate-500">Thn Pengadaan</dt>
                                            <dd>{unit.tahun_perolehan}</dd>
                                            <dt className="text-slate-500">Penerima</dt>
                                            <dd>{unit.pegawai ?? '-'}</dd>
                                            <dt className="text-slate-500">Lokasi</dt>
                                            <dd>{unit.ruang ?? '-'}</dd>
                                            <dt className="text-slate-500">Vol Salur</dt>
                                            <dd>{unit.volume} {unit.satuan ?? 'Unit'}</dd>
                                            <dt className="text-slate-500">Harga Satuan</dt>
                                            <dd>{rupiah(unit.harga_satuan)}</dd>
                                            <dt className="text-slate-500">BAST</dt>
                                            <dd>{unit.nomor_bast_terakhir ?? '-'}</dd>
                                        </dl>
                                        {canProsesPengembalian && (
                                            <button
                                                type="button"
                                                onClick={() => bukaModal(unit)}
                                                className="mt-3 inline-flex min-h-10 w-full items-center justify-center gap-1 rounded-lg bg-blue-600 px-3 text-xs font-bold text-white"
                                            >
                                                <Undo2 className="h-3.5 w-3.5" /> Kembalikan
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full min-w-[1000px] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                            <th className="px-4 py-3">No</th>
                                            <th className="px-4 py-3">Kode / No. Reg</th>
                                            <th className="px-4 py-3">Nama Barang</th>
                                            <th className="px-4 py-3">Merk &amp; Tipe</th>
                                            <th className="px-4 py-3">Thn Pengadaan</th>
                                            <th className="px-4 py-3">Penerima &amp; Lokasi</th>
                                            <th className="px-4 py-3">Vol Salur</th>
                                            <th className="px-4 py-3 text-right">Harga Satuan</th>
                                            <th className="px-4 py-3 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {daftar.map((unit, index) => (
                                            <tr key={`${unit.kode_barang}-${unit.nomor_register}-${unit.pegawai_id ?? 'tanpa'}`} className="border-b border-slate-50 last:border-0">
                                                <td className="px-4 py-3 text-slate-500">{index + 1}</td>
                                                <td className="px-4 py-3">
                                                    <p className="font-mono text-xs font-bold text-slate-700">{unit.kode_barang}</p>
                                                    <p className="text-xs text-slate-500">Reg: {unit.nomor_register}</p>
                                                </td>
                                                <td className="px-4 py-3 font-semibold text-slate-700">{unit.nama_barang ?? '-'}</td>
                                                <td className="px-4 py-3 text-slate-600">{unit.merk_tipe ?? '-'}</td>
                                                <td className="px-4 py-3 text-slate-600">{unit.tahun_perolehan}</td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    <p>{unit.pegawai ?? '-'}</p>
                                                    <p className="text-xs text-slate-500">{unit.ruang ?? '-'}</p>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{unit.volume} {unit.satuan ?? 'Unit'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-slate-600">{rupiah(unit.harga_satuan)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    {canProsesPengembalian ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => bukaModal(unit)}
                                                            className="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white"
                                                        >
                                                            <Undo2 className="h-3.5 w-3.5" /> Kembalikan
                                                        </button>
                                                    ) : (
                                                        <span className="text-xs text-slate-500">-</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </section>
            </div>

            <Modal
                isOpen={riwayatTerbuka}
                onClose={tutupRiwayat}
                title={riwayatPenuh ? 'Riwayat Lengkap Pengembalian Barang' : 'History Pengembalian (Terbaru)'}
                maxWidth={riwayatPenuh ? '3xl' : 'lg'}
            >
                <div className="space-y-4">
                    {riwayatPenuh && (
                        <input
                            value={cariRiwayat}
                            onChange={(e) => setCariRiwayat(e.target.value)}
                            placeholder="Cari nomor surat, barang, atau penerima..."
                            className="min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />
                    )}

                    {riwayatTampil.length === 0 ? (
                        <EmptyState
                            title="Tidak ditemukan adanya riwayat data pengembalian barang BMD."
                            description="Riwayat tercatat otomatis saat pengembalian diproses."
                        />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {riwayatTampil.map((item) => (
                                    <li key={item.id} className="py-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="font-mono text-xs font-bold text-blue-700">{item.nomor ?? item.kode_barang ?? '-'}</span>
                                            <span className="text-xs text-slate-500">{riwayatPenuh ? waktuWib(item.waktu) : tanggalSingkat(item.tanggal)}</span>
                                        </div>
                                        <p className="mt-1 font-semibold text-slate-800">
                                            {item.nama_barang ?? '-'} <span className="font-normal text-slate-500">(Reg: {item.nomor_register ?? '-'})</span>
                                        </p>
                                        <p className="text-xs text-slate-500">Thn Pengadaan: {item.tahun_perolehan ?? '-'}</p>
                                        <p className="text-xs text-slate-500">Penerima: {item.asal_pegawai ?? '-'}</p>
                                        <p className="text-xs text-slate-500">Lokasi: {item.asal_ruang ?? '-'}</p>
                                        {riwayatPenuh && item.dokumen_id && (
                                            <a
                                                href={`/pencatatan-bm/dokumen/${item.dokumen_id}/pdf`}
                                                className="mt-2 inline-flex min-h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-bold text-slate-700"
                                            >
                                                <FileText className="h-3.5 w-3.5" /> Cetak BA
                                            </a>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            <div className="hidden md:block overflow-x-auto">
                                <table className="w-full min-w-[720px] text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                                            <th className="px-3 py-2">Nomor Surat</th>
                                            <th className="px-3 py-2">{riwayatPenuh ? 'Tanggal Kembali' : 'Tanggal'}</th>
                                            <th className="px-3 py-2">Kode / No. Reg</th>
                                            <th className="px-3 py-2">Nama Barang</th>
                                            <th className="px-3 py-2">Thn Pengadaan</th>
                                            <th className="px-3 py-2">Eks Penerima</th>
                                            <th className="px-3 py-2">Volume</th>
                                            {riwayatPenuh && <th className="px-3 py-2 text-right">Aksi</th>}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {riwayatTampil.map((item) => (
                                            <tr key={item.id} className="border-b border-slate-50 last:border-0">
                                                <td className="px-3 py-2 font-mono text-xs text-slate-700">{item.nomor ?? '-'}</td>
                                                <td className="whitespace-nowrap px-3 py-2 text-slate-600">
                                                    {riwayatPenuh ? waktuWib(item.waktu) : tanggalSingkat(item.tanggal)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <p className="font-mono text-xs text-slate-700">{item.kode_barang ?? '-'}</p>
                                                    <p className="text-xs text-slate-500">Reg: {item.nomor_register ?? '-'}</p>
                                                </td>
                                                <td className="px-3 py-2 text-slate-700">{item.nama_barang ?? '-'}</td>
                                                <td className="px-3 py-2 text-slate-600">{item.tahun_perolehan ?? '-'}</td>
                                                <td className="px-3 py-2 text-slate-600">{item.asal_pegawai ?? '-'}</td>
                                                <td className="px-3 py-2 text-slate-600">{item.volume}</td>
                                                {riwayatPenuh && (
                                                    <td className="px-3 py-2 text-right">
                                                        {item.dokumen_id ? (
                                                            <a
                                                                href={`/pencatatan-bm/dokumen/${item.dokumen_id}/pdf`}
                                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700"
                                                            >
                                                                <FileText className="h-3.5 w-3.5" /> Cetak BA
                                                            </a>
                                                        ) : (
                                                            <span className="text-xs text-slate-500">-</span>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {!riwayatPenuh && (
                                <button
                                    type="button"
                                    onClick={() => setRiwayatPenuh(true)}
                                    className="min-h-10 w-full rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Lihat Riwayat Lengkap
                                </button>
                            )}
                        </>
                    )}
                </div>
            </Modal>

            {target && (
                <Modal
                    isOpen
                    onClose={() => { if (!prosesModal) { setTarget(null); } }}
                    title="Generate BA Pengembalian"
                >
                    <div className="space-y-3">
                        <p className="text-sm text-slate-500">
                            Sistem akan memproses berkas PDF serta memperbarui status penyaluran aktif untuk barang:
                            <strong className="text-slate-700"> {target.nama_barang ?? '-'}</strong>
                            <span className="block text-xs text-slate-500">
                                Penerima {target.pegawai ?? '-'} · {target.ruang ?? '-'}
                            </span>
                        </p>

                        <div className="mt-4 space-y-3">
                            <label className="block text-xs font-semibold text-slate-600">
                                Nomor Surat BA Pengembalian (Otomatis)
                                <input
                                    readOnly
                                    value={nomorBerikutnya ?? '.......'}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-sm text-slate-500"
                                />
                            </label>

                            <fieldset className="block text-xs font-semibold text-slate-600">
                                <legend>Jumlah Unit yang Dikembalikan</legend>
                                <div className="mt-1 space-y-1.5">
                                    <label className="flex items-center gap-2 text-sm font-normal text-slate-700">
                                        <input
                                            type="radio"
                                            name="mode"
                                            checked={mode === 'semua'}
                                            onChange={() => setMode('semua')}
                                        />
                                        Semua unit (rentang {target.nomor_register})
                                    </label>
                                    <label className="flex items-center gap-2 text-sm font-normal text-slate-700">
                                        <input
                                            type="radio"
                                            name="mode"
                                            checked={mode === 'sebagian'}
                                            onChange={() => setMode('sebagian')}
                                        />
                                        Sebagian
                                    </label>
                                </div>
                            </fieldset>

                            {mode === 'sebagian' && (
                                <div className="grid grid-cols-2 gap-3">
                                    <label className="block text-xs font-semibold text-slate-600">
                                        No. Reg awal
                                        <input
                                            value={regDari}
                                            onChange={(e) => setRegDari(e.target.value)}
                                            inputMode="numeric"
                                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <label className="block text-xs font-semibold text-slate-600">
                                        No. Reg akhir
                                        <input
                                            value={regSampai}
                                            onChange={(e) => setRegSampai(e.target.value)}
                                            inputMode="numeric"
                                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </label>
                                </div>
                            )}

                            <label className="block text-xs font-semibold text-slate-600">
                                Kondisi Barang Saat Dikembalikan:
                                <select
                                    value={kondisi}
                                    onChange={(e) => setKondisi(e.target.value)}
                                    className="mt-1 min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                                >
                                    {KONDISI_KEMBALI.map(([kode, label]) => (
                                        <option key={kode} value={kode}>{label}</option>
                                    ))}
                                </select>
                                <span className="mt-1 block text-xs text-slate-500">
                                    Kondisi di luar pilihan di atas: {Object.values(kondisiList).join(', ')}
                                </span>
                            </label>

                            <label className="block text-xs font-semibold text-slate-600">
                                Tanggal Pengembalian
                                <input
                                    type="date"
                                    value={tanggal}
                                    onChange={(e) => setTanggal(e.target.value)}
                                    className="mt-1 min-h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                                />
                            </label>

                            <label className="block text-xs font-semibold text-slate-600">
                                Keterangan Tambahan:
                                <textarea
                                    value={keterangan}
                                    onChange={(e) => setKeterangan(e.target.value)}
                                    rows={2}
                                    placeholder="Tuliskan keterangan (misal: layar pecah, kabel hilang)..."
                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                />
                            </label>

                            {galat && (
                                <p className="rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">
                                    {galat}
                                </p>
                            )}
                        </div>

                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setTarget(null)}
                                disabled={prosesModal}
                                className="min-h-10 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-600 disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={proses}
                                disabled={prosesModal}
                                className="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-blue-600 px-4 text-sm font-bold text-white disabled:opacity-60"
                            >
                                <FileText className="h-4 w-4" /> {prosesModal ? 'Memproses...' : 'Cetak & Proses'}
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </AppLayout>
    );
}
