<?php

namespace App\Renderers;

use InvalidArgumentException;

class RendererFactory {
    private $renderers;

    /**
     * Constructor to inject available renderers.
     *
     * @param array $renderers An associative array of renderers keyed by format.
     */
    public function __construct(array $renderers) {
        foreach ($renderers as $key => $renderer) {
            if (!$renderer instanceof RendererInterface) {
                throw new InvalidArgumentException("Renderer for '$key' must implement RendererInterface.");
            }
        }
        $this->renderers = $renderers;
    }

    /**
     * Get the renderer for the specified format.
     *
     * @param string $format The desired output format (e.g., 'html', 'pdf').
     * @return RendererInterface
     * @throws InvalidArgumentException if the format is not supported.
     */
    public function getRenderer(string $format): RendererInterface {
        return $this->renderers[$format] 
            ?? throw new InvalidArgumentException("Unknown format: $format");
    }
}
