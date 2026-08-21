@extends('documents.layout')

@section('content')
    @php
        $destination = $sppd->spt->destination;
        $signatory = $sppd->signatory;
        $employee = $signatory->employee_snapshot;
        $position = $signatory->signatory_role ?: data_get($employee, 'jabatan.nama', data_get($employee, 'jabatan'));
        $rankName = data_get($employee, 'golongan.pangkat', data_get($employee, 'pangkat.nama', data_get($employee, 'pangkat')));
        $group = data_get($employee, 'golongan.nama', data_get($employee, 'golongan'));
        $rank = $rankName && $group ? $rankName.' ('.$group.')' : ($rankName ?: $group);
    @endphp

    <table class="visum-table">
        <colgroup>
            <col style="width: 50%">
            <col style="width: 50%">
        </colgroup>
        <tbody>
            <tr class="visum-row-departure">
                <td>&nbsp;</td>
                <td>
                    <table class="visum-data-table">
                        <colgroup>
                            <col style="width: 24px">
                            <col style="width: 105px">
                            <col style="width: 16px">
                            <col>
                        </colgroup>
                        <tr><td class="visum-number">I.</td><td class="visum-label">Berangkat dari</td><td class="visum-colon">:</td><td>{{ $destination->departure_place }}</td></tr>
                        <tr><td></td><td>Ke</td><td>:</td><td>{{ $destination->destination_place }}</td></tr>
                        <tr><td></td><td>Pada Tanggal</td><td>:</td><td>{{ $sppd->departure_date->format('d/m/Y') }}</td></tr>
                    </table>
                </td>
            </tr>

            @foreach(['II.', 'III.', 'IV.'] as $roman)
                <tr class="visum-row-transit">
                    <td>
                        <table class="visum-section-table">
                            <tr>
                                <td class="visum-section-content">
                                    <table class="visum-data-table">
                                        <colgroup>
                                            <col style="width: 24px">
                                            <col style="width: 105px">
                                            <col style="width: 16px">
                                            <col>
                                        </colgroup>
                                        <tr><td class="visum-number">{{ $roman }}</td><td class="visum-label">Tiba di</td><td class="visum-colon">:</td><td>&nbsp;</td></tr>
                                        <tr><td></td><td>Pada Tanggal</td><td>:</td><td>&nbsp;</td></tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td class="visum-transit-signature">@include('documents.partials.visum-transit-signature')</td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        <table class="visum-section-table">
                            <tr>
                                <td class="visum-section-content">
                                    <table class="visum-data-table">
                                        <tr><td class="visum-label">Berangkat dari</td><td class="visum-colon">:</td><td>&nbsp;</td></tr>
                                        <tr><td>Ke</td><td>:</td><td>&nbsp;</td></tr>
                                        <tr><td>Pada Tanggal</td><td>:</td><td>&nbsp;</td></tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td class="visum-transit-signature">@include('documents.partials.visum-transit-signature')</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endforeach

            <tr class="visum-row-return-content">
                <td class="visum-return-content">
                    <table class="visum-data-table">
                        <colgroup>
                            <col style="width: 24px">
                            <col style="width: 105px">
                            <col style="width: 16px">
                            <col>
                        </colgroup>
                        <tr><td class="visum-number">V.</td><td class="visum-label visum-label-wrap">Tiba Kembali (Tempat kedudukan)</td><td class="visum-colon">:</td><td>&nbsp;</td></tr>
                        <tr><td></td><td>Pada Tanggal</td><td>:</td><td>&nbsp;</td></tr>
                    </table>
                </td>
                <td class="visum-return-content text-justify">
                    Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya dan semata-mata untuk kepentingan jabatan dalam waktu yang sesingkat-singkatnya.
                </td>
            </tr>

            <tr class="visum-row-return-signatures">
                <td>
                    <table class="visum-return-signature-table">
                        <tr><td class="visum-signatory-role">{{ $position }}</td></tr>
                        <tr><td class="visum-signature-space">&nbsp;</td></tr>
                        <tr>
                            <td class="visum-signatory-identity">
                                {{ data_get($employee, 'nama') }}<br>
                                @if($rank){{ $rank }}<br>@endif
                                NIP. {{ data_get($employee, 'nip') }}
                            </td>
                        </tr>
                    </table>
                </td>
                <td>
                    <table class="visum-return-signature-table">
                        <tr><td class="visum-signatory-role">{{ $position }}</td></tr>
                        <tr><td class="visum-signature-space">&nbsp;</td></tr>
                        <tr>
                            <td class="visum-signatory-identity">
                                {{ data_get($employee, 'nama') }}<br>
                                @if($rank){{ $rank }}<br>@endif
                                NIP. {{ data_get($employee, 'nip') }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr class="visum-row-notes">
                <td colspan="2">
                    <table class="visum-data-table">
                        <tr><td class="visum-number"><strong>VI.</strong></td><td>Catatan lain-lain:</td></tr>
                    </table>
                </td>
            </tr>
            <tr class="visum-row-warning">
                <td colspan="2">
                    <table class="visum-data-table">
                        <tr><td class="visum-number"><strong>VII.</strong></td><td><strong>PERHATIAN</strong></td></tr>
                        <tr>
                            <td></td>
                            <td class="text-justify">Pejabat yang berwenang menerbitkan SPD, pegawai yang melakukan perjalanan dinas, para pejabat yang mengesahkan tanggal berangkat/tiba, serta bendahara pengeluaran bertanggung jawab berdasarkan peraturan-peraturan keuangan daerah apabila daerah menderita rugi akibat kesalahan, kelalaian, dan kealpaannya.</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>
@endsection
