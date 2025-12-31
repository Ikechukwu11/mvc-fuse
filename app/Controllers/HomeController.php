<?php
namespace App\Controllers;

class HomeController
{
    public function index(): string
    {
        return view('home', ['title' => 'Welcome', 'message' => 'Your custom PHP MVC is running'], 'layouts/main');
    }
}
