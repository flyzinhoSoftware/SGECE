<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        $basePath = realpath(__DIR__ . '/../Views');
        $filePath = realpath(__DIR__ . '/../Views/' . $view . '.php');

        if (
            $basePath === false ||
            $filePath === false ||
            !str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR) ||
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