<?php

namespace Native\Mobile;

class PendingAlert
{
    protected ?string $id = null;
    protected ?string $eventClass = null;
    protected bool $shown = false;

    public function __construct(
        protected string $title,
        protected string $message,
        protected array $buttons = []
    ) {}

    /**
     * Set a unique identifier for this alert.
     */
    public function id(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the alert's unique identifier.
     */
    public function getId(): string
    {
        if ($this->id === null) {
            $this->id = uniqid('alert_');
        }
        return $this->id;
    }

    /**
     * Set a custom event class to dispatch when a button is pressed.
     */
    public function event(string $eventClass): self
    {
        $this->eventClass = $eventClass;
        return $this;
    }

    /**
     * Display the alert.
     */
    public function show(): void
    {
        if ($this->shown) {
            return;
        }

        $this->shown = true;

        Native::call('Dialog.Alert', [
            'title' => $this->title,
            'message' => $this->message,
            'buttons' => $this->buttons,
            'id' => $this->getId(),
            'event' => $this->eventClass,
        ]);
    }
}
