<?php

use App\Services\GeoIpService;
use Illuminate\Support\Facades\Http;

test('geoip resolves from the local mmdb without calling external apis', function () {
    Http::fake(); // any external provider call would be recorded
    config([
        'services.geolite.city_db' => base_path('tests/fixtures/city-test.mmdb'),
        'services.geolite.asn_db' => base_path('tests/fixtures/asn-test.mmdb'),
    ]);

    $details = app(GeoIpService::class)->details('203.0.113.7');

    expect($details)->toMatchArray(['country' => 'ID', 'city' => 'Jakarta', 'provider' => 'Telkom Indonesia']);
    Http::assertNothingSent();
});
