<?php

namespace QPS\DB;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

class Logger
{
    protected static MonologLogger $logger;

    public static function getLogger(): MonologLogger
    {
        if (!isset(self::$logger)) {
            self::$logger = new MonologLogger('name');
            self::$logger->pushHandler(new StreamHandler(WP_CONTENT_DIR . '/cache/qpsdb-logs.log'));
        }

        return self::$logger;
    }
}
