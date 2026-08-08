<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the TfL line provider cannot serve a usable response
 * (timeout, connection failure, 4xx/5xx, or malformed payload).
 */
final class LineProviderUnavailableException extends RuntimeException {}
