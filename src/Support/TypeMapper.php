<?php

namespace Botnetdobbs\Luminous\Support;

use Botnetdobbs\Luminous\Extractors\EnumExtractor;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

class TypeMapper
{
    private EnumExtractor $enumExtractor;

    public function __construct(?EnumExtractor $enumExtractor = null)
    {
        $this->enumExtractor = $enumExtractor ?? new EnumExtractor;
    }

    private const PHP_TO_OPENAPI = [
        'int' => ['type' => 'integer'],
        'float' => ['type' => 'number', 'format' => 'float'],
        'bool' => ['type' => 'boolean'],
        'string' => ['type' => 'string'],
        'array' => ['type' => 'array'],
        'mixed' => [],
        'void' => [],
    ];

    private const FORMAT_MAP = [
        'uuid' => ['type' => 'string',  'format' => 'uuid'],
        'email' => ['type' => 'string',  'format' => 'email'],
        'uri' => ['type' => 'string',  'format' => 'uri'],
        'url' => ['type' => 'string',  'format' => 'uri'],
        'date-time' => ['type' => 'string',  'format' => 'date-time'],
        'date' => ['type' => 'string',  'format' => 'date'],
        'time' => ['type' => 'string',  'format' => 'time'],
        'password' => ['type' => 'string',  'format' => 'password'],
        'binary' => ['type' => 'string',  'format' => 'binary'],
        'byte' => ['type' => 'string',  'format' => 'byte'],
        'int32' => ['type' => 'integer', 'format' => 'int32'],
        'int64' => ['type' => 'integer', 'format' => 'int64'],
        'float' => ['type' => 'number',  'format' => 'float'],
        'double' => ['type' => 'number',  'format' => 'double'],
    ];

    public function phpTypeToOpenApi(string $phpType, ?string $format = null): array
    {
        if ($format && isset(self::FORMAT_MAP[$format])) {
            return self::FORMAT_MAP[$format];
        }

        $base = ltrim($phpType, '?');

        // Handle PHP 8 union types (e.g. string|null, int|float|null)
        if (str_contains($base, '|')) {
            $parts = explode('|', $base);
            $parts = collect($parts)->reject(fn ($p) => $p === 'null')->values()->all();
            $base = count($parts) === 1 ? $parts[0] : 'mixed';
        }

        if (class_exists($base) && is_subclass_of($base, \BackedEnum::class)) {
            return $this->enumToOpenApi($base);
        }

        return self::PHP_TO_OPENAPI[$base] ?? ['type' => 'string'];
    }

    public function enumToOpenApi(string $enumClass): array
    {
        return $this->enumExtractor->extract($enumClass);
    }

    /**
     * Two-pass approach:
     *   Pass 1: resolve the field type from rule strings
     *   Pass 2: apply constraints using the resolved type
     *
     * Single-pass fails because min:/max: may appear before the type keyword
     * in the rules array, producing numeric constraints on string fields.
     */
    public function validationRulesToSchema(array $rules): array
    {
        $ruleStrings = collect($rules)->filter(fn ($r) => is_string($r));
        $ruleObjects = collect($rules)->filter(fn ($r) => is_object($r));

        // Pass 1: resolve the field type
        $resolvedType = 'string';
        foreach ($ruleStrings as $rule) {
            $resolvedType = match (true) {
                $rule === 'integer', $rule === 'int' => 'integer',
                $rule === 'numeric', $rule === 'decimal', str_starts_with($rule, 'decimal:') => 'number',
                $rule === 'boolean', $rule === 'bool' => 'boolean',
                $rule === 'array' => 'array',
                $rule === 'file', $rule === 'image' => 'file',
                $rule === 'string' => 'string',
                default => $resolvedType,
            };
        }

        $schema = ['type' => $resolvedType === 'file' ? 'string' : $resolvedType];
        if ($resolvedType === 'file') {
            $schema['format'] = 'binary';
        }

        $isNumeric = in_array($resolvedType, ['integer', 'number'], true);
        $isArray = $resolvedType === 'array';

        // Pass 2: apply constraints based on the resolved type
        foreach ($ruleStrings as $rule) {
            if ($rule === 'email') {
                $schema['format'] = 'email';

                continue;
            }
            if ($rule === 'uuid') {
                $schema['format'] = 'uuid';

                continue;
            }
            if ($rule === 'url') {
                $schema['format'] = 'uri';

                continue;
            }
            if ($rule === 'nullable') {
                $schema['nullable'] = true;

                continue;
            }
            if ($rule === 'ip' || $rule === 'ipv4') {
                $schema['format'] = 'ipv4';

                continue;
            }
            if ($rule === 'ipv6') {
                $schema['format'] = 'ipv6';

                continue;
            }
            if ($rule === 'confirmed') {
                $schema['writeOnly'] = true;
                if ($resolvedType === 'string') {
                    $schema['format'] = 'password';
                }

                continue;
            }
            if ($rule === 'date') {
                $schema['format'] = 'date';

                continue;
            }
            if ($rule === 'date_format:Y-m-d') {
                $schema['format'] = 'date';

                continue;
            }
            if ($rule === 'date_format:Y-m-d\TH:i:sP') {
                $schema['format'] = 'date-time';

                continue;
            }

            if (str_starts_with($rule, 'min:')) {
                $val = (int) substr($rule, 4);
                $schema[$isNumeric ? 'minimum' : ($isArray ? 'minItems' : 'minLength')] = $val;

                continue;
            }
            if (str_starts_with($rule, 'max:')) {
                $val = (int) substr($rule, 4);
                $schema[$isNumeric ? 'maximum' : ($isArray ? 'maxItems' : 'maxLength')] = $val;

                continue;
            }
            if (str_starts_with($rule, 'size:')) {
                $val = (int) substr($rule, 5);
                $schema[$isNumeric ? 'minimum' : ($isArray ? 'minItems' : 'minLength')] = $val;
                $schema[$isNumeric ? 'maximum' : ($isArray ? 'maxItems' : 'maxLength')] = $val;

                continue;
            }
            if (str_starts_with($rule, 'between:')) {
                $parts = explode(',', substr($rule, 8), 2);
                if (count($parts) !== 2) {
                    $this->warn("Luminous: malformed between: rule '{$rule}', expected between:min,max. Skipping.");

                    continue;
                }
                [$min, $max] = $parts;
                $schema[$isNumeric ? 'minimum' : ($isArray ? 'minItems' : 'minLength')] = (int) $min;
                $schema[$isNumeric ? 'maximum' : ($isArray ? 'maxItems' : 'maxLength')] = (int) $max;

                continue;
            }
            if (str_starts_with($rule, 'digits:')) {
                // digits: means "exactly N digits". Only meaningful for string types in OpenAPI
                if (! $isNumeric) {
                    $d = (int) substr($rule, 7);
                    $schema['minLength'] = $d;
                    $schema['maxLength'] = $d;
                    $schema['pattern'] = "^\\d{{$d}}$";
                }

                continue;
            }
            if (str_starts_with($rule, 'digits_between:')) {
                if (! $isNumeric) {
                    $parts = explode(',', substr($rule, 15), 2);
                    if (count($parts) !== 2) {
                        $this->warn("Luminous: malformed digits_between: rule '{$rule}', expected digits_between:min,max. Skipping.");

                        continue;
                    }
                    [$min, $max] = $parts;
                    $schema['minLength'] = (int) $min;
                    $schema['maxLength'] = (int) $max;
                    $schema['pattern'] = "^\\d{{$min},{$max}}$";
                }

                continue;
            }
            if (str_starts_with($rule, 'in:')) {
                $schema['enum'] = explode(',', substr($rule, 3));

                continue;
            }
            if (str_starts_with($rule, 'regex:')) {
                $raw = substr($rule, 6);
                // Strip common regex delimiters (e.g. /pattern/, ~pattern~, #pattern#)
                if (strlen($raw) > 1 && $raw[0] === $raw[-1] && ! ctype_alnum($raw[0])) {
                    $raw = substr($raw, 1, -1);
                }
                // OpenAPI pattern must be ECMAScript. Warn on PCRE-only constructs
                // (atomic groups, named groups, possessive quantifiers, \K, \p{}) that
                // are invalid ECMAScript and can break Swagger UI or cause ReDoS.
                if (preg_match('/\(\?>|\(\?P[<\']|[+*?]\+|\\\\K\b|\\\\[pP]\{/', $raw)) {
                    $this->warn("Luminous: regex: rule contains PCRE-only constructs that ECMAScript does not support. The pattern may not work correctly in Swagger UI: {$raw}");
                }
                $schema['pattern'] = $raw;

                continue;
            }
            if (str_starts_with($rule, 'min_digits:')) {
                if (! $isNumeric) {
                    $schema['minLength'] = (int) substr($rule, 11);
                }

                continue;
            }
            if (str_starts_with($rule, 'max_digits:')) {
                if (! $isNumeric) {
                    $schema['maxLength'] = (int) substr($rule, 11);
                }

                continue;
            }
        }

        // Rule objects (Rule::enum(), Rule::in(), etc.)
        foreach ($ruleObjects as $rule) {
            if ($rule instanceof Enum) {
                $enumClass = $this->enumExtractor->classFromRule($rule);
                if ($enumClass !== null) {
                    $schema['enum'] = collect($enumClass::cases())->map(fn ($c) => $c->value)->all();
                }
            } elseif ($rule instanceof In) {
                try {
                    $prop = (new \ReflectionObject($rule))->getProperty('values');
                    $values = $prop->getValue($rule);
                    $schema['enum'] = collect($values)->map(fn ($v) => (string) $v)->all();
                } catch (\Throwable $e) {
                    // Comma-splitting __toString would silently corrupt values containing commas.
                    // Omit the enum list and warn rather than produce a wrong schema.
                    $this->warn("Luminous: could not read Rule::In values via reflection, so the enum list was left out of the schema. Error: {$e->getMessage()}");
                }
            } elseif (method_exists($rule, '__toString')) {
                $str = (string) $rule;
                if (str_starts_with($str, 'in:')) {
                    $schema['enum'] = collect(explode(',', substr($str, 3)))->map(fn ($v) => trim($v, '"'))->all();
                }
            }
        }

        // OpenAPI 3.1: convert nullable sentinel to type array
        if (isset($schema['nullable'])) {
            unset($schema['nullable']);
            $type = $schema['type'] ?? 'string';
            if (! is_array($type)) {
                $schema['type'] = [$type, 'null'];
            }
        }

        return collect($schema)->filter(fn ($v) => $v !== [] && $v !== '')->all();
    }

    private function warn(string $message): void
    {
        try {
            logger()->warning($message);
        } catch (\Throwable) {
            // No-op when the Laravel container is not available (e.g. unit tests).
        }
    }
}
