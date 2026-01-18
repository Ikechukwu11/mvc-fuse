<?php

namespace Engine\Support;

use Native\Mobile\Facades\App as AppFacade;
use Stringable;

class ViewResponse implements Stringable
{
    /**
     * The rendered content.
     *
     * @var string
     */
    protected string $content;

    /**
     * Create a new ViewResponse instance.
     *
     * @param string $content
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    /**
     * Set the status bar color and style.
     *
     * @param string|null $color Hex color
     * @param string|null $style 'light', 'dark', 'auto'
     * @param bool $overlay Whether to overlay content
     * @return $this
     */
    public function withStatusBar(?string $color = null, ?string $style = null, bool $overlay = true): self
    {
        if (class_exists(AppFacade::class)) {
            AppFacade::setStatusBar($color, $style, $overlay);
        }
        return $this;
    }

    /**
     * Get the content as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->content;
    }
}
