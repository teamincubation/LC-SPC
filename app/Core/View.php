<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * View Rendering Engine
 * Renders PHP template files with layout inheritance, scoped variables, and output escaping.
 */
class View
{
    private static string $viewsPath;

    public static function getViewsPath(): string
    {
        if (!isset(self::$viewsPath)) {
            self::$viewsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        }
        return self::$viewsPath;
    }

    /**
     * Render a view file with optional layout.
     *
     * @param string $view View name, e.g. 'home/index' or 'errors/404'
     * @param array $data Variables to expose inside the view
     * @param string|null $layout Layout name, e.g. 'layouts/main', or null for raw view
     * @return string Rendered HTML output
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $viewFile = self::resolveViewFile($view);

        // Render the view content into an output buffer
        $content = self::renderFile($viewFile, $data);

        // If a layout is specified, wrap the view content inside the layout
        if ($layout !== null) {
            $layoutFile = self::resolveViewFile($layout);
            $layoutData = array_merge($data, ['content' => $content]);
            return self::renderFile($layoutFile, $layoutData);
        }

        return $content;
    }

    /**
     * Render a sub-view / partial without any layout wrapping.
     */
    public static function partial(string $view, array $data = []): string
    {
        return self::render($view, $data, null);
    }

    /**
     * Locate the file path for a view identifier.
     */
    private static function resolveViewFile(string $view): string
    {
        $viewPath = rtrim(self::getViewsPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new RuntimeException("View file not found: [{$viewPath}]");
        }

        return $viewPath;
    }

    /**
     * Evaluate a template file in an isolated variable scope.
     */
    private static function renderFile(string $filePath, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $filePath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
