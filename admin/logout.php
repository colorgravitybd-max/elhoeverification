<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
use Elhoe\Auth;
Auth::logout();
header('Location: ' . admin_url());
exit;
