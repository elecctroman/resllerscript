<?php
$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
$target = preg_replace('~^/magaza~i', '', $uri);
if ($target === '' || $target === false) {
    $target = '/';
}
header('Location: ' . $target, true, 301);
exit;
