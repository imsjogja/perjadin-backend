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
        @page { margin: 11mm 10mm 12mm; }
        * { box-sizing: border-box; }
        body { color: #000; font-family: "Source Sans Pro", DejaVu Sans, sans-serif; font-size: 12pt; line-height: 1.28; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .kop { margin-bottom: 30px; text-align: center; }
        .kop-logo { width: 68px; text-align: left; vertical-align: middle; }
        .kop-logo img { width: 60px; max-height: 70px; object-fit: contain; }
        .kop-government { font-family: "Source Sans Pro", DejaVu Sans, serif; font-size: 18pt; font-weight: 400; letter-spacing: .1rem; line-height: 1rem; margin: 0; text-transform: uppercase; transform: scale(1.2, 1); }
        .kop-agency { font-family: Times, serif; font-size: 18pt; font-weight: bold; letter-spacing: .2rem; line-height: 1rem; margin: 8px 0 0; text-transform: uppercase; transform: scale(1, 1.3); }
        .kop-address { font-family: "Source Sans Pro", DejaVu Sans, sans-serif; font-size: 11pt; line-height: 1.2rem; }
        .kop-line { border-bottom: 3px solid #000; }
        .document-title { font-size: 16pt; font-weight: 450; letter-spacing: .25rem; text-align: center; text-decoration: underline; text-transform: uppercase; }
        .document-command { font-size: 16pt; font-weight: bold; letter-spacing: .25rem; margin: 15px 0; }
        .document-number { color: #4d4d4d; font-size: 11pt; letter-spacing: 1px; text-align: center; text-transform: uppercase;}
        .text-center { text-align: center; }
        .text-justify { text-align: justify; }
        .va-top { vertical-align: top; }
        .signature-space { height: 43px; }
        .qr-document { font-size: 7pt; text-align: center; vertical-align: middle; }
        .qr-document img { display: block; height: 125px; margin: 2px auto 0; width: 125px; }
        .footer { font-size: 10pt; margin-top: 16px; }
        .sppd-table { border: 2px solid #000; margin-top: 10px; }
        .sppd-table td { border: 1px solid #000; padding: 3px; vertical-align: top; }
        .inner-table td { border: 0; padding: 0; }
        .visum-table { border: 1px solid #000; table-layout: fixed; }
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
        .visum-data-table td { border: 0 !important; line-height: 1.3; overflow-wrap: break-word; padding: 1px 0 !important; vertical-align: top; }
        .visum-number { width: 24px; }
        .visum-label { white-space: nowrap; width: 145px; }
        .visum-label-wrap { white-space: normal; }
        .visum-colon { width: 16px; }
        .visum-section-table { height: 128px; table-layout: fixed; }
        .visum-return-signature-table { height: 141px; table-layout: fixed; }
        .visum-section-table td,
        .visum-return-signature-table td { border: 0 !important; }
        .visum-section-content { height: 58px; padding: 7px 9px !important; vertical-align: top; }
        .visum-transit-signature { height: 70px; padding: 10px 30px 6px !important; text-align: center; vertical-align: bottom; }
        .visum-nip-label { font-size: inherit; margin-top: 3px; text-align: left; }
        .visum-row-return-content > td { border-bottom: 0 !important; }
        .visum-row-return-signatures > td { border-top: 0 !important; }
        .visum-return-content { line-height: 1.3; padding: 7px 9px 3px !important; vertical-align: top; }
        .visum-signatory-role { height: 28px; line-height: 1.25; padding: 3px 9px 0 !important; text-align: center; vertical-align: top; }
        .visum-signature-space { height: 58px; padding: 0 !important; }
        .visum-signatory-identity { height: 55px; line-height: 1.25; padding: 2px 9px 6px !important; text-align: center; vertical-align: bottom; }
        .small { font-size: inherit; }
        .muted-code { color: #4d4d4d; font-size: 9pt; letter-spacing: 1px; }
        .document-content { min-height: 333mm; }
        .document-preview { background-image: url("file://{{ resource_path('images/preview-watermark.png') }}"); background-position: 4mm 37mm; background-repeat: repeat; background-size: 102mm 70mm; }
    </style>
</head>
<body>
    <div @class(['document-content', 'document-preview' => ! empty($preview)])>
        @yield('content')
    </div>
</body>
</html>
