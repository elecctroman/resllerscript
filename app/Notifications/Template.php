<?php

declare(strict_types=1);

namespace App\Notifications;

final class Template
{
    /**
     * @param array<string, scalar|array|null> $payload
     */
    public static function render(string $template, array $payload): string
    {
        $replacements = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{{' . $key . '}}'] = (string) $value;
            }
        }

        return strtr($template, $replacements);
    }
}
