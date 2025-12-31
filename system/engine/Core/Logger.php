<?php

namespace Engine\Core;

/**
 * Class Logger
 *
 * Simple file-based logger.
 * Writes logs to daily files in the specified directory.
 */
class Logger
{
    /**
     * @var string Directory where logs are stored
     */
    protected string $dir;

    /**
     * Create a new Logger instance.
     *
     * @param string $dir Log directory path
     */
    public function __construct(string $dir = __DIR__ . '/../../../storage/logs')
    {
        $this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0777, true);
        }
    }

    /**
     * Log a message with a specific level.
     *
     * @param string $level Log level (e.g., 'error', 'info')
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $file = $this->dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context) : ''
        );
        @file_put_contents($file, $line, FILE_APPEND);
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}
