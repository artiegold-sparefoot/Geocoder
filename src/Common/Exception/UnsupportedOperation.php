<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Exception;

/**
 * Thrown when you are trying to use a Provider for something it does not support. Example if you trying to reverse
 * geocode an IP address.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class UnsupportedOperation extends InvalidArgument implements Exception
{
}
