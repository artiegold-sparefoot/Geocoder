<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Mapzen;

use Geocoder\Collection;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\QuotaExceeded;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\AbstractHttpProvider;
use Geocoder\Provider\Provider;
use Http\Client\HttpClient;

/**
 * @author Gary Gale <gary@vicchi.org>
 */
final class Mapzen extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const GEOCODE_ENDPOINT_URL = 'https://search.mapzen.com/v1/search?text=%s&key=%s&size=%d';

    /**
     * @var string
     */
    const REVERSE_ENDPOINT_URL = 'https://search.mapzen.com/v1/reverse?point.lat=%f&point.lon=%f&key=%s&size=%d';

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @param HttpClient $client an HTTP adapter
     * @param string     $apiKey an API key
     */
    public function __construct(HttpClient $client, $apiKey)
    {
        parent::__construct($client);

        $this->apiKey = $apiKey;
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query)
    {
        $address = $query->getText();
        if (null === $this->apiKey) {
            throw new InvalidCredentials('No API Key provided.');
        }

        // This API doesn't handle IPs
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The Mapzen provider does not support IP addresses, only street addresses.');
        }

        $url = sprintf(self::GEOCODE_ENDPOINT_URL, urlencode($address), $this->apiKey, $query->getLimit());

        return $this->executeQuery($url);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query)
    {
        $coordinates = $query->getCoordinates();
        $longitude = $coordinates->getLongitude();
        $latitude = $coordinates->getLatitude();
        if (null === $this->apiKey) {
            throw new InvalidCredentials('No API Key provided.');
        }

        $url = sprintf(self::REVERSE_ENDPOINT_URL, $latitude, $longitude, $this->apiKey, $query->getLimit());

        return $this->executeQuery($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'mapzen';
    }

    /**
     * @param $url
     *
     * @return Collection
     */
    private function executeQuery($url)
    {
        $content = $this->getUrlContents($url);
        $json = json_decode($content, true);

        // See https://mapzen.com/documentation/search/api-keys-rate-limits/
        if (isset($json['meta'])) {
            switch ($json['meta']['status_code']) {
                case 403:
                    throw new InvalidCredentials('Invalid or missing api key.');
                case 429:
                    throw new QuotaExceeded('Valid request but quota exceeded.');
            }
        }

        if (!isset($json['type']) || $json['type'] !== 'FeatureCollection' || !isset($json['features']) || count($json['features']) === 0) {
            return new AddressCollection([]);
        }

        $locations = $json['features'];

        if (empty($locations)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($locations as $location) {
            $bounds = [];
            if (isset($location['bbox'])) {
                $bounds = [
                    'south' => $location['bbox'][3],
                    'west' => $location['bbox'][2],
                    'north' => $location['bbox'][1],
                    'east' => $location['bbox'][0],
                ];
            }

            $props = $location['properties'];

            $adminLevels = [];
            foreach (['region', 'locality', 'macroregion', 'country'] as $i => $component) {
                if (isset($props[$component])) {
                    $adminLevels[] = ['name' => $props[$component], 'level' => $i + 1];
                }
            }

            $results[] = array_merge($this->getDefaults(), [
                'latitude' => $location['geometry']['coordinates'][1],
                'longitude' => $location['geometry']['coordinates'][0],
                'bounds' => $bounds ?: [],
                'streetNumber' => isset($props['housenumber']) ? $props['housenumber'] : null,
                'streetName' => isset($props['street']) ? $props['street'] : null,
                'subLocality' => isset($props['neighbourhood']) ? $props['neighbourhood'] : null,
                'locality' => isset($props['locality']) ? $props['locality'] : null,
                'postalCode' => isset($props['postalcode']) ? $props['postalcode'] : null,
                'adminLevels' => $adminLevels,
                'country' => isset($props['country']) ? $props['country'] : null,
                'countryCode' => isset($props['country_a']) ? strtoupper($props['country_a']) : null,
            ]);
        }

        return $this->returnResults($results);
    }

    /**
     * @param array $components
     *
     * @return null|string
     */
    protected function guessLocality(array $components)
    {
        $localityKeys = ['city', 'town', 'village', 'hamlet'];

        return $this->guessBestComponent($components, $localityKeys);
    }

    /**
     * @param array $components
     *
     * @return null|string
     */
    protected function guessStreetName(array $components)
    {
        $streetNameKeys = ['road', 'street', 'street_name', 'residential'];

        return $this->guessBestComponent($components, $streetNameKeys);
    }

    /**
     * @param array $components
     *
     * @return null|string
     */
    protected function guessSubLocality(array $components)
    {
        $subLocalityKeys = ['neighbourhood', 'city_district'];

        return $this->guessBestComponent($components, $subLocalityKeys);
    }

    /**
     * @param array $components
     * @param array $keys
     *
     * @return null|string
     */
    protected function guessBestComponent(array $components, array $keys)
    {
        foreach ($keys as $key) {
            if (isset($components[$key]) && !empty($components[$key])) {
                return $components[$key];
            }
        }

        return null;
    }
}
