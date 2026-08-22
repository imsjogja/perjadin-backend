@php
    $employee = $signatory->employee_snapshot;
    $position = $signatory->signatory_role ?: data_get($employee, 'jabatan.nama', data_get($employee, 'jabatan'));
    $rankName = data_get($employee, 'golongan.pangkat', data_get($employee, 'pangkat.nama', data_get($employee, 'pangkat')));
    $group = data_get($employee, 'golongan.nama', data_get($employee, 'golongan'));
    $rank = $rankName && $group ? $rankName.' ('.$group.')' : ($rankName ?: $group);
    $acting = $signatory->is_acting ? 'Plt. ' : '';
@endphp
<table style="margin-top: {{ $marginTop ?? 24 }}px;">
    <tr>
        <td style="width: 50%; vertical-align: middle;">
            @if(! empty($qrCode))
                <div class="qr-document">
                    <div>*{{ $documentNumber }}*</div>
                    <img src="{{ $qrCode }}" alt="QR Code nomor surat">
                </div>
            @endif
        </td>
        <td style="width: 50%; vertical-align: top;">
            <table class="inner-table">
                <tr>
                    <td style="width: 110px">Dikeluarkan di</td>
                    <td style="width: 10px">:</td>
                    <td>{{ $issuedPlace }}</td>
                </tr>
                <tr>
                    <td style="border-bottom: 1px solid #000">Tanggal</td>
                    <td style="border-bottom: 1px solid #000">:</td>
                    <td style="border-bottom: 1px solid #000">{{ $issuedDate->format('d/m/Y') }}</td>
                </tr>
                @if($signatory->behalf_of)
                    <tr><td colspan="3" class="signatory-role" style="padding-top:4px">{{ $signatory->behalf_of }}</td></tr>
                @endif
                <tr><td colspan="3" class="signatory-role" style="padding-top:4px">{{ $acting }}{{ $position }}</td></tr>
                <tr><td colspan="3" class="signature-space"></td></tr>
                <tr><td colspan="3" class="signatory-identity">{{ data_get($employee, 'nama') }}</td></tr>
                @if($rank)<tr><td colspan="3" class="signatory-identity">{{ $rank }}</td></tr>@endif
                <tr><td colspan="3" class="signatory-identity">NIP. {{ data_get($employee, 'nip') }}</td></tr>
            </table>
        </td>
    </tr>
</table>
