<?php
namespace App\Controllers;

class DemoController
{
    public function secret(): string
    {
        return view('secret', ['title' => 'Secret Area'], 'layouts/main');
    }
}

