# Configuration

Open `config/luminous.php` after publishing it. The defaults work for most projects.

```php
return [
    // Whether Luminous is enabled at all.
    // Set LUMINOUS_ENABLED=false to disable docs entirely in production.
    'enabled' => env('LUMINOUS_ENABLED', true),

    // The URL path where your docs are served. Default: /docs
    'path' => env('LUMINOUS_PATH', 'docs'),

    // Your API info. These appear at the top of the Swagger UI.
    'info' => [
        'title'       => env('LUMINOUS_TITLE', config('app.name') . ' API'),
        'version'     => env('LUMINOUS_VERSION', '1.0.0'),
        'description' => env('LUMINOUS_DESCRIPTION', ''),
        'contact' => [
            'name'  => env('LUMINOUS_CONTACT_NAME', ''),
            'email' => env('LUMINOUS_CONTACT_EMAIL', ''),
            'url'   => env('LUMINOUS_CONTACT_URL', ''),
        ],
        'license' => [
            'name' => env('LUMINOUS_LICENSE_NAME', ''),
            'url'  => env('LUMINOUS_LICENSE_URL', ''),
        ],
    ],

    // The servers that appear in the Servers dropdown in Swagger UI.
    // Add one entry per environment you want consumers to be able to switch to.
    'servers' => [
        ['url' => env('APP_URL', 'http://localhost'), 'description' => 'Local'],
    ],

    // Middleware to protect the docs routes.
    // Set LUMINOUS_MIDDLEWARE=auth:sanctum to require authentication.
    'middleware' => env('LUMINOUS_MIDDLEWARE')
        ? explode(',', env('LUMINOUS_MIDDLEWARE'))
        : [],

    // Only document routes whose names match these patterns.
    // Supports exact names and wildcard suffixes using .* (e.g. 'api.*').
    // Empty means all routes are included.
    'include_routes' => [],

    // Exclude routes whose names match these patterns.
    // Supports exact names and wildcard suffixes using .* (e.g. 'luminous.*').
    'exclude_routes' => ['luminous.*'],

    // Set to true if your API wraps responses in {"data": ...}.
    // Laravel's JsonResource wraps by default, so set this to true unless you
    // have called JsonResource::withoutWrapping() somewhere in your app.
    'wrap_responses' => false,
    'response_wrapper_key' => 'data',

    // Whether to include the shared PaginationMeta schema in components.
    // This is referenced automatically when you use paginated: true on #[ApiResponse].
    'include_pagination_schema' => true,

    // Default security applied to every endpoint that has no explicit #[ApiSecurity].
    // An empty array means no default security.
    'default_security' => [],

    // Security scheme definitions. These go into components.securitySchemes.
    'security_schemes' => [],

    // OpenAPI 3.2.0 $self field. Declares the canonical URL of this spec document.
    // Leave null to omit the field.
    'self_url' => env('LUMINOUS_SELF_URL', null),

    // Cache settings. Always enable caching in production.
    'cache' => [
        'enabled' => env('LUMINOUS_CACHE', true),
        'store'   => env('LUMINOUS_CACHE_STORE', null),
        'key'     => env('LUMINOUS_CACHE_KEY', 'luminous:spec'),
        'ttl'     => env('LUMINOUS_CACHE_TTL', 3600),
    ],
];
```

---

## Common .env settings

```env
# Basic info
LUMINOUS_TITLE="Payments API"
LUMINOUS_VERSION="2.0.0"
LUMINOUS_DESCRIPTION="Handles payment creation and lifecycle management."

# Protect docs in production (recommended)
LUMINOUS_MIDDLEWARE=auth:sanctum

# Or hide docs entirely and export to a static file instead
LUMINOUS_ENABLED=false

# Cache
LUMINOUS_CACHE=true
LUMINOUS_CACHE_TTL=3600
```

---

## Filtering routes

Use `include_routes` to show only specific routes, or `exclude_routes` to hide routes
you do not want in the docs. Both accept exact route names and wildcard patterns
using `.*`.

```php
// Only document routes under the "api.*" name prefix
'include_routes' => ['api.*'],

// Exclude internal and admin routes
'exclude_routes' => ['luminous.*', 'admin.*', 'internal.*'],

// Exclude a specific route by exact name
'exclude_routes' => ['health.check'],
```

---

## Multiple servers

List every server your API runs on. Swagger UI shows a dropdown so consumers can
switch between them.

```php
'servers' => [
    ['url' => 'https://api.example.com',         'description' => 'Production'],
    ['url' => 'https://staging.api.example.com', 'description' => 'Staging'],
    ['url' => 'http://localhost',                 'description' => 'Local'],
],
```

---

## Document identity

`self_url` sets the OpenAPI 3.2.0 `$self` field, which declares the canonical URL of
your spec document. Most tools do not require it, but it helps with relative reference
resolution in multi-file API descriptions.

```env
LUMINOUS_SELF_URL=https://api.example.com/docs/openapi.json
```

Leave it unset (the default) to omit the field entirely.

---

## Shared schemas

`shared_schemas` registers named schemas in `components.schemas` before any routes are
processed. The default includes `ErrorResponse`, which is available as a `$ref` for
any `#[ApiResponse]` that returns an error shape.

```php
'shared_schemas' => [
    'ErrorResponse' => [
        'type' => 'object',
        'properties' => [
            'code'       => ['type' => 'string'],
            'message'    => ['type' => 'string'],
            'request_id' => ['type' => 'string'],
            'timestamp'  => ['type' => 'string', 'format' => 'date-time'],
            'details'    => ['type' => 'object'],
        ],
    ],
],
```

You can add your own schemas, override the defaults, or remove them by setting the
entry to `null`. Any schema you add here is available as a `$ref` across your spec.

---

## Security schemes

Define your schemes here. They are referenced by name in `#[ApiSecurity]` attributes
on your controllers. See [Security](security.md) for full details.

```php
'security_schemes' => [
    'bearerAuth' => [
        'type'         => 'http',
        'scheme'       => 'bearer',
        'bearerFormat' => 'JWT',
    ],
    'apiKey' => [
        'type' => 'apiKey',
        'in'   => 'header',
        'name' => 'X-API-Key',
    ],
],
```
