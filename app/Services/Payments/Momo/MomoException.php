<?php

namespace App\Services\Payments\Momo;

use RuntimeException;

/** A transport or configuration problem talking to MTN. Never means "payment failed". */
class MomoException extends RuntimeException
{
}
