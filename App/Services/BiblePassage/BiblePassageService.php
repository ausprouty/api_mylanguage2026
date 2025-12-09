<?php

namespace App\Services\BiblePassage;

use App\Factories\PassageFactory;
use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageReferenceModel;
use App\Models\Bible\PassageModel;
use App\Services\Database\DatabaseService;
use App\Repositories\PassageRepository;
use App\Services\BiblePassage\AbstractBiblePassageService;
use App\Services\BiblePassage\BibleBrainPassageService;
use App\Services\BiblePassage\BibleGatewayPassageService;
use App\Services\BiblePassage\BibleWordPassageService;
use App\Services\BiblePassage\YouVersionPassageService;
use App\Services\LoggerService;
use Psr\Container\ContainerInterface;

/**
 * Service to manage Bible passages. This class checks if a passage exists in the
 * database and, if not, determines the appropriate service to retrieve and store
 * the passage from an external source.
 */
final class BiblePassageService
{
    /** @var DatabaseService The database service for interacting with the database. */
    private $databaseService;

    /** @var BibleModel The Bible model instance. */
    private $bibleModel;

    /** @var PassageReferenceModel The passage reference model instance. */
    private $passageReferenceModel;

    /** @var PassageRepository The repository for handling passage data. */
    private $passageRepository;

    /** @var string The unique Bible Passage ID (BPID). */
    private $bpid;

    /** PSR-11 container for building concrete passage services */
    private ?ContainerInterface $container = null;

    /**
     * Constructor to initialize dependencies.
     *
     * @param DatabaseService $databaseService The database service instance.
     * @param PassageRepository $passageRepository The passage repository instance.
     */
    public function __construct(
        ContainerInterface $container,
        DatabaseService $databaseService,
        PassageRepository $passageRepository
    ) {
        $this->databaseService = $databaseService;
        $this->passageRepository = $passageRepository;
        $this->container = $container;
    }

    /**
     * Retrieves a Bible passage. Checks the database first, and if the passage is
     * not found, it uses the appropriate service to fetch and store the passage.
     *
     * @param BibleModel $bible The Bible model instance.
     * @param PassageReferenceModel $passageReferenceModel The passage reference model.
     * @return Models/Bible/PassageModel properties of the retrieved passage.
     */
    public function getPassage(
        BibleModel $bibleModel, 
        PassageReferenceModel $passageReferenceModel) :PassageModel
    {
        $this->bibleModel = $bibleModel;
        $this->passageReferenceModel = $passageReferenceModel;

        // Generate the Bible Passage ID (BPID).
        $this->bpid = $this->bibleModel->getBid() . '-' . $this->passageReferenceModel->getPassageID();

        // Check if the passage is in the database or fetch it externally.
        if ($this->inDatabase($this->bpid)) {
            $passageModel = $this->retrieveStoredData($this->bpid);
        } else {
            $passageModel = $this->retrieveExternalPassage( $this->passageReferenceModel);
        }

        // Return the passage properties.
        return $passageModel;
    }

    public function getPassageModel(
            BibleModel $bibleModel, 
            PassageReferenceModel $passageReferenceModel) :PassageModel
    {
        $this->bibleModel = $bibleModel;
        $this->passageReferenceModel = $passageReferenceModel;

        // Generate the Bible Passage ID (BPID).
        $this->bpid = $this->bibleModel->getBid() . '-' . $this->passageReferenceModel->getPassageID();

        // Check if the passage is in the database or fetch it externally.
        if ($this->inDatabase()) {
            $passageModel = $this->retrieveStoredData($this->bpid);
        } else {
            $passageModel = $this->retrieveExternalPassage($this->passageReferenceModel);
        }

        // Return the passage properties.
        return $passageModel;
    }

    /**
     * Checks if the passage exists in the database.
     *
     * @return bool True if the passage exists, false otherwise.
     */
    private function inDatabase($bpid)
    {
        return $this->passageRepository->existsById($bpid);
    }

    /**
     * Retrieves the passage from the database and updates its usage statistics.
     *
     * @return PassageModel The retrieved passage model.
     */
    private function retrieveStoredData($bpid) : PassageModel
    {
        // Fetch the stored data from the database.
        $data = $this->passageRepository->findStoredById($bpid);

        if ($data === null) {
            throw new \RuntimeException("Passage not found: {$bpid}");
        }

        // Create a PassageModel from the retrieved data.
        $passageModel = PassageFactory::createFromData($data);

        // Update the usage statistics for the passage.
        $this->updateUsage($passageModel);

        return $passageModel;
    }

    /**
     * Updates the usage statistics of a passage.
     *
     * @param PassageModel $passage The passage model to update.
     * @return void
     */
    private function updateUsage(PassageModel $passageModel): void
    {
        $passageModel->setDateLastUsed(date('Y-m-d'));
        $passageModel->setTimesUsed($passageModel->getTimesUsed() + 1);

        // Save the updated usage information to the database.
        $this->passageRepository->updatePassageUse($passageModel);
    }
    /**
     * Retrieves the passage from an external source using the appropriate service.
     */
    private function retrieveExternalPassage(PassageReferenceModel $reference): PassageModel
    {
        $service = $this->getPassageService();
        return $service->createPassageModel($reference);
    }

    /** Optional setter if you can’t change the constructor right now */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Determines the appropriate service to use for fetching the passage.
     *
     * @throws \InvalidArgumentException If the source is unsupported.
     */
    private function getPassageService(): AbstractBiblePassageService
    {
        $source = (string)$this->bibleModel->getSource();
        LoggerService::logInfo('BiblePassageService-163', $source);

        // Strategy map (source -> concrete class). No per-service branches below.
        $map = [
            'bible_brain'  => BibleBrainPassageService::class,
            'bible_gateway'=> BibleGatewayPassageService::class,
            'youversion'   => YouVersionPassageService::class,
            'word'         => BibleWordPassageService::class,
            'dbt'          => DbtPassageService::class,
        ];

        $class = $map[$source] ?? null;
        if ($class === null) {
            throw new \InvalidArgumentException("Unsupported source: {$source}");
        }
                /**
         * Decide *before* constructing:
         *  - If the class exposes initRuntime(), we let DI autowire only the
         *    connection factory, then pass runtime args explicitly.
         *  - Otherwise we construct with *named* runtime args so PHP-DI binds
         *    them correctly to the child's __construct(...) signature.
         */
        if (method_exists($class, 'initRuntime')) {
            /** @var AbstractBiblePassageService $svc */
            $svc = $this->container->make($class);
            $svc->initRuntime($this->bibleModel, $this->databaseService);
            return $svc;
        }
        // No initRuntime(): construct with named args (prevents DI misbinding).
        /** @var AbstractBiblePassageService $svc */
        $svc = $this->container->make($class, [
            'bible'           => $this->bibleModel,
            'databaseService' => $this->databaseService,
        ]);
    

        return $svc;
  
    }

   
}
