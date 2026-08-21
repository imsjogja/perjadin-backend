<?php

return [
    'number_formats' => [
        /*
         * Placeholder yang tersedia: {number} (5 digit), {year}, dan {type}.
         * Kosongkan nilai environment untuk memakai nilai default di bawah.
         */
        'spt' => env('PERJADIN_SPT_NUMBER_FORMAT'),
        'sppd' => env('PERJADIN_SPPD_NUMBER_FORMAT'),
        'defaults' => [
            'spt' => '823-{number}/BKD-{type}/{year}',
            'sppd' => '823-{number}/BKD-{type}/{year}',
        ],
    ],

    'stationery' => [
        'government' => env('PERJADIN_STATIONERY_GOVERNMENT', 'Provinsi Papua Barat'),
        'agency' => env('PERJADIN_STATIONERY_AGENCY', 'Badan Kepegawaian Daerah'),
        'secretariat' => env('PERJADIN_STATIONERY_SECRETARIAT', 'Sekretariat Daerah'),
        'address' => env(
            'PERJADIN_STATIONERY_ADDRESS',
            'Jl. Brigjend Marinir (Pur) Abraham O. Atururi Kompleks Perkantoran Arfai'
        ),
        'city' => env('PERJADIN_STATIONERY_CITY', 'Manokwari'),
        /*
         * Logo resmi bawaan dapat diganti dengan path absolut melalui
         * PERJADIN_STATIONERY_LOGO_PATH bila instansi menerbitkan pembaruan.
        */
        'logo_path' => env('PERJADIN_STATIONERY_LOGO_PATH') ?: resource_path('images/logo-pabar.png'),
        'font_path' => resource_path('fonts/SourceSansPro-Regular.ttf'),
        'font_light_path' => resource_path('fonts/SourceSansPro-Light.ttf'),
        'font_bold_path' => resource_path('fonts/SourceSansPro-Bold.ttf'),
        'copies' => [
            'Kepala Badan Pengelola Keuangan dan Aset Daerah Provinsi Papua Barat;',
            'Arsip',
        ],
    ],

    'admin' => [
        'name' => env('PERJADIN_ADMIN_NAME', 'Administrator'),
        'email' => env('PERJADIN_ADMIN_EMAIL', 'admin@perjadin.local'),
        'password' => env('PERJADIN_ADMIN_PASSWORD', 'password'),
    ],
];
