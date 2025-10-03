<?php

declare(strict_types=1);

namespace Lotus;

final class ResponseTypes
{
    /**
     * Normalize numeric string values commonly returned by the API.
     *
     * @param mixed $payload
     * @return mixed
     */
    public static function normalizeNumericValues(mixed $payload): mixed
    {
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                if (is_array($value)) {
                    $payload[$key] = self::normalizeNumericValues($value);
                    continue;
                }

                if (is_string($value) && ($key === 'amount' || $key === 'credit')) {
                    $payload[$key] = self::safeFloatval($value);
                }
            }
        }

        return $payload;
    }

    public static function appendCreatedAtDateTime(array $payload): array
    {
        if (isset($payload['data'])) {
            $payload['data'] = self::transformCreatedAt($payload['data']);
        }

        return $payload;
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private static function transformCreatedAt(mixed $data): mixed
    {
        if (is_array($data)) {
            if (self::isAssoc($data)) {
                if (isset($data['created_at']) && is_string($data['created_at'])) {
                    $data['created_at_dt'] = self::toDateTimeImmutable($data['created_at']);
                }

                foreach ($data as $key => $value) {
                    $data[$key] = self::transformCreatedAt($value);
                }
            } else {
                foreach ($data as $index => $value) {
                    $data[$index] = self::transformCreatedAt($value);
                }
            }
        }

        return $data;
    }

    public static function toDateTimeImmutable(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function applyListMeta(array $payload, array $params = []): array
    {
        $data = $payload['data'] ?? [];
        $count = is_array($data) ? count($data) : 0;

        $payload['meta'] = array_merge([
            'page' => isset($params['page']) ? (int) $params['page'] : 1,
            'per_page' => isset($params['per_page']) ? (int) $params['per_page'] : ($count > 0 ? $count : null),
            'count' => $count,
        ], $payload['meta'] ?? []);

        return $payload;
    }

    private static function safeFloatval(string $value): float
    {
        if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches) === 1) {
            return (float) $matches[0];
        }

        return (float) $value;
    }

    private static function isAssoc(array $array): bool
    {
        return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
    }
}
