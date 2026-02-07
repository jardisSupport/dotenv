<?php

declare(strict_types=1);

namespace JardisSupport\DotEnv\Casting;

use Exception;

/**
 * Type cast string to array
 */
class CastStringToArray
{
    private CastTypeHandler $CastTypeHandler;

    public function __construct(CastTypeHandler $CastTypeHandler)
    {
        $this->CastTypeHandler = $CastTypeHandler;
    }

    /**
     * @param string|null $value
     * @return array<int|string, array<int|string, mixed>|string|null>|string|null
     * @throws Exception
     */
    public function __invoke(?string $value = null): array|string|null
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('/[\[\]]|=>/', $value)) {
            $result = [];
            $value = (string) preg_replace(['/^\[/', '/\]$/'], '', $value);
            preg_match_all(
                '/\w+=>\[[^\[\]]*(?:\[[^\[\]]*\])*[^\[\]]*\]|\w+=>[^,]+|[^,]+/',
                $value,
                $matches
            );

            if (!empty($matches[0])) {
                foreach ($matches[0] as $element) {
                    if (strpos($element, '=>') !== false) {
                        [$key, $rawValue] = explode('=>', $element, 2);
                        $key = trim($key);

                        if (preg_match('/^\[.*\]$/', $rawValue)) {
                            $result[$key] = $this->__invoke($rawValue);
                        } else {
                            $result[$key] = $rawValue ? ($this->CastTypeHandler)(trim($rawValue)) : null;
                        }
                    } else {
                        $result[] = ($this->CastTypeHandler)(trim($element));
                    }
                }
            }

            return $result;
        }

        return $value;
    }
}
