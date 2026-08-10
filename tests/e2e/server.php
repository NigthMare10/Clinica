<?php

$public = dirname(__DIR__, 2).'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

require $public.'/index.php';
