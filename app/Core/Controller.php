<?php

namespace App\Core;

class Controller
{
    /**
     * Carregar uma View.
     */
    protected function view(
        string $view,
        array $data = []
    ): void {
        View::render($view, $data);
    }
}
