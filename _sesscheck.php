<?php
echo 'before config: ' . session_cache_limiter() . PHP_EOL;
require __DIR__ . '/config.php';
echo 'after config: ' . session_cache_limiter() . PHP_EOL;
echo 'status: ' . session_status() . PHP_EOL;
echo 'raw ini: ' . ini_get('session.cache_limiter') . PHP_EOL;
