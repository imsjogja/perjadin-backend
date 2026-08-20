@extends('documents.layout')

@section('content')
    @php
        $signatory = $spt->signatory;
        $useSecretariat = str_contains(strtoupper((string) $signatory->signatory_role), 'SEKDA');
    @endphp
    @include('documents.partials.stationery', compact('stationery', 'useSecretariat'))

    <div style="margin-top: 38px">
        <div class="document-title">Surat Perintah Tugas</div>
        <div class="document-number">Nomor : {{ $spt->document_number }}</div>
    </div>

    <table style="margin-top: 15px">
        <tr>
            <td style="width: 70px">Dasar</td><td style="width: 10px">:</td>
            <td style="width: 20px" class="va-top">A.</td><td class="text-justify">{{ $spt->dasar }}</td>
        </tr>
        @if($spt->disposisi)
            <tr><td></td><td></td><td class="va-top">B.</td><td class="text-justify">{{ $spt->disposisi }}</td></tr>
        @endif
    </table>

    <p class="text-center" style="font-size: 12pt; font-weight: bold; margin: 15px 0">MEMERINTAHKAN</p>

    <table>
        @forelse($spt->assignees as $index => $assignee)
            @php
                $employee = $assignee->employee_snapshot;
                $position = data_get($employee, 'jabatan.nama', data_get($employee, 'jabatan'));
                $rankName = data_get($employee, 'golongan.pangkat', data_get($employee, 'pangkat.nama', data_get($employee, 'pangkat')));
                $group = data_get($employee, 'golongan.nama', data_get($employee, 'golongan'));
                $rank = $rankName && $group ? $rankName.' ('.$group.')' : ($rankName ?: $group);
            @endphp
            <tr>
                <td style="width: 70px">{{ $index === 0 ? 'Kepada' : '' }}</td><td style="width: 10px">{{ $index === 0 ? ':' : '' }}</td>
                <td style="width: 20px" class="va-top">{{ $index + 1 }}.</td>
                <td>{{ data_get($employee, 'nama') }} / NIP. {{ data_get($employee, 'nip') }}</td>
            </tr>
            <tr><td></td><td></td><td></td><td><span style="display:inline-block;width:55px" class="va-top">Jabatan</span>: {{ $position }}</td></tr>
            @if($rank)
                <tr><td></td><td></td><td></td><td><span style="display:inline-block;width:55px" class="va-top">Pangkat</span>: {{ $rank }}</td></tr>
            @endif
        @empty
            <tr><td style="width:70px">Kepada</td><td style="width:10px">:</td><td colspan="2">Belum ada pelaksana tugas.</td></tr>
        @endforelse
    </table>

    <table style="margin-top: 15px">
        <tr>
            <td style="width:70px" class="va-top">Untuk</td><td style="width:10px" class="va-top">:</td><td style="width:20px" class="va-top">C.</td>
            <td class="text-justify">Seterima surat perintah ini segera menyiapkan diri untuk berangkat dari {{ $spt->destination->departure_place }} - {{ $spt->destination->destination_place }} dalam rangka {{ $spt->dalam_rangka }}.</td>
        </tr>
        <tr><td></td><td></td><td class="va-top">D.</td><td class="text-justify">Berangkat pada kesempatan pertama dengan {{ $spt->destination->transportation }} biaya Negara Tahun {{ $spt->issued_date->format('Y') }}.</td></tr>
        <tr><td></td><td></td><td>E.</td><td>Lama Perjalanan Dinas {{ $spt->destination->duration_days }} hari.</td></tr>
        <tr><td></td><td></td><td class="va-top">F.</td><td class="text-justify">Setelah melaksanakan urusan dinas segera melaporkan hasilnya kepada {{ $signatory->signatory_role ?: data_get($signatory->employee_snapshot, 'jabatan.nama') }}.</td></tr>
        <tr><td></td><td></td><td>G.</td><td>Diindahkan dan dilaksanakan dengan penuh rasa tanggung jawab.</td></tr>
    </table>

    @include('documents.partials.signatory', [
        'signatory' => $signatory,
        'issuedPlace' => $spt->issued_place,
        'issuedDate' => $spt->issued_date,
        'documentNumber' => $spt->document_number,
        'marginTop' => 24,
    ])

    <div class="footer">
        <div style="text-decoration: underline">Tembusan Kepada Yth.</div>
        @foreach($stationery['copies'] as $index => $copy)<div>{{ $index + 1 }}. {{ $copy }}</div>@endforeach
    </div>
@endsection
