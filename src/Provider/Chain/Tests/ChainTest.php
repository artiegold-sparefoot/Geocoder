<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Chain\Tests;

use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Provider;
use Geocoder\Tests\TestCase;
use Geocoder\Provider\Chain\Chain;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class ChainTest extends TestCase
{
    public function testAdd()
    {
        $mock = $this->getMockBuilder('Geocoder\Provider\Provider')->getMock();
        $chain = new Chain();

        $chain->add($mock);
    }

    public function testGetName()
    {
        $chain = new Chain();
        $this->assertEquals('chain', $chain->getName());
    }

    public function testReverse()
    {
        $mockOne = $this->getMockBuilder(Provider::class)->getMock();
        $mockOne->expects($this->once())
            ->method('reverseQuery')
            ->will($this->returnCallback(function () {
                throw new \Exception();
            }));

        $mockTwo = $this->getMockBuilder('Geocoder\\Provider\\Provider')->getMock();
        $mockTwo->expects($this->once())
            ->method('reverseQuery')
            ->will($this->returnValue(['foo' => 'bar']));

        $chain = new Chain([$mockOne, $mockTwo]);

        $this->assertEquals(['foo' => 'bar'], $chain->reverseQuery(ReverseQuery::fromCoordinates(11, 22)));
    }

    public function testGeocode()
    {
        $query = GeocodeQuery::create('Paris');
        $mockOne = $this->getMockBuilder('Geocoder\\Provider\\Provider')->getMock();
        $mockOne->expects($this->once())
            ->method('geocodeQuery')
            ->will($this->returnCallback(function () {
                throw new \Exception();
            }));

        $mockTwo = $this->getMockBuilder('Geocoder\\Provider\\Provider')->getMock();
        $mockTwo->expects($this->once())
            ->method('geocodeQuery')
            ->with($query)
            ->will($this->returnValue(['foo' => 'bar']));

        $chain = new Chain([$mockOne, $mockTwo]);

        $this->assertEquals(['foo' => 'bar'], $chain->geocodeQuery($query));
    }
}
