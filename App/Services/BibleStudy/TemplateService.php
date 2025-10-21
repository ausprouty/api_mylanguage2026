<?php

namespace App\Services\BibleStudy;


use App\Services\LoggerService;
use App\Configuration\Config;

class TemplateService
{
    
    public function getStudyTemplateName($format, $study, $render): string {
        if ($format == 'json'){
            return 'blank.twig';
        }
        $name = '';
    
        // Determine the format type
        if ($format == 'monolingual') {
            $name .= 'monolingual';
        } else {
            $name .= 'bilingual';
        }

        $name .= ucfirst($study) .'Study';
    
        // Capitalize the first letter of $render and append it
        $name .= ucfirst($render) . '.twig';
        return $name;
    }
    
    
    public function getTemplate(string $template): string
    {
        try {
          

            // Get the directory path
            $dir = Config::getDir('resources.templates');
            if (!$dir) {
                $message = "Templates directory not configured.";
                LoggerService::logError('getTemplate-41',$message);
                throw new \RuntimeException($message);
            }

            // Ensure the directory path ends with a slash
            $dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            // Sanitize the template name to prevent directory traversal attacks
            $sanitizedTemplate = basename($template);

            // Restrict to .twig files only
            if (pathinfo($sanitizedTemplate, PATHINFO_EXTENSION) !== 'twig') {
                $message = "Invalid file type requested: $sanitizedTemplate.";
                LoggerService::logError('getTemplate-55', $message);
                throw new \RuntimeException($message);
            }

            // Construct the full path
            $templatePath = $dir . $sanitizedTemplate;

            // Check if the file exists
            if (!file_exists($templatePath)) {
                $message = "Template file not found: $templatePath.";
                LoggerService::logError('getTemplate-65', $message);
                throw new \RuntimeException($message);
            }

            // Read the file contents
            $file = file_get_contents($templatePath);
            if ($file === false) {
                $message = "Failed to read template file: $templatePath.";
                LoggerService::logError('getTemplate-73', $message);
                throw new \RuntimeException($message);
            }

            LoggerService::logInfo('getTemplate-77', "Template file successfully retrieved: $templatePath");
            return $file;
        } catch (\Exception $e) {
            // Log the exception
            LoggerService::logError('getTemplate-81', $e->getMessage());
            // Optionally rethrow the exception
            throw $e;
        }
    }
}
