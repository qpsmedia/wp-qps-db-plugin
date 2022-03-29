<?php

namespace QPS\DB;

use WP_CLI;
use QPS\DB\CLI\YouTube;

class CLI
{
    /**
     * Initialize the singleton free from constructor parameters.
     */
    public function __construct()
    {
        if (!class_exists('\WP_CLI')) {
            return;
        }

        WP_CLI::add_command('qps db youtube', YouTube::class);
    }
}
