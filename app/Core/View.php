<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        $viewsDirectory = realpath(__DIR__ . '/../Views');
        $filePath = realpath(__DIR__ . '/../Views/' . $view . '.php');

        if (
            $viewsDirectory === false ||
            $filePath === false ||
            !str_starts_with($filePath, $viewsDirectory . DIRECTORY_SEPARATOR) ||
            !is_file($filePath)
        ) {
            throw new \RuntimeException(
                'A página solicitada não foi encontrada.'
            );
        }

        extract($data, EXTR_SKIP);

        require $filePath;
    }
}