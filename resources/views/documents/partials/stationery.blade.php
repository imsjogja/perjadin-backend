<table @class([
    'kop',
    'kop-'.$documentType,
    'kop-with-logo' => ! empty($stationery['logo_data_uri']),
])>
    <tr>
        @if(! empty($stationery['logo_data_uri']))
            <td class="kop-logo"><img src="{{ $stationery['logo_data_uri'] }}" alt="Logo instansi"></td>
        @endif
        <td class="kop-content">
            <h2 class="kop-government">Pemerintah {{ $stationery['government'] }}</h2>
            <h2 class="kop-agency">{{ $useSecretariat ? $stationery['secretariat'] : $stationery['agency'] }}</h2>
            <span class="kop-address">{{ $stationery['address'] }} - {{ $stationery['city'] }}</span>
        </td>
        @if(! empty($stationery['logo_data_uri']))
            <td class="kop-spacer"></td>
        @endif
    </tr>
    <tr><td class="kop-line" colspan="{{ ! empty($stationery['logo_data_uri']) ? 3 : 1 }}"></td></tr>
</table>
