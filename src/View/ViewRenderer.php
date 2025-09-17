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
    public function render(string $view, array $data = [], string $layout = 'layouts/main.php'): void
    {
        //Normalize path
    // echo "Original base path: " . $this->basePath . "<br>";
    // echo "Original view: " . $view . "<br>";

   // Normalize separators in view and basePath
    $this->basePath = str_replace('/', DIRECTORY_SEPARATOR, $this->basePath);
    $this->basePath = str_replace('\\', DIRECTORY_SEPARATOR, $this->basePath); 

    $view = str_replace('/', DIRECTORY_SEPARATOR, $view);
    $view = str_replace('\\', DIRECTORY_SEPARATOR, $view);

    $layout = str_replace('/', DIRECTORY_SEPARATOR, $layout);
    $layout = str_replace('\\', DIRECTORY_SEPARATOR, $layout);

    // echo "Normalized base path: " . $this->basePath . "<br>";
    // echo "Normalized view: " . $view . "<br>";

    $viewPath = $this->basePath . $view;
    // echo "Final view path: " . $viewPath . "<br>";

    if (!file_exists($viewPath)) {
        die("FATAL: View file not found at: " . $viewPath);
    }

        $viewPath = $this->basePath . $view;
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View file not found: {$viewPath}");
        }

        $layoutPath = $this->basePath . $layout;
        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout file not found: {$layoutPath}");
        }

        // Extract data into variables
        extract($data);

        // Include the layout, which will include the view
        require $layoutPath;
    }
}