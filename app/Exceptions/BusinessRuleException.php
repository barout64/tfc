<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A request broke a business rule (e.g. entitlement exceeded). Message is shown to the user.
 */
class BusinessRuleException extends RuntimeException {}
