<?php

namespace QPS\DB;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

class Logger
{
    public static function getLogger(): MonologLogger
    {
        $logger = new MonologLogger('name');
        $logger->pushHandler(new StreamHandler(WP_CONTENT_DIR . '/cache/qpsdb-logs.log'));
        return $logger;
    }
}
