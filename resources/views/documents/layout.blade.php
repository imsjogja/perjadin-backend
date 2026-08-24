<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @if(! empty($stationery['font_path']))
            @font-face {
                font-family: "Source Sans Pro";
                font-style: normal;
                font-weight: normal;
                src: url("file://{{ $stationery['font_path'] }}") format("truetype");
            }
        @endif
        @if(! empty($stationery['font_light_path']))
            @font-face {
                font-family: "Source Sans Pro";
                font-style: normal;
                font-weight: 300;
                src: url("file://{{ $stationery['font_light_path'] }}") format("truetype");
            }
        @endif
        @if(! empty($stationery['font_bold_path']))
            @font-face {
                font-family: "Source Sans Pro";
                font-style: normal;
                font-weight: bold;
                src: url("file://{{ $stationery['font_bold_path'] }}") format("truetype");
            }
        @endif
        @if(! empty($stationery['source_sans_3_black_font_path']) && is_readable($stationery['source_sans_3_black_font_path']))
            @font-face {
                font-family: "Source Sans 3";
                font-style: normal;
                font-weight: 900;
                src: url("file://{{ $stationery['source_sans_3_black_font_path'] }}") format("truetype");
            }
        @endif
        @if(! empty($stationery['calibri_font_path']) && is_readable($stationery['calibri_font_path']))
            @font-face {
                font-family: "Calibri";
                font-style: normal;
                font-weight: normal;
                src: url("file://{{ $stationery['calibri_font_path'] }}") format("truetype");
            }
        @endif
        @if(! empty($stationery['calibri_bold_font_path']) && is_readable($stationery['calibri_bold_font_path']))
            @font-face {
                font-family: "Calibri";
                font-style: normal;
                font-weight: bold;
                src: url("file://{{ $stationery['calibri_bold_font_path'] }}") format("truetype");
            }
        @endif
        @if(! empty($stationery['arial_black_font_path']) && is_readable($stationery['arial_black_font_path']))
            @font-face {
                font-family: "Arial Black";
                font-style: normal;
                font-weight: 800;
                src: url("file://{{ $stationery['arial_black_font_path'] }}") format("truetype");
            }
        @endif
        @page { margin: 12.5mm 12.8mm 5mm 10mm; }
        @yield('page-style')
        * { box-sizing: border-box; }
        body { color: #000; font-family: Calibri, "Source Sans Pro", sans-serif; font-size: 11pt; line-height: 1; margin: 0; }
        table { border-collapse: collapse; font-family: Calibri, "Source Sans Pro", sans-serif; font-size: 11pt; width: 100%; }
        td, th { font-size: 11pt; }
        .kop { font-size: 11pt; text-align: center; }
        .kop-spt { margin-bottom: 57px; }
        .kop-sppd { margin-bottom: 16px; }
        .kop-logo { padding: 0; text-align: center; vertical-align: top; width: 150px; }
        .kop-logo img { display: block; height: 82px; margin: -7px auto 0; object-fit: contain; width: 58px; }
        .kop-content { padding: 0; position: relative; top: -3px; vertical-align: top; }
        .kop-with-logo .kop-content { left: -75px; }
        .kop-government { font-family: Times, "Times New Roman", serif; font-size: 19pt; font-weight: normal; letter-spacing: 0; line-height: .9; margin: 0 0 -4px; text-transform: uppercase; }
        .kop-agency { font-family: "Source Sans 3", "Source Sans Pro", sans-serif; font-size: 32px; font-weight: 900; letter-spacing: .02rem; line-height: .9; margin: 0; text-transform: uppercase; }
        @if(! empty($stationery['arial_black_font_path']) && is_readable($stationery['arial_black_font_path']))
            .kop-agency { font-family: "Arial Black", sans-serif; font-size: 32px; font-weight: 900; }
        @endif
        .kop-address { display: block; font-family: Calibri, "Source Sans Pro", sans-serif; font-size: 11pt; line-height: 1.104; margin-top: 0; }
        .kop-line { border-bottom: 3px double #000; height: 3px; }
        .document-heading { text-align: center; }
        .document-heading-spt { margin-top: 28px; }
        .document-heading-sppd { margin-top: 20px; }
        .document-title { font-family: Calibri, "Source Sans Pro", sans-serif; font-size: 18pt; font-weight: normal; letter-spacing: 0; line-height: 1; text-align: center; text-decoration: underline; text-transform: uppercase; }
        .document-command { font-family: Calibri, "Source Sans Pro", sans-serif; font-size: 18pt; font-weight: normal; letter-spacing: 0; line-height: 1; margin: 18px 0 20px; }
        .document-number { color: #4d4d4d; font-family: Calibri, "Source Sans Pro", sans-serif; font-size: 10pt; font-weight: bold; letter-spacing: .16rem; line-height: 1; text-align: center; text-transform: uppercase; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-justify { text-align: justify; }
        .va-top { vertical-align: top; }
        .signature-space { height: 48px; }
        .signatory-role,
        .signatory-identity { font-size: 11pt; line-height: 1; text-align: left; }
        .qr-document { color: #4d4d4d; font-size: 10pt; letter-spacing: .16rem; line-height: 1; text-align: center; vertical-align: middle; }
        .qr-document img { display: block; height: 125px; margin: 2px auto 0; width: 125px; }
        .footer { font-size: 11pt; line-height: 1; margin-top: 16px; }
        .footer div { font-size: 11pt; }
        .sppd-table { border: 1px solid #000; font-size: 11pt; line-height: 1; margin-top: 20px; }
        .sppd-table td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
        .sppd-row-standard > td { height: 36px; }
        .sppd-row-profile > td { height: 128px; }
        .sppd-row-purpose > td { height: 52px; }
        .sppd-row-transport > td { height: 31px; }
        .sppd-row-route > td { height: 55px; }
        .sppd-row-duration > td { height: 75px; }
        .sppd-row-followers-heading > td { height: 43px; }
        .sppd-row-followers > td,
        .sppd-row-description > td { height: 30px; }
        .sppd-row-budget > td { height: 80px; }
        .inner-table td { border: 0; padding: 0; }
        .visum-table { border: 1px solid #000; font-size: 11pt; line-height: 1; table-layout: fixed; }
        .visum-table > tbody > tr > td { border: 1px solid #000; padding: 0; vertical-align: top; }
        .visum-row-departure { height: 88px; }
        .visum-row-transit { height: 128px; }
        .visum-row-return-content { height: 64px; }
        .visum-row-return-signatures { height: 141px; }
        .visum-row-notes { height: 52px; }
        .visum-row-warning { height: 76px; }
        .visum-row-departure > td,
        .visum-row-notes > td,
        .visum-row-warning > td { padding: 7px 9px !important; }
        .visum-data-table { table-layout: auto; }
        .visum-data-table td { border: 0 !important; font-size: 11pt; line-height: 1; overflow-wrap: break-word; padding: 1px 0 !important; vertical-align: top; }
        .visum-number { width: 24px; }
        .visum-label { white-space: nowrap; width: 145px; }
        .visum-label-wrap { white-space: normal; }
        .visum-colon { width: 16px; }
        .visum-section-table { height: 128px; table-layout: fixed; }
        .visum-return-signature-table { height: 141px; table-layout: fixed; }
        .visum-section-table td,
        .visum-return-signature-table td { border: 0 !important; }
        .visum-transit-content-row { height: 65px; }
        .visum-transit-signature-row { height: 63px; }
        .visum-section-content { height: 65px; padding: 7px 9px !important; vertical-align: top; }
        .visum-transit-signature { height: 63px; padding: 0 30px 6px !important; vertical-align: bottom; }
        .visum-transit-signature-table { table-layout: fixed; }
        .visum-transit-signature-table td { border: 0 !important; padding: 0 !important; }
        .visum-transit-signature-spacer { height: 22px; }
        .visum-transit-signature-line { text-align: center; vertical-align: bottom; }
        .visum-transit-signature-nip { height: 23px; text-align: left; vertical-align: bottom; }
        .visum-row-return-content > td { border-bottom: 0 !important; }
        .visum-row-return-signatures > td { border-top: 0 !important; }
        .visum-return-content { line-height: 1; padding: 7px 9px 3px !important; vertical-align: top; }
        .visum-signatory-role { font-size: 11pt; height: 28px; line-height: 1; padding: 3px 9px 0 !important; text-align: center; vertical-align: top; }
        .visum-signature-space { height: 58px; padding: 0 !important; }
        .visum-signatory-identity { font-size: 11pt; height: 55px; line-height: 1; padding: 2px 9px 6px !important; text-align: center; vertical-align: bottom; }
        .small { font-size: 11pt; }
        .muted-code { color: #4d4d4d; font-size: 9pt; letter-spacing: 1px; }
        .sppd-closing-block { page-break-inside: avoid; }
        .sppd-closing-table { margin-top: -2px; }
        .document-preview { background-image: url("file://{{ resource_path('images/preview-watermark.png') }}"); background-position: 4mm 37mm; background-repeat: repeat; background-size: 102mm 70mm; }
    </style>
</head>
<body>
    <div @class(['document-content', 'document-preview' => ! empty($preview)])>
        @yield('content')
    </div>
</body>
</html>
