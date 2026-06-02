<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Handler;

/**
 * Type cast valid JSON strings to arrays
 * Supports both JSON objects {"key":"value"} and JSON arrays ["a","b"]
 */
class CastStringToJson
{
    private CastTypeHandler $castTypeHandler;

    public function __construct(CastTypeHandler $castTypeHandler)
    {
        $this->castTypeHandler = $castTypeHandler;
    }

    /**
     * @param string|null $value
     * @return array<int|string, mixed>|string|null
     */
    public function __invoke(?string $value = null): array|string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$this->looksLikeJson($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return $value;
        }

        return $this->castValues($decoded);
    }

    private function looksLikeJson(string $value): bool
    {
        $firstChar = $value[0] ?? '';
        $lastChar = substr($value, -1);

        return ($firstChar === '{' && $lastChar === '}')
            || ($firstChar === '[' && $lastChar === ']');
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    private function castValues(array $data): array
    {
        foreach ($data as $key => $item) {
            if (is_array($item)) {
                $data[$key] = $this->castValues($item);
            } elseif (is_string($item)) {
                $data[$key] = ($this->castTypeHandler)($item);
            }
        }

        return $data;
    }
}
