<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable / Disable
    |--------------------------------------------------------------------------
    |
    | Set LARADOC_ENABLED=false in production to hide the docs entirely.
    | All three routes (/docs, /docs/openapi.json, /docs/openapi.yaml)
    | return 404 when disabled.
    |
    */
    'enabled' => env('LARADOC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | URL Path
    |--------------------------------------------------------------------------
    |
    | The URL prefix under which the docs are served.
    | Defaults to "docs", so the UI is at /docs and the spec at /docs/openapi.json.
    |
    */
    'path' => env('LARADOC_PATH', 'docs'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all three docs routes. Accepts a comma-separated
    | list of middleware names in the environment variable.
    |
    | Example: LARADOC_MIDDLEWARE=auth,throttle:60,1
    |
    | Leave blank to allow public access to the docs.
    |
    */
    'middleware' => env('LARADOC_MIDDLEWARE')
        ? array_map('trim', explode(',', env('LARADOC_MIDDLEWARE')))
        : [],

    /*
    |--------------------------------------------------------------------------
    | API Info
    |--------------------------------------------------------------------------
    |
    | Appears in the OpenAPI "info" object and in the Swagger UI page title.
    |
    */
    'info' => [
        'title' => env('LARADOC_TITLE', 'Luminous API'),
        'version' => env('LARADOC_VERSION', '1.0.0'),
        'description' => env('LARADOC_DESCRIPTION', ''),
        'contact' => [
            'name' => env('LARADOC_CONTACT_NAME', ''),
            'email' => env('LARADOC_CONTACT_EMAIL', ''),
            'url' => env('LARADOC_CONTACT_URL', ''),
        ],
        'license' => [
            'name' => env('LARADOC_LICENSE', ''),
            'url' => env('LARADOC_LICENSE_URL', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | The list of servers shown in Swagger UI's server selector.
    | Defaults to APP_URL. Add staging or production servers here.
    |
    */
    'servers' => [
        ['url' => env('APP_URL', 'http://localhost'), 'description' => 'Local'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Inclusion Filter
    |--------------------------------------------------------------------------
    |
    | When this array is empty, all routes are included except those matched
    | by "exclude_routes" below.
    |
    | When non-empty, only routes whose name matches one of the listed patterns
    | are included in the spec. Patterns are matched against the route name.
    | A trailing ".*" wildcard matches any suffix.
    |
    | Example: include only API v1 routes
    |
    |   'include_routes' => ['api.v1.*'],
    |
    | Route names that do not match any pattern are excluded regardless of the
    | "exclude_routes" list.
    |
    */
    'include_routes' => [],

    /*
    |--------------------------------------------------------------------------
    | Route Exclusion Filter
    |--------------------------------------------------------------------------
    |
    | Route name patterns to exclude from the generated spec. Evaluated after
    | "include_routes". A trailing ".*" wildcard matches any suffix.
    |
    | The package's own routes (luminous.*) are always excluded so they do
    | not appear as documented endpoints.
    |
    */
    'exclude_routes' => [
        'luminous.*', 'telescope.*', 'horizon.*',
        'debugbar.*', 'sanctum.*', 'ignition.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Schemes
    |--------------------------------------------------------------------------
    |
    | Named security schemes that can be referenced by #[ApiSecurity] attributes.
    | These appear in the OpenAPI "components.securitySchemes" object.
    |
    | Supported types: http, apiKey, oauth2, openIdConnect
    |
    */
    'security_schemes' => [
        'bearerAuth' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'Authorization',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Security
    |--------------------------------------------------------------------------
    |
    | Applied to every operation unless overridden by #[ApiSecurity] or
    | #[ApiNoSecurity] on the controller or method.
    |
    | Each entry is a security requirement object as defined in the OpenAPI spec.
    | Example: require bearerAuth on all routes by default
    |
    |   'default_security' => [['bearerAuth' => []]],
    |
    */
    'default_security' => [],

    /*
    |--------------------------------------------------------------------------
    | Response Envelope Wrapping
    |--------------------------------------------------------------------------
    |
    | When true, every response schema is wrapped in a top-level object using
    | the key defined by "response_wrapper_key".
    |
    | Enable this if your API always returns {"data": ...} around its payloads.
    |
    | For paginated responses (#[ApiResponse(paginated: true)]), a "pagination"
    | key is added alongside "data" when "include_pagination_schema" is true.
    |
    */
    'wrap_responses' => false,
    'response_wrapper_key' => 'data',
    'include_pagination_schema' => true,

    /*
    |--------------------------------------------------------------------------
    | Spec Cache
    |--------------------------------------------------------------------------
    |
    | Caching avoids re-generating the spec on every request. Recommended for
    | production. Flush the cache with: php artisan luminous:generate --force
    |
    | "store" accepts any cache store defined in config/cache.php.
    | Leave null to use the default store.
    |
    */
    'cache' => [
        'enabled' => env('LARADOC_CACHE', false),
        'ttl' => env('LARADOC_CACHE_TTL', 3600),
        'key' => 'luminous:spec',
        'store' => env('LARADOC_CACHE_STORE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Swagger UI Options
    |--------------------------------------------------------------------------
    |
    | Controls the behaviour of the embedded Swagger UI at /docs.
    |
    | "cdn.swagger_ui" is pinned to a specific version. Do not use @latest
    | as breaking changes in a CDN update could silently break the UI.
    |
    */
    'ui' => [
        'persist_authorization' => true,
        'display_request_duration' => true,
        'default_models_expand_depth' => 1,
        'syntax_highlight_theme' => 'monokai',
        'try_it_out_enabled' => true,
        'cdn' => [
            'swagger_ui' => 'https://unpkg.com/swagger-ui-dist@5.18.2',
        ],
    ],

];
