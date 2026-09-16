<?php

namespace App\Exports;

use App\Models\Master\KodeBarang;
use App\Models\PelaporanBm\Realisasi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RealisasiBmSheet implements Export, WithEvents, WithTitle
{
    /** @var array<int, array<int, mixed>> */
    private array $dataRows = [];

    /** @var array<string, int> */
    private array $maxLen = [];

    /**
     * @param  Collection<int, Realisasi>  $records
     */
    public function __construct(
        private Collection $records,
        private string $namaSekolah,
        private int $filterTahun,
    ) {
        $this->buildData();
    }

    public function title(): string
    {
        return 'Laporan Belanja Modal';
    }

    private function buildData(): void
    {
        $batasMaster = KodeBarang::count() + 1;
        if ($batasMaster < 2) {
            $batasMaster = 2;
        }

        $no = 1;
        foreach ($this->records as $row) {
            $tg = $bl = $thn = '';
            if (! empty($row->ba_tgl) && $row->ba_tgl !== '0000-00-00') {
                $time = strtotime($row->ba_tgl);
                $tg = (int) date('d', $time);
                $bl = (int) date('m', $time);
                $thn = date('Y', $time);
            }
            $excelRow = 9 + $no;
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
            $valY = $this->safeCell($row->sekolah?->nama_sekolah ?? $this->namaSekolah);
            $this->dataRows[] = [
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
                $valY,
            ];
            foreach (['B' => $valB, 'C' => $valC, 'D' => $valD, 'E' => $valE, 'F' => $valF, 'J' => $valJ, 'L' => $valL, 'M' => $valM, 'N' => $valN, 'O' => $valO, 'Y' => $valY] as $col => $val) {
                $this->maxLen[$col] = max($this->maxLen[$col] ?? 0, strlen((string) $val));
            }
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(10, count($this->dataRows) + 9);

                $sheet->setShowGridlines(true);

                $sheet->setCellValue('A1', 'DAFTAR PENGADAAN BARANG DARI BELANJA MODAL');
                $sheet->mergeCells('A1:Y1');
                $sheet->setCellValue('A2', strtoupper($this->safeCell($this->namaSekolah)));
                $sheet->mergeCells('A2:Y2');
                $sheet->setCellValue('A3', 'PERIODE TAHUN '.($this->filterTahun > 0 ? $this->filterTahun : date('Y')));
                $sheet->mergeCells('A3:Y3');

                $sheet->getStyle('A1:A3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11, 'name' => 'Calibri'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('A2')->getFont()->getColor()->setRGB('FF0000');

                $sheet->setCellValue('A5', '*CATATAN HURUF KOLOM:');
                $sheet->setCellValue('D5', ': Wajib Diisi secara Manual');
                $sheet->setCellValue('D6', ': Terisi Otomatis');
                $sheet->getStyle('C5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
                $sheet->getStyle('C6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('000000');

                $sheet->setCellValue('A8', 'No');
                $sheet->mergeCells('A8:A9');
                $sheet->setCellValue('B8', 'No. SP2D');
                $sheet->mergeCells('B8:B9');
                $sheet->setCellValue('C8', 'Sumber Perolehan');
                $sheet->mergeCells('C8:C9');
                $sheet->setCellValue('D8', 'Kodering Belanja');
                $sheet->mergeCells('D8:D9');
                $sheet->setCellValue('E8', 'No. SPK / Faktur / Kuitansi');
                $sheet->mergeCells('E8:E9');
                $sheet->setCellValue('F8', 'BA Penerimaan');
                $sheet->mergeCells('F8:I8');
                $sheet->setCellValue('J8', 'Kode Barang');
                $sheet->mergeCells('J8:J9');
                $sheet->setCellValue('K8', 'Rincian Barang');
                $sheet->mergeCells('K8:R8');
                $sheet->setCellValue('S8', 'Kodering Aset');
                $sheet->mergeCells('S8:S9');
                $sheet->setCellValue('T8', 'Nama Rekening Aset');
                $sheet->mergeCells('T8:T9');
                $sheet->setCellValue('U8', 'Umur Ekonomis');
                $sheet->mergeCells('U8:U9');
                $sheet->setCellValue('V8', 'Intrakomptabel');
                $sheet->mergeCells('V8:W8');
                $sheet->setCellValue('X8', 'Ekstrakomptabel');
                $sheet->mergeCells('X8:X9');
                $sheet->setCellValue('Y8', 'Nama Sekolah');
                $sheet->mergeCells('Y8:Y9');
                $sheet->setCellValue('F9', 'No');
                $sheet->setCellValue('G9', 'Tgl');
                $sheet->setCellValue('H9', 'Bln');
                $sheet->setCellValue('I9', 'Thn');
                $sheet->setCellValue('K9', 'Nama Barang');
                $sheet->setCellValue('L9', 'Merk/Tipe');
                $sheet->setCellValue('M9', 'No. Sertifikat/ No. Rangka/ No. Mesin');
                $sheet->setCellValue('N9', 'Ukuran (Gedung/ Bangunan)');
                $sheet->setCellValue('O9', 'Satuan');
                $sheet->setCellValue('P9', 'Volume');
                $sheet->setCellValue('Q9', 'Harga Satuan');
                $sheet->setCellValue('R9', 'Nilai Perolehan');
                $sheet->setCellValue('V9', 'Nilai Perolehan');
                $sheet->setCellValue('W9', 'Beban Penyusutan');

                $sheet->getStyle('A8:Y9')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDEBF7']],
                    'font' => ['bold' => false, 'size' => 11, 'name' => 'Calibri'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
                ]);
                foreach (['C8', 'D8', 'E8', 'F8', 'F9', 'G9', 'H9', 'I9', 'J8', 'L9', 'M9', 'N9', 'O9', 'P9', 'Q9', 'Y8'] as $cell) {
                    $sheet->getStyle($cell)->getFont()->getColor()->setRGB('FF0000');
                }

                if (! empty($this->dataRows)) {
                    $sheet->fromArray($this->dataRows, null, 'A10');
                }

                if ($lastRow >= 10) {
                    $sheet->getStyle("A10:Y{$lastRow}")->getFont()->setSize(11)->setName('Calibri');
                    $sheet->getStyle("A10:Y{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
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
                foreach (range('B', 'Y') as $col) {
                    $maxLen = in_array($col, ['K', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X']) ? 14 : ($this->maxLen[$col] ?? 0);
                    $finalWidth = $maxLen + 4;
                    $minWidth = in_array($col, ['B', 'E', 'K', 'L', 'M', 'T', 'Y']) ? 16 : 11;
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
}
