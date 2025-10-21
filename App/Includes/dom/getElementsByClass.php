<?php
// from https://stackoverflow.com/questions/6366351/getting-dom-elements-by-classname/31616848#31616848

/**
 * Retrieves an array of DOM elements by tag name and class name.
 *
 * This function searches within a specified parent DOM node for child elements
 * that match a given tag name and contain the specified class name.
 * It returns all matching nodes as an array.
 *
 * Note: This function is useful in DOM manipulation scenarios where DOMDocument
 * does not natively support class-based searches, as it allows filtering based on class name.
 *
 * @param DOMNode $parentNode The parent DOM node to search within.
 * @param string $tagName The tag name of elements to search for (e.g., 'div', 'span').
 * @param string $className The class name to search for within elements.
 * @return array An array of DOM elements matching the specified tag name and class name.
 */
function getElementsByClass(&$parentNode, $tagName, $className) {
    $nodes = [];

    // Retrieve all child nodes with the specified tag name
    $childNodeList = $parentNode->getElementsByTagName($tagName);
    for ($i = 0; $i < $childNodeList->length; $i++) {
        $temp = $childNodeList->item($i);
        // Check if the class attribute contains the specified class name (case-insensitive)
        if (stripos($temp->getAttribute('class'), $className) !== false) {
            $nodes[] = $temp;
        }
    }
    return $nodes;
}
