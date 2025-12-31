<?php
namespace Engine\Http;

/**
 * Class Response
 *
 * Represents an HTTP response.
 * Handles status codes, headers, and body content (JSON/HTML).
 */
class Response
{
    /**
     * @var int HTTP status code
     */
    protected int $status = 200;

    /**
     * @var array<string, string> HTTP headers
     */
    protected array $headers = [];

    /**
     * @var string Response body content
     */
    protected string $body = '';

    /**
     * Set the HTTP status code.
     *
     * @param int $code
     * @return self
     */
    public function setStatus(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    /**
     * Set a header value.
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set a JSON response.
     *
     * Sets Content-Type to application/json and encodes data.
     *
     * @param mixed $data Data to encode
     * @param int $status HTTP status code
     * @return self
     */
    public function json($data, int $status = 200): self
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'application/json');
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

    /**
     * Set an HTML response.
     *
     * Sets Content-Type to text/html.
     *
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @return self
     */
    public function html(string $html, int $status = 200): self
    {
        $this->setStatus($status);
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->body = $html;
        return $this;
    }

    /**
     * Send the response to the client.
     *
     * Sends headers and outputs the body.
     *
     * @return void
     */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header($k . ': ' . $v);
        }
        echo $this->body;
    }
}

