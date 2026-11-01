<?php

namespace App\Exceptions;

use RuntimeException;

final class MissingOutboxDelivery extends RuntimeException {}
