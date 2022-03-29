<?php

/**
 * @package QPS DB
 * @version 0.0.1
 *
 * Plugin Name: QPS DB
 * Description: Allows uploading of video media assets to YouTube.
 * Version: 0.0.1
 * Author: QPS Media
 * Author URI: https://mypolice.qld.gov.au/
 */

require_once __DIR__ . '/vendor/autoload.php';

use QPS\DB\Admin;
use QPS\DB\CLI;

new Admin();


if (class_exists('WP_CLI')) {
    new CLI();
}
