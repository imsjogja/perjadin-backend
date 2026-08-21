@extends('documents.layout')

@section('content')
    @php
        $signatory = $spt->signatory;
        $useSecretariat = str_contains(strtoupper((string) $signatory->signatory_role), 'SEKDA');
        $bases = $spt->bases
            ->pluck('content')
            ->filter(fn ($content) => filled($content))
            ->values();
        if ($bases->isEmpty() && filled($spt->dasar)) {
            $bases = collect(preg_split('/\R/', $spt->dasar))
                ->filter(fn ($content) => filled($content))
                ->values();
        }
        $letter = static function (int $index): string {
            $number = $index + 1;
            $value = '';
            while ($number > 0) {
                $number--;
                $value = chr(65 + ($number % 26)).$value;
                $number = intdiv($number, 26);
            }

            return $value;
        };
        $commandLetterIndex = $bases->count();
    @endphp
    @include('documents.partials.stationery', compact('stationery', 'useSecretariat'))

    <div style="margin-top: 38px">
        <div class="document-title">Surat Perintah Tugas</div>
        <div class="document-number">Nomor : {{ $spt->document_number }}</div>
    </div>

    <table style="margin-top: 15px">
        @forelse($bases as $index => $basis)
            <tr>
                <td style="width: 70px">{{ $index === 0 ? 'Dasar' : '' }}</td><td style="width: 10px">{{ $index === 0 ? ':' : '' }}</td>
                <td style="width: 20px" class="va-top">{{ $letter($index) }}.</td><td class="text-justify">{{ $basis }}</td>
            </tr>
        @empty
            <tr><td style="width: 70px">Dasar</td><td style="width: 10px">:</td><td colspan="2">-</td></tr>
        @endforelse
        @if($spt->disposisi)
            <tr><td></td><td></td><td class="va-top">{{ $letter($commandLetterIndex) }}.</td><td class="text-justify">{{ $spt->disposisi }}</td></tr>
            @php
                $commandLetterIndex++;
            @endphp
        @endif
    </table>

    <p class="text-center document-command">MEMERINTAHKAN</p>

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
        @php($letterForPurpose = $letter($commandLetterIndex++))
        <tr>
            <td style="width:70px" class="va-top">Untuk</td><td style="width:10px" class="va-top">:</td><td style="width:20px" class="va-top">{{ $letterForPurpose }}.</td>
            <td class="text-justify">Seterima surat perintah ini segera menyiapkan diri untuk berangkat dari {{ $spt->destination->departure_place }} - {{ $spt->destination->destination_place }} dalam rangka {{ $spt->dalam_rangka }}.</td>
        </tr>
        @php($letterForDeparture = $letter($commandLetterIndex++))
        <tr><td></td><td></td><td class="va-top">{{ $letterForDeparture }}.</td><td class="text-justify">Berangkat pada kesempatan pertama dengan {{ $spt->destination->transportation }} biaya Negara Tahun {{ $spt->issued_date->format('Y') }}.</td></tr>
        @php($letterForDuration = $letter($commandLetterIndex++))
        <tr><td></td><td></td><td>{{ $letterForDuration }}.</td><td>Lama Perjalanan Dinas {{ $spt->destination->duration_days }} hari.</td></tr>
        @php($letterForReport = $letter($commandLetterIndex++))
        <tr><td></td><td></td><td class="va-top">{{ $letterForReport }}.</td><td class="text-justify">Setelah melaksanakan urusan dinas segera melaporkan hasilnya kepada {{ $signatory->signatory_role ?: data_get($signatory->employee_snapshot, 'jabatan.nama') }}.</td></tr>
        @php($letterForExecution = $letter($commandLetterIndex))
        <tr><td></td><td></td><td>{{ $letterForExecution }}.</td><td>Diindahkan dan dilaksanakan dengan penuh rasa tanggung jawab.</td></tr>
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
