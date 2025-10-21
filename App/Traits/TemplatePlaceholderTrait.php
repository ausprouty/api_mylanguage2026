<?php

namespace App\Traits;

/**
 * Trait TemplatePlaceholderTrait
 *
 * Provides a utility method for replacing placeholders within a template string.
 * This trait enables classes to replace multiple placeholders in a single operation,
 * using an associative array of placeholder-value pairs. It is designed for flexible
 * template customization, making it easy to populate templates with dynamic content.
 *
 * This trait is particularly useful in controllers and services that work with
 * HTML or text templates containing placeholders for content insertion.
 *
 * @package App\Traits
 */
trait TemplatePlaceholderTrait
{
    /**
     * Replaces placeholders in the provided template string.
     *
     * This method accepts an associative array where the keys are placeholders
     * (e.g., `{{Title}}`) and the values are the replacements. The method then
     * iterates through each placeholder-value pair, performing a `str_replace` to
     * populate the template string with the corresponding values.
     *
     * @param array $placeholders An associative array of placeholders and their replacements.
     *                            Example: ['{{Title}}' => 'My Title', '{{Content}}' => 'My Content']
     * @param string &$template   The template string in which placeholders are replaced. This parameter
     *                            is passed by reference, allowing the method to directly modify the original template.
     */
    protected function replacePlaceholders(array $placeholders, string &$template): void {
        foreach ($placeholders as $key => $value) {
            $template = str_replace($key, $value, $template);
        }
    }
}
