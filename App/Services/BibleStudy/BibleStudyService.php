<?php

namespace App\Services\BibleStudy;

use App\Renderers\RendererFactory;
use App\Repositories\LanguageRepository;
use App\Services\ResourceStorageService;
use App\Services\Database\DatabaseService;
use App\Traits\BibleStudyFileNamingTrait;
use InvalidArgumentException;
use DI\Container;

class BibleStudyService
{
    use BibleStudyFileNamingTrait;

    private RendererFactory $rendererFactory;
    private LanguageRepository $languageRepository;
    private DatabaseService $databaseService;
    private Container $container;

    public function __construct(
        RendererFactory $rendererFactory,
        LanguageRepository $languageRepository,
        DatabaseService  $databaseService,
        Container $container,
    ) {
        $this->rendererFactory = $rendererFactory;
        $this->languageRepository = $languageRepository;
        $this->databaseService = $databaseService;
        $this->container = $container;
    }

    public function getStudy(
        string $study,
        string $format,
        string $session,
        string $languageCodeHL1,
        ?string $languageCodeHL2 = null
    ): string {
        $filename = $this->getFileName(
            $study, $format, $session, $languageCodeHL1, $languageCodeHL2
        );
        $storagePath = $this->getStoragePath($study, $format);
        $storageService = new ResourceStorageService($storagePath);
        $file = null; //TODO: go back and see how this used to be done
        return $file 
            ? $file 
            : $this->createStudy(
                $study, $format, $session, $languageCodeHL1, $languageCodeHL2
            );
    }

    private function createStudy(
        string $study,
        string $format,
        string $session,
        string $languageCodeHL1,
        ?string $languageCodeHL2 = null
    ): string {
        // Map study types to their corresponding service classes
        $serviceClassMap = [
                'monolingual' => MonolingualStudyService::class,
                'bilingual' => BilingualStudyService::class
        ];
    
        // Validate the study type
        if (!isset($serviceClassMap[$study])) {
            throw new InvalidArgumentException("Unsupported study type: $study");
        }
    
        // Determine the appropriate service class based on language codes
        $serviceType = $languageCodeHL2 ? 'bilingual' : 'monolingual';
        $serviceClass = $serviceClassMap[$study][$serviceType];
    
        // Resolve the service class from the container
        $studyService = $this->container->get($serviceClass);
    
        // Call the service's generate method
        return $studyService->generate(
            $study, $format, $session, $languageCodeHL1, $languageCodeHL2
        );
    }
    
}
