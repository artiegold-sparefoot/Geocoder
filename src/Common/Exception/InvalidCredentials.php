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
 * Thrown when the Provider API declines the request because of wrong credentials.
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class InvalidCredentials extends \RuntimeException implements Exception
{
}
