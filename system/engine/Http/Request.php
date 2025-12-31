<?php
namespace Engine\Http;

/**
 * HTTP Request
 *
 * Captures and normalizes the incoming HTTP request.
 * Handles method spoofing for PUT/PATCH/DELETE support via _method or headers.
 */
class Request
{
    /**
     * @var string HTTP method (GET, POST, etc.)
     */
    public string $method;

    /**
     * @var string Request URI path
     */
    public string $uri;

    /**
     * @var array Query parameters ($_GET)
     */
    public array $query;

    /**
     * @var array Request body parameters ($_POST or JSON)
     */
    public array $body;

    /**
     * @var array Uploaded files ($_FILES)
     */
    public array $files;

    /**
     * @var array HTTP headers
     */
    public array $headers;

    /**
     * Initialize the Request from global PHP variables.
     */
    public function __construct()
    {
        $this->query = $_GET ?? [];
        $this->body = $_POST ?? [];
        $this->files = $_FILES ?? [];
        $this->headers = $this->getAllHeaders();

        // Handle JSON body if applicable - BUT usually Middleware handles this now.
        // We will remove the redundant check here to avoid double reading input stream if not needed,
        // OR we can keep it but ensure it doesn't conflict.
        // Actually, php://input can be read multiple times? No, it's a stream.
        // It's better to let Middleware handle it OR handle it here only.
        // Since we have JsonBodyParser middleware, we should remove it here to avoid conflicts
        // or let the middleware populate it.
        // For now, let's remove it from constructor and rely on Middleware or lazy loading.
        // BUT, if the user doesn't use the Kernel/Middleware stack, this might break.
        // Let's check if we can peek.
        // Actually, reading php://input clears it for some SAPI? No, it's seekable usually.
        // Let's just remove it here and trust the Middleware to populate $this->body.
        // Wait, the constructor is called BEFORE middleware.
        // If we remove it here, $this->body will only have $_POST.
        // Then Middleware runs and updates $this->body. That is correct.

        // Determine method (with spoofing support)
        $this->method = $this->determineMethod();
        $this->uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    }

    /**
     * Determine the HTTP method, checking for spoofing.
     *
     * @return string
     */
    private function determineMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            // Check for _method input
            if (isset($this->body['_method'])) {
                return strtoupper($this->body['_method']);
            }

            // Check X-HTTP-Method-Override header
            $override = $this->header('X-HTTP-Method-Override');
            if ($override) {
                return strtoupper($override);
            }
        }

        return $method;
    }

    /**
     * Get a header value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        // Normalize keys to capitalize/standard format if needed,
        // but for now simple lookup. Keys in $this->headers usually depend on server.
        // Let's try case-insensitive lookup.
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $key) === 0) {
                return $v;
            }
        }
        return $default;
    }

    /**
     * Fetch all headers polyfill.
     *
     * @return array
     */
    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get an input value from body or query.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function input(?string $key = null, $default = null)
    {
        $all = array_merge($this->query, $this->body);

        if ($key === null) {
            return $all;
        }

        return $all[$key] ?? $default;
    }

    /**
     * Check if request expects JSON or sends JSON.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $ct = $this->header('Content-Type', '');
        return stripos($ct, 'application/json') !== false;
    }
}

