<?php

use App\Renderers\RendererFactory;
use App\Renderers\HtmlRenderer;
use App\Renderers\PdfRenderer;

// Add classes that need TwigService    
$requiresTwigService = [
    'App\Services\VideoService',
    'App\Services\BibleStudy\AbstractBibleStudyService',
    'App\Services\BibleStudy\MonolingualStudyService',
    'App\Services\BibleStudy\BilingualStudyService']; 

$directory = __DIR__ . '/../'; // Adjust the path as needed
$progressFile = __DIR__ . '/php-di-progress.json'; // File to save progress
$outputFile = __DIR__ . '/../Configuration/di/di-all.php'; // File to save the final definitions

require __DIR__ . '/../../Vendor/autoload.php';

$definitions = []; // Initialize an empty array to hold definitions

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
        $namespace = $matches[1];
    } else {
        $namespace = null;
    }

    if (preg_match('/class\s+(\w+)/', $content, $matches)) {
        $class = $namespace ? $namespace . '\\' . $matches[1] : $matches[1];

        if (!class_exists($class)) {
            echo "Skipping: Class not found -> $class\n";
            continue;
        }

        try {
            $reflector = new ReflectionClass($class);
            if ($reflector->isInstantiable()) {
                $constructor = $reflector->getConstructor();
                if ($constructor) {
                    $parameters = $constructor->getParameters();
                    $dependencies = [];

                    foreach ($parameters as $param) {
                        $type = $param->getType();
                        if ($type && !$type->isBuiltin()) {
                            $dependencies[$param->getName()] = (string) $type;
                        } elseif ($param->getType() && (string)$param->getType() === 'array') {
                            // Special handling for associative arrays like RendererFactory
                            if ($reflector->getName() === RendererFactory::class) {
                                $dependencies[$param->getName()] = [
                                    'html' => HtmlRenderer::class,
                                    'pdf' => PdfRenderer::class,
                                ];
                            }
                        }
                    }
                    //add Twig Service
                    if (in_array($reflector->getName(), $requiresTwigService, true) 
                        && !in_array('App\Services\TwigService', $dependencies, true)) {
                        $dependencies['twigService'] = 'App\Services\TwigService';
                    }

                    $definitions[$class] = $dependencies;
                } else {
                    $definitions[$class] = [];
                }
            }
        } catch (Exception $e) {
            echo "Error processing $class: " . $e->getMessage() . "\n";
            $definitions[$class] = null;
        }

        echo "Processed: $class\n";
    }
}

// Write final PHP-DI configuration file for Factories
$phpDiConfig = "<?php\nreturn [\n";
foreach ($definitions as $class => $dependencies) {
    if ($class === 'App\\Renderers\\RendererFactory') {
        $phpDiConfig .= "    '$class' => DI\\create()\n        ->constructor([\n";
        foreach ($dependencies as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $format => $rendererClass) {
                    $phpDiConfig .= "            '$format' => DI\\get('$rendererClass'),\n";
                }
            }
        }
        $phpDiConfig .= "        ]),\n";
    } else {
        $phpDiConfig .= "    '$class' => DI\\autowire()->constructor(\n";
        if (!empty($dependencies)) {
            $phpDiConfig .= implode(",\n", array_map(fn($dep) => "        DI\\get('$dep')", $dependencies));
        }
        $phpDiConfig .= "\n    ),\n";
    }
}
$phpDiConfig .= "];\n";


file_put_contents($outputFile, $phpDiConfig);

echo "PHP-DI configuration file generated: $outputFile\n";
