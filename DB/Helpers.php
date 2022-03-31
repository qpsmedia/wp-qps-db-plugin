<?php

namespace QPS\DB;

class Helpers
{
    public static function arrayGet(array $array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    public static function arrayOnly(array $array, $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function requireWith(string $filepath, array $vars = []): string
    {
        extract($vars);

        ob_start();

        require $filepath;

        return ob_get_clean();
    }

    public static function currentUrl(): string
    {
        return "https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    }

    public static function GET(string $var, $default = null)
    {
        return isset($_GET[$var]) ? $_GET[$var] : $default;
    }

    public static function POST(string $var, $default = null)
    {
        return isset($_POST[$var]) ? $_POST[$var] : $default;
    }

    public static function REQUEST(string $var, $default = null)
    {
        return isset($_REQUEST[$var]) ? $_REQUEST[$var] : $default;
    }

    public static function SERVER(string $var, $default = null)
    {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : $default;
    }

    public static function humanFileSize(int $size, string $unit = ""): string
    {
        if ((!$unit && $size >= 1 << 30) || $unit === "GB") {
            return number_format($size / (1 << 30), 2) . " GB";
        }

        if ((!$unit && $size >= 1 << 20) || $unit === "MB") {
            return number_format($size / (1 << 20), 2) . " MB";
        }

        if ((!$unit && $size >= 1 << 10) || $unit === "KB") {
            return number_format($size / (1 << 10), 2) . " KB";
        }

        return number_format($size) . " B";
    }

    public static function safeCLIArg(string $arg): string
    {
        $arg = strip_tags($arg);
        $arg = addslashes($arg);
        // $arg = str_replace(["\r\n", "\n\r", "\n", "\r"], '\n', $arg);

        return $arg;
    }
}
