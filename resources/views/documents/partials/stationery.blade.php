<table class="kop">
    <tr>
        @if(! empty($stationery['logo_data_uri']))
            <td class="kop-logo"><img src="{{ $stationery['logo_data_uri'] }}" alt="Logo instansi"></td>
        @endif
        <td>
            <h2 class="kop-government">Pemerintah {{ $stationery['government'] }}</h2>
            <h2 class="kop-agency">{{ $useSecretariat ? $stationery['secretariat'] : $stationery['agency'] }}</h2>
            <span class="kop-address">{{ $stationery['address'] }} - {{ $stationery['city'] }}</span>
        </td>
    </tr>
    <tr><td class="kop-line" colspan="{{ ! empty($stationery['logo_data_uri']) ? 2 : 1 }}"></td></tr>
</table>
