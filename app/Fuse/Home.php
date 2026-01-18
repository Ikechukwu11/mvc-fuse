<?php

namespace App\Fuse;

class Home extends BaseFuse
{
  public $statusBarColor;

  // public function mount()
  // {
  //   $this->statusBarColor = $this->randomColor;
  // }

  public function render()
  {
    switch ($this->current_route) {
      case '/phpinfo':
      case 'info':
        $view = 'phpinfo';
        $title = 'PHP Info';
        $message = '';
        break;
      default:
        $view = 'home';
        $title = 'Welcome';
        $message = 'Your custom PHP MVC is running';
    }

    return view(
      $view,
      array_merge(
        $this->getViewData(),
        [
          'title' => $title,
          'message' => $message
        ]
      ),
      'layouts/main'
    );
  }
}
