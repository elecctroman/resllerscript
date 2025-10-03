<?php

namespace App;

class View
{
    /**
     * @param string $template
     * @param array<string,mixed> $data
     * @return void
     */
    public static function render($template, array $data = array())
    {
        $clean = ltrim((string) $template, '/');
        $clean = str_replace(array('..', '\\'), '', $clean);
        $path = dirname(__DIR__) . '/views/' . $clean;

        if (!is_file($path)) {
            error_log('[View] Template not found: ' . $clean);
            return;
        }

        if ($data) {
            extract($data, EXTR_SKIP);
        }

        include $path;
    }
}
