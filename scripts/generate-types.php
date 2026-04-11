<?php

declare(strict_types=1);

/**
 * Generates PHP DTOs and enums from the ZenoBank OpenAPI spec.
 *
 * Usage: php scripts/generate-types.php [path/to/openapi.json]
 *
 * Walks $refs from a fixed allowlist of (path, method) operations the SDK
 * actually calls, then emits one PHP file per reachable schema into
 * src/Types/Generated/. Mirrors the approach used by the TS SDK's
 * scripts/generate-types.ts but written in PHP so the SDK has no Node
 * toolchain dependency.
 */

const NAMESPACE_GENERATED = 'ZenoBank\\Sdk\\Types\\Generated';

// (path, method) pairs the SDK calls. Adding a new endpoint to the SDK
// means adding it here and re-running the generator.
const OPERATIONS = [
    ['/api/v1/checkouts', 'post'],
    ['/api/v1/checkouts/{id}', 'get'],
];

// Inline property enums get a synthetic class name like
// "<SchemaName><PropertyName>". This map overrides those names so the public
// surface uses friendlier identifiers (e.g. CheckoutStatus, not
// CheckoutResponseDtoStatus).
const ENUM_NAME_OVERRIDES = [
    'CheckoutResponseDto.status' => 'CheckoutStatus',
];

$root = dirname(__DIR__);
$default_spec = $root . '/../zenobank/apps/api/openapi.json';
$spec_path = $argv[1] ?? $default_spec;
$out_dir = $root . '/src/Types/Generated';

if (!file_exists($spec_path)) {
    fwrite(STDERR, "Spec not found: {$spec_path}\n");
    exit(1);
}

$spec = json_decode((string) file_get_contents($spec_path), true);
if (!is_array($spec)) {
    fwrite(STDERR, "Failed to parse spec at {$spec_path}\n");
    exit(1);
}

$schemas = $spec['components']['schemas'] ?? [];

// Collect referenced schemas transitively from the allowlisted operations.
$referenced = [];
foreach (OPERATIONS as [$path, $method]) {
    $op = $spec['paths'][$path][$method] ?? null;
    if ($op === null) {
        fwrite(STDERR, "Operation not found in spec: {$method} {$path}\n");
        exit(1);
    }
    collect_refs($op, $referenced);
}

$prev = -1;
while ($prev !== count($referenced)) {
    $prev = count($referenced);
    foreach (array_keys($referenced) as $name) {
        if (isset($schemas[$name])) {
            collect_refs($schemas[$name], $referenced);
        }
    }
}

// Clear the output dir so renames/removals are reflected. Only remove .php
// files we recognise as generated.
if (is_dir($out_dir)) {
    foreach (glob($out_dir . '/*.php') ?: [] as $file) {
        $head = (string) file_get_contents($file, false, null, 0, 200);
        if (str_contains($head, 'AUTO-GENERATED')) {
            unlink($file);
        }
    }
} else {
    mkdir($out_dir, 0755, true);
}

// Collect webhook event type literals by walking spec.webhooks.*.post.requestBody
// -> schema -> `type` enum. Sourcing the strings from the DTOs themselves (rather
// than the top-level webhooks keys) keeps the enum tied to the actual payload
// shape.
$webhook_event_values = [];
foreach ($spec['webhooks'] ?? [] as $webhook) {
    $schema_ref = $webhook['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
    if ($schema_ref === null) {
        continue;
    }
    $event_schema = $schemas[ref_name($schema_ref)] ?? null;
    $type_enum = $event_schema['properties']['type']['enum'] ?? null;
    if (!is_array($type_enum)) {
        continue;
    }
    foreach ($type_enum as $value) {
        $webhook_event_values[(string) $value] = true;
    }
}

ksort($referenced);

foreach (array_keys($referenced) as $name) {
    $schema = $schemas[$name] ?? null;
    if ($schema === null) {
        fwrite(STDERR, "Referenced schema not in spec: {$name}\n");
        exit(1);
    }
    $code = render_schema($name, $schema);
    file_put_contents($out_dir . '/' . $name . '.php', $code);
    echo "wrote {$name}.php\n";
}

if (!empty($webhook_event_values)) {
    ksort($webhook_event_values);
    $synthetic_schema = [
        'type' => 'string',
        'enum' => array_keys($webhook_event_values),
    ];
    $code = render_enum('WebhookEventType', $synthetic_schema);
    file_put_contents($out_dir . '/WebhookEventType.php', $code);
    echo "wrote WebhookEventType.php\n";
}

echo "done.\n";

// ---------------------------------------------------------------------------

function collect_refs(mixed $node, array &$out): void
{
    if (!is_array($node)) {
        return;
    }
    if (isset($node['$ref']) && is_string($node['$ref'])) {
        if (preg_match('~^#/components/schemas/(\w+)$~', $node['$ref'], $m)) {
            $out[$m[1]] = true;
        }
    }
    foreach ($node as $value) {
        collect_refs($value, $out);
    }
}

function render_schema(string $name, array $schema): string
{
    // Inline enum -> emit a backed enum.
    if (isset($schema['enum']) && (($schema['type'] ?? 'string') === 'string')) {
        return render_enum($name, $schema);
    }

    if (($schema['type'] ?? null) !== 'object') {
        fwrite(STDERR, "Top-level schema {$name} is not an object — skipping\n");
        exit(1);
    }

    return render_object($name, $schema);
}

function render_enum(string $name, array $schema): string
{
    $cases = '';
    foreach ($schema['enum'] as $value) {
        $case_name = enum_case_name((string) $value);
        $cases .= "    case {$case_name} = " . var_export($value, true) . ";\n";
    }

    $ns = NAMESPACE_GENERATED;

    return <<<PHP
        <?php

        // THIS FILE IS AUTO-GENERATED BY scripts/generate-types.php
        // Do not edit manually. Run: composer generate

        declare(strict_types=1);

        namespace {$ns};

        enum {$name}: string
        {
        {$cases}}

        PHP;
}

function render_object(string $name, array $schema): string
{
    $properties = $schema['properties'] ?? [];
    $required = array_flip($schema['required'] ?? []);

    // Two passes so non-default params come before defaulted ones (PHP rule).
    $required_props = [];
    $optional_props = [];

    foreach ($properties as $camel_key => $prop_schema) {
        // Skip deprecated fields entirely — the SDK shouldn't expose them.
        if (!empty($prop_schema['deprecated'])) {
            continue;
        }
        $is_required = isset($required[$camel_key]);
        $is_nullable = !empty($prop_schema['nullable']);
        $optional = !$is_required || $is_nullable;
        if ($optional) {
            $optional_props[$camel_key] = $prop_schema;
        } else {
            $required_props[$camel_key] = $prop_schema;
        }
    }

    $constructor_params = [];
    $from_array_lines = [];
    $to_array_lines = [];

    $emit = function (string $camel_key, array $prop_schema, bool $optional) use (
        $name,
        &$constructor_params,
        &$from_array_lines,
        &$to_array_lines,
    ): void {
        $snake_key = camel_to_snake($camel_key);

        if (isset($prop_schema['enum']) && (($prop_schema['type'] ?? 'string') === 'string')) {
            $synthetic = $name . ucfirst($camel_key);
            $enum_class_name = ENUM_NAME_OVERRIDES["{$name}.{$camel_key}"] ?? $synthetic;
            emit_property_enum($enum_class_name, $prop_schema['enum']);
            $php_type = $enum_class_name;
            $from_expr = "{$enum_class_name}::from(\$data['{$camel_key}'])";
            $to_expr = "\$this->{$snake_key}->value";
            $is_passthrough = false;
        } elseif (isset($prop_schema['$ref'])) {
            $ref_name = ref_name($prop_schema['$ref']);
            $php_type = $ref_name;
            $from_expr = "{$ref_name}::from_array(\$data['{$camel_key}'])";
            $to_expr = "\$this->{$snake_key}->to_array()";
            $is_passthrough = false;
        } else {
            $php_type = openapi_type_to_php($prop_schema);
            $from_expr = "\$data['{$camel_key}']";
            $to_expr = "\$this->{$snake_key}";
            $is_passthrough = true;
        }

        $type_decl = ($optional ? '?' : '') . $php_type;
        $default = $optional ? ' = null' : '';
        $constructor_params[] = "        public readonly {$type_decl} \${$snake_key}{$default},";

        if ($optional) {
            $from_array_lines[] = "            {$snake_key}: isset(\$data['{$camel_key}']) ? {$from_expr} : null,";
        } else {
            $from_array_lines[] = "            {$snake_key}: {$from_expr},";
        }

        if ($optional && !$is_passthrough) {
            $to_array_lines[] = "            '{$camel_key}' => \$this->{$snake_key} === null ? null : {$to_expr},";
        } else {
            $to_array_lines[] = "            '{$camel_key}' => {$to_expr},";
        }
    };

    foreach ($required_props as $k => $p) {
        $emit($k, $p, false);
    }
    foreach ($optional_props as $k => $p) {
        $emit($k, $p, true);
    }

    $ns = NAMESPACE_GENERATED;
    $params_block = implode("\n", $constructor_params);
    $from_block = implode("\n", $from_array_lines);
    $to_block = implode("\n", $to_array_lines);

    return <<<PHP
        <?php

        // THIS FILE IS AUTO-GENERATED BY scripts/generate-types.php
        // Do not edit manually. Run: composer generate

        declare(strict_types=1);

        namespace {$ns};

        final class {$name}
        {
            public function __construct(
        {$params_block}
            ) {}

            /**
             * @param array<string, mixed> \$data
             */
            public static function from_array(array \$data): self
            {
                return new self(
        {$from_block}
                );
            }

            /**
             * @return array<string, mixed>
             */
            public function to_array(): array
            {
                return [
        {$to_block}
                ];
            }
        }

        PHP;
}

/**
 * Emit a property-level enum as its own file in the Generated directory.
 * Called from inside render_object as a side effect.
 */
function emit_property_enum(string $class_name, array $values): void
{
    global $out_dir;
    $cases = '';
    foreach ($values as $value) {
        $case_name = enum_case_name((string) $value);
        $cases .= "    case {$case_name} = " . var_export($value, true) . ";\n";
    }
    $ns = NAMESPACE_GENERATED;
    $code = <<<PHP
        <?php

        // THIS FILE IS AUTO-GENERATED BY scripts/generate-types.php
        // Do not edit manually. Run: composer generate

        declare(strict_types=1);

        namespace {$ns};

        enum {$class_name}: string
        {
        {$cases}}

        PHP;
    file_put_contents($out_dir . '/' . $class_name . '.php', $code);
}

function openapi_type_to_php(array $schema): string
{
    $type = $schema['type'] ?? 'string';
    return match ($type) {
        'string' => 'string',
        'integer' => 'int',
        'number' => 'float',
        'boolean' => 'bool',
        'array' => 'array',
        'object' => 'array',
        default => 'mixed',
    };
}

function ref_name(string $ref): string
{
    if (preg_match('~^#/components/schemas/(\w+)$~', $ref, $m)) {
        return $m[1];
    }
    fwrite(STDERR, "Unrecognised \$ref: {$ref}\n");
    exit(1);
}

function camel_to_snake(string $camel): string
{
    return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $camel));
}

function enum_case_name(string $value): string
{
    // 'checkout.completed' -> 'CHECKOUT_COMPLETED'; 'OPEN' -> 'OPEN'.
    $upper = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? $value);
    return ltrim($upper, '_');
}
