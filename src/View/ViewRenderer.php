<?php
namespace App\View;

class ViewRenderer
{
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/') . '/';
    }

    /**
     * Renders a view
     *
     * @param string $view - The view file to render
     * @param array $data - Data to pass to the view
     * @param string $layout - The layout file (default: 'layouts/main.php')
     * @return void
     * @throws \RuntimeException If view or layout file is not found
     */
    public function render(string $view, array $data = [], string $layout = 'layouts/main.php'): string
    {
        $this->basePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->basePath);
        $view           = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view);
        $layout         = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layout);

        $viewPath   = $this->basePath . $view;
        $layoutPath = $this->basePath . $layout;

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View file not found: {$viewPath}");
        }
        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout file not found: {$layoutPath}");
        }

        extract($data);

        ob_start();
        require $layoutPath;
        return ob_get_clean();
    }
}