<?php

namespace App\Controllers;

class HomeController
{
    public function index(): string
    {
        return view('home', ['title' => 'Welcome', 'message' => 'Your custom PHP MVC is running'], 'layouts/main');
    }

    public function phpinfo(): string
    {
        return view('phpinfo', ['title' => 'PHP Info', 'content' => ''], 'layouts/main');
    }
}
