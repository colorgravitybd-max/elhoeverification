<?php
/**
 * Admin API bootstrap. Requires authenticated admin session.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../_bootstrap.php';

use Elhoe\Auth;

Auth::require();
