<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\IpInfoDb;

use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Collection;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\AbstractHttpProvider;
use Geocoder\Provider\IpAddressGeocoder;
use Geocoder\Provider\Provider;
use Http\Client\HttpClient;

/**
 * @author William Durand <william.durand1@gmail.com>
 */
final class IpInfoDb extends AbstractHttpProvider implements Provider, IpAddressGeocoder
{
    /**
     * @var string
     */
    const CITY_PRECISION_ENDPOINT_URL = 'https://api.ipinfodb.com/v3/ip-city/?key=%s&format=json&ip=%s';

    /**
     * @var string
     */
    const COUNTRY_PRECISION_ENDPOINT_URL = 'https://api.ipinfodb.com/v3/ip-country/?key=%s&format=json&ip=%s';

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $endpointUrl;

    /**
     * @param HttpClient $client    an HTTP adapter
     * @param string     $apiKey    an API key
     * @param string     $precision The endpoint precision. Either "city" or "country" (faster)
     *
     * @throws \Geocoder\Exception\InvalidArgument
     */
    public function __construct(HttpClient $client, $apiKey, $precision = 'city')
    {
        parent::__construct($client);

        $this->apiKey = $apiKey;
        switch ($precision) {
            case 'city':
                $this->endpointUrl = self::CITY_PRECISION_ENDPOINT_URL;
                break;

            case 'country':
                $this->endpointUrl = self::COUNTRY_PRECISION_ENDPOINT_URL;
                break;

            default:
                throw new InvalidArgument(sprintf(
                    'Invalid precision value "%s" (allowed values: "city", "country").',
                    $precision
                ));
        }
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

        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The IpInfoDb provider does not support street addresses, only IPv4 addresses.');
        }

        // This API does not support IPv6
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new UnsupportedOperation('The IpInfoDb provider does not support IPv6 addresses, only IPv4 addresses.');
        }

        if ('127.0.0.1' === $address) {
            return $this->returnResults([$this->getLocalhostDefaults()]);
        }

        $url = sprintf($this->endpointUrl, $this->apiKey, $address);

        return $this->executeQuery($url);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query)
    {
        throw new UnsupportedOperation('The IpInfoDb provider is not able to do reverse geocoding.');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'ip_info_db';
    }

    /**
     * @param string $url
     *
     * @return Collection
     */
    private function executeQuery($url)
    {
        $content = $this->getUrlContents($url);
        $data = json_decode($content, true);

        if (empty($data) || 'OK' !== $data['statusCode']) {
            return new AddressCollection([]);
        }

        $timezone = null;
        if (isset($data['timeZone'])) {
            $timezone = timezone_name_from_abbr('', (int) substr($data['timeZone'], 0, strpos($data['timeZone'], ':')) * 3600, 0);
        }

        return $this->returnResults([
            array_merge($this->getDefaults(), [
                'latitude' => isset($data['latitude']) ? $data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? $data['longitude'] : null,
                'locality' => isset($data['cityName']) ? $data['cityName'] : null,
                'postalCode' => isset($data['zipCode']) ? $data['zipCode'] : null,
                'adminLevels' => isset($data['regionName']) ? [['name' => $data['regionName'], 'level' => 1]] : [],
                'country' => isset($data['countryName']) ? $data['countryName'] : null,
                'countryCode' => isset($data['countryName']) ? $data['countryCode'] : null,
                'timezone' => $timezone,
            ]),
        ]);
    }
}
