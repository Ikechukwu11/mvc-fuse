<?php

namespace App\Fuse;

use Engine\Fuse\Component;

abstract class BaseFuse extends Component
{
  // Public properties automatically exposed to view
  public $current_route;
  public $randomColor;

  public function __construct()
  {
    parent::__construct();
    $this->boot();
    $this->mount();
  }

  /**
   * Boot method: runs before mount, ideal for initial setup
   */
  public function boot()
  {
    $this->randomColor = $GLOBALS['__randomColor'];
    $this->current_route = current_route_path();
    //$this->useStatusBar($this->randomColor, 'auto');
  }

  // /**
  //  * Mount method: runs after boot, child components can override
  //  */
  public function mount(): void
  {
    // default: do nothing
  }

  /**
   * Gather all public properties for view
   */
  protected function getViewData(): array
  {
    return get_object_vars($this);
  }

  /**
   * Render helper: automatically injects public properties into the view
   *
   * @param string $view Blade view
   * @param string|null $layout Optional layout
   */
  public function renderView(string $view, ?string $layout = null): string
  {
    return view($view, $this->getViewData(), $layout);
  }
}
