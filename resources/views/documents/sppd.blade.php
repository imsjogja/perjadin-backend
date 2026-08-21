@extends('documents.layout')

@section('content')
    @php
        $employee = $sppd->employee_snapshot;
        $destination = $sppd->spt->destination;
        $signatory = $sppd->signatory;
        $position = data_get($employee, 'jabatan.nama', data_get($employee, 'jabatan'));
        $rankName = data_get($employee, 'golongan.pangkat', data_get($employee, 'pangkat.nama', data_get($employee, 'pangkat')));
        $group = data_get($employee, 'golongan.nama', data_get($employee, 'golongan'));
        $rank = $rankName && $group ? $rankName.' ('.$group.')' : ($rankName ?: $group);
        $eselon = data_get($employee, 'eselon.nama', data_get($employee, 'eselon'));
        $useSecretariat = str_contains(strtoupper((string) $signatory->signatory_role), 'SEKDA');
    @endphp
    @include('documents.partials.stationery', compact('stationery', 'useSecretariat'))

    <div style="margin-top: 38px">
        <div class="document-title">Surat Perintah Perjalanan Dinas</div>
        <div class="document-number">Nomor : {{ $sppd->document_number }}</div>
    </div>

    <table class="sppd-table">
        <tr><td style="width:5%">01.</td><td style="width:47.5%">Pejabat berwenang yang memberi perintah</td><td style="width:47.5%">{{ $sppd->order_giver }}</td></tr>
        <tr><td>02.</td><td>Nama/NIP Pegawai yang diperintahkan</td><td>{{ data_get($employee, 'nama') }} / {{ data_get($employee, 'nip') }}</td></tr>
        <tr>
            <td>03.</td>
            <td>
                <table class="inner-table"><tr><td style="width:15px">a.</td><td>Pangkat dan golongan ruang</td></tr><tr><td>b.</td><td>Jabatan / Instansi</td></tr><tr><td>c.</td><td>Tingkat menurut peraturan perjalanan dinas</td></tr></table>
            </td>
            <td>
                <table class="inner-table"><tr><td style="width:15px">a.</td><td>{{ $rank }}</td></tr><tr><td>b.</td><td>{{ $position }}</td></tr><tr><td>c.</td><td>{{ $sppd->travel_level }} {{ $eselon ? ' — Eselon: '.$eselon : '' }}</td></tr></table>
            </td>
        </tr>
        <tr><td>04.</td><td style="height:68px">Maksud Perjalanan Dinas</td><td class="text-justify">{{ $sppd->spt->dalam_rangka }}</td></tr>
        <tr><td>05.</td><td>Alat angkut yang dipergunakan</td><td>{{ $destination->transportation }}</td></tr>
        <tr>
            <td>06.</td>
            <td><table class="inner-table"><tr><td style="width:15px">a.</td><td>Tempat berangkat</td></tr><tr><td>b.</td><td>Tempat tujuan</td></tr></table></td>
            <td><table class="inner-table"><tr><td style="width:15px">a.</td><td>{{ $destination->departure_place }}</td></tr><tr><td>b.</td><td>{{ $destination->destination_place }}</td></tr></table></td>
        </tr>
        <tr>
            <td>07.</td>
            <td><table class="inner-table"><tr><td style="width:15px">a.</td><td>Lamanya perjalanan dinas</td></tr><tr><td>b.</td><td>Tanggal berangkat</td></tr><tr><td>c.</td><td>Tanggal kembali</td></tr></table></td>
            <td><table class="inner-table"><tr><td style="width:15px">a.</td><td>{{ $destination->duration_days }} hari</td></tr><tr><td>b.</td><td>{{ $sppd->departure_date->format('d/m/Y') }}</td></tr><tr><td>c.</td><td>{{ $sppd->return_date->format('d/m/Y') }}</td></tr></table></td>
        </tr>
        <tr><td>08.</td><td><span class="small">Pengikut: Nama/NIP</span></td><td><span class="small">Gol. Ruang / Keterangan</span></td></tr>
        <tr>
            <td>&nbsp;</td>
            <td style="min-height:52px">
                @forelse($sppd->followers as $index => $follower)
                    <div class="small">{{ $index + 1 }}. {{ data_get($follower->employee_snapshot, 'nama') }} / {{ data_get($follower->employee_snapshot, 'nip') }}</div>
                @empty
                    <div>&nbsp;</div>
                @endforelse
            </td>
            <td style="min-height:52px">
                @forelse($sppd->followers as $follower)
                    @php
                        $followerEmployee = $follower->employee_snapshot;
                        $followerRankName = data_get($followerEmployee, 'golongan.pangkat', data_get($followerEmployee, 'pangkat.nama', data_get($followerEmployee, 'pangkat')));
                        $followerGroup = data_get($followerEmployee, 'golongan.nama', data_get($followerEmployee, 'golongan'));
                        $followerRank = $followerRankName && $followerGroup ? $followerRankName.' ('.$followerGroup.')' : ($followerRankName ?: $followerGroup);
                    @endphp
                    <div class="small">{{ $followerRank }}</div>
                @empty
                    <div>&nbsp;</div>
                @endforelse
            </td>
        </tr>
        <tr>
            <td>09.</td>
            <td><table class="inner-table"><tr><td colspan="2">Pembebanan anggaran</td></tr><tr><td style="width:15px">a.</td><td>Instansi</td></tr><tr><td>b.</td><td>Mata anggaran</td></tr></table></td>
            <td><table class="inner-table"><tr><td colspan="2">Satuan kerja</td></tr><tr><td style="width:15px">a.</td><td>{{ $sppd->budget_agency }}</td></tr><tr><td>b.</td><td>{{ $sppd->budget_account }}</td></tr></table></td>
        </tr>
        <tr><td>10.</td><td>Keterangan</td><td>{{ $sppd->description }}</td></tr>
    </table>

    @include('documents.partials.signatory', [
        'signatory' => $signatory,
        'issuedPlace' => $sppd->issued_place,
        'issuedDate' => $sppd->issued_date,
        'documentNumber' => $sppd->document_number,
        'marginTop' => 6,
    ])

    <div class="footer">
        <div style="text-decoration: underline">Tembusan Kepada Yth.</div>
        @foreach($stationery['copies'] as $index => $copy)<div>{{ $index + 1 }}. {{ $copy }}</div>@endforeach
    </div>
@endsection
