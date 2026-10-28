<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Libur Nasional Tetap
    |--------------------------------------------------------------------------
    |
    | Tanggal yang sama setiap tahun. Dipakai sebagai jaring terakhir ketika
    | API tanggal merah tidak dapat dihubungi dan belum ada cache sama sekali.
    | Libur yang bergerak (Idul Fitri, Nyepi, Waisak, Isra Mikraj, dst.) hanya
    | datang dari API — kalau API mati dan belum pernah sukses, tanggal itu
    | akan dianggap hari kerja.
    |
    | Kunci berformat `bulan-hari`.
    |
    */

    'nasional_tetap' => [
        '01-01' => 'Tahun Baru Masehi',
        '05-01' => 'Hari Buruh Internasional',
        '06-01' => 'Hari Lahir Pancasila',
        '08-17' => 'Hari Kemerdekaan Republik Indonesia',
        '12-25' => 'Hari Raya Natal',
    ],

];
