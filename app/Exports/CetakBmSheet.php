<?php

namespace App\Exports;

use App\Models\Master\KodeBarang;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CetakBmSheet implements FromCollection, WithEvents, WithTitle
{
    /**
     * @param  Collection<int, Realisasi>  $records
     */
    public function __construct(
        private Collection $records,
        private string $namaSekolah,
        private int $filterTahun,
    ) {}

    public function title(): string
    {
        return 'Laporan Belanja Modal';
    }

    public function collection(): Collection
    {
        $rows = collect([
            ['DAFTAR PENGADAAN BARANG DARI BELANJA MODAL'],
            ['SMAN/SMKN/SLBN'],
            ['DARI TANGGAL 1 JANUARI S.D 31 DESEMBER '.$this->filterTahun],
            [],
            ['*CATATAN HURUF KOLOM:', '', '', ': Wajib Diisi secara Manual'],
            ['', '', '', ': Terisi Otomatis'],
            [],
            $this->headerBaris1(),
            $this->headerBaris2(),
        ]);

        $batasMaster = KodeBarang::count() + 1;
        if ($batasMaster < 2) {
            $batasMaster = 2;
        }

        $no = 1;
        $maxLen = [];
        foreach ($this->records as $row) {
            $tg = $bl = $thn = '';
            if (! empty($row->ba_tgl) && $row->ba_tgl !== '0000-00-00') {
                $time = strtotime($row->ba_tgl);
                $tg = (int) date('d', $time);
                $bl = (int) date('m', $time);
                $thn = date('Y', $time);
            }
            // ponytail: baris data mulai Excel row 10 (9 baris judul+header di atas)
            $excelRow = $rows->count() + 1;
            $valB = $this->safeCell($row->no_sp2d);
            $valC = $this->safeCell($row->sumber_perolehan);
            $valD = $this->safeCell($row->kodering_belanja);
            $valE = $this->safeCell($row->no_spk);
            $valF = $this->safeCell($row->ba_no);
            $valJ = $this->safeCell(trim((string) $row->kode_barang));
            $valL = $this->safeCell($row->merk_tipe);
            $valM = $this->safeCell($row->no_sertifikat);
            $valN = $this->safeCell($row->ukuran_bangunan);
            $valO = $this->safeCell($row->satuan);
            $namaSekolah = $row->sekolah?->nama_sekolah ?? 'Sekolah ID: '.$row->sekolah_id;
            $kotaKab = $this->formatKotaKab($row->sekolah?->kota_kab ?? '');
            $rows->push([
                $no++,
                $valB,
                $valC,
                $valD,
                $valE,
                $valF,
                $tg,
                $bl,
                $thn,
                $valJ,
                '=IFERROR(VLOOKUP(J'.$excelRow.',\'KODE BARANG\'!$A$2:$E$'.$batasMaster.',2,FALSE),"")',
                $valL,
                $valM,
                $valN,
                $valO,
                (int) ($row->volume ?? 0),
                (float) ($row->harga_satuan ?? 0),
                '=P'.$excelRow.'*Q'.$excelRow,
                '=IFERROR(VLOOKUP(J'.$excelRow.',\'KODE BARANG\'!$A$2:$E$'.$batasMaster.',3,FALSE),"")',
                '=IFERROR(VLOOKUP(J'.$excelRow.',\'KODE BARANG\'!$A$2:$E$'.$batasMaster.',4,FALSE),"")',
                '=IFERROR(VLOOKUP(J'.$excelRow.',\'KODE BARANG\'!$A$2:$E$'.$batasMaster.',5,FALSE),0)',
                '=R'.$excelRow,
                '=IF(AND($V'.$excelRow.'=0)," ",(($V'.$excelRow.'/$U'.$excelRow.')*(13-H'.$excelRow.')/12))',
                '=IF(Q'.$excelRow.'<=1000000,R'.$excelRow.',0)',
                $this->safeCell($namaSekolah),
                $this->safeCell($kotaKab),
            ]);
            foreach (['B' => $valB, 'C' => $valC, 'D' => $valD, 'E' => $valE, 'F' => $valF, 'J' => $valJ, 'L' => $valL, 'M' => $valM, 'N' => $valN, 'O' => $valO, 'Y' => $namaSekolah, 'Z' => $kotaKab] as $col => $val) {
                $maxLen[$col] = max($maxLen[$col] ?? 0, strlen((string) $val));
            }
        }

        $this->maxLen = $maxLen;

        return $rows;
    }

    /** @var array<string, int> */
    private array $maxLen = [];

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(10, $this->records->count() + 9);

                $sheet->setShowGridlines(true);

                foreach (['A1:Z1', 'A2:Z2', 'A3:Z3', 'A8:A9', 'B8:B9', 'C8:C9', 'D8:D9', 'E8:E9', 'F8:I8', 'J8:J9', 'K8:R8', 'S8:S9', 'T8:T9', 'U8:U9', 'V8:W8', 'X8:X9', 'Y8:Y9', 'Z8:Z9'] as $merge) {
                    $sheet->mergeCells($merge);
                }

                $sheet->getStyle('A1:A3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11, 'name' => 'Calibri'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A2')->getFont()->getColor()->setRGB('FF0000');
                $sheet->getStyle('C5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
                $sheet->getStyle('C6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('000000');

                $styleHeader = [
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
                    'font' => ['bold' => false, 'size' => 11, 'name' => 'Calibri'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ];
                $sheet->getStyle('A8:Y9')->applyFromArray($styleHeader);
                $styleHeaderZ = $styleHeader;
                unset($styleHeaderZ['borders']);
                $sheet->getStyle('Z8:Z9')->applyFromArray($styleHeaderZ);

                foreach (['C8', 'D8', 'E8', 'F8', 'F9', 'G9', 'H9', 'I9', 'J8', 'L9', 'M9', 'N9', 'O9', 'P9', 'Q9', 'Y8'] as $cell) {
                    $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FF0000');
                }

                if ($lastRow >= 10) {
                    $sheet->getStyle("A10:Y{$lastRow}")->getFont()->setSize(11)->setName('Calibri');
                    $sheet->getStyle("A10:Y{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
                    $sheet->getStyle("Z10:Z{$lastRow}")->getFont()->setSize(11)->setName('Calibri');
                    $sheet->getStyle("A10:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G10:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("O10:P{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("U10:U{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $acct = '_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)';
                    $sheet->getStyle("Q10:R{$lastRow}")->getNumberFormat()->setFormatCode($acct);
                    $sheet->getStyle("V10:X{$lastRow}")->getNumberFormat()->setFormatCode($acct);
                }

                $sheet->setCellValue('R7', "=SUM(R10:R{$lastRow})");
                $sheet->getStyle('R7')->getNumberFormat()->setFormatCode('_(* #,##0.00_);_(* (#,##0.00);_(* "-"??_);_(@_)');
                $sheet->getStyle('R7')->getFont()->setSize(11)->setName('Calibri')->setBold(true)->getColor()->setRGB('000000');
                $sheet->getStyle('R7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
                $sheet->getStyle('R7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

                $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
                foreach (range('B', 'Z') as $col) {
                    $maxLen = in_array($col, ['K', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X']) ? 14 : ($this->maxLen[$col] ?? 0);
                    $finalWidth = $maxLen + 4;
                    $minWidth = in_array($col, ['B', 'E', 'K', 'L', 'M', 'T', 'Y', 'Z']) ? 16 : 11;
                    if ($finalWidth < $minWidth) {
                        $finalWidth = $minWidth;
                    }
                    $sheet->getColumnDimension($col)->setAutoSize(false)->setWidth($finalWidth);
                }

                $sheet->freezePane('F10');
            },
        ];
    }

    private function safeCell(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $str = (string) $value;
        if (preg_match('/^[\=\+\-\@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }

    private function formatKotaKab(mixed $value): string
    {
        $val = trim(strtoupper((string) $value));
        if ($val === '') {
            return '';
        }
        if (str_starts_with($val, 'KOTA ') || str_starts_with($val, 'KABUPATEN ')) {
            return $val;
        }
        if (preg_match('/^KAB\.?\s+(.+)$/i', $val, $matches)) {
            return 'KABUPATEN '.trim($matches[1]);
        }

        return 'KABUPATEN '.$val;
    }

    /**
     * @return array<int, string>
     */
    private function headerBaris1(): array
    {
        return ['No', 'No. SP2D', 'Sumber Perolehan', 'Kodering Belanja', 'No. SPK / Faktur / Kuitansi', 'BA Penerimaan', '', '', '', 'Kode Barang', 'Rincian Barang', '', '', '', '', '', '', '', 'Kodering Aset', 'Nama Rekening Aset', 'Umur Ekonomis', 'Intrakomptabel', '', 'Ekstrakomptabel', 'Nama Sekolah', 'kab/kota'];
    }

    /**
     * @return array<int, string>
     */
    private function headerBaris2(): array
    {
        return ['', '', '', '', '', 'No', 'Tgl', 'Bln', 'Thn', '', 'Nama Barang', 'Merk/Tipe', 'No. Sertifikat/ No. Rangka/ No. Mesin', 'Ukuran (Gedung/ Bangunan)', 'Satuan', 'Volume', 'Harga Satuan', 'Nilai Perolehan', '', '', '', 'Nilai Perolehan', 'Beban Penyusutan', '', '', ''];
    }
}
