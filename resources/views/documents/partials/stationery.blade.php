<table class="kop">
    <tr>
        @if(! empty($stationery['logo_data_uri']))
            <td class="kop-logo"><img src="{{ $stationery['logo_data_uri'] }}" alt="Logo instansi"></td>
        @endif
        <td>
            <p class="kop-government">Pemerintah {{ $stationery['government'] }}</p>
            <p class="kop-agency">{{ $useSecretariat ? $stationery['secretariat'] : $stationery['agency'] }}</p>
            <span>{{ $stationery['address'] }} - {{ $stationery['city'] }}</span>
        </td>
    </tr>
    <tr><td class="kop-line" colspan="{{ ! empty($stationery['logo_data_uri']) ? 2 : 1 }}"></td></tr>
</table>
