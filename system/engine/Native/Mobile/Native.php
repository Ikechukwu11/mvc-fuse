<?php

namespace Native\Mobile;

class Native
{
    protected static array $queue = [];

    /**
     * Call a native function.
     *
     * @param string $event
     * @param array $payload
     * @return void
     */
    public static function call(string $event, array $payload = []): void
    {
        self::$queue[] = [
            'name' => $event,
            'detail' => $payload
        ];
    }

    /**
     * Get and clear the queue of native calls.
     *
     * @return array
     */
    public static function flush(): array
    {
        $queue = self::$queue;
        // Debug logging for flushed events
        if (!empty($queue)) {
            $log = new \Engine\Core\Logger();
            $log->info("Fuse Flushed Native Events", $queue);
        }
        self::$queue = [];
        return $queue;
    }
}
