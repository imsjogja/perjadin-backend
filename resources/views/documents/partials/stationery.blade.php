@php
    $address = $useSecretariat
        ? data_get($stationery, 'secretariat_address', data_get($stationery, 'address'))
        : data_get($stationery, 'address');
    $city = $useSecretariat
        ? data_get($stationery, 'secretariat_city', data_get($stationery, 'city'))
        : data_get($stationery, 'city');
@endphp

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
            <div class="kop-content-inner">
                <h2 class="kop-government">Pemerintah {{ $stationery['government'] }}</h2>
                <h2 class="kop-agency">{{ $useSecretariat ? $stationery['secretariat'] : $stationery['agency'] }}</h2>
                <span class="kop-address">{{ $address }} - {{ $city }}</span>
            </div>
        </td>
    </tr>
    <tr><td class="kop-line" colspan="{{ ! empty($stationery['logo_data_uri']) ? 2 : 1 }}"></td></tr>
</table>
