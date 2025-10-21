<?php

namespace App\Services\BiblePassage;

use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Services\Database\DatabaseService;
use App\Factories\PassageFactory;
use App\Repositories\PassageRepository;
use stdClass;

/**
 * AbstractBiblePassageService provides a base structure for handling Bible passages.
 * Subclasses must implement specific methods for retrieving passage data.
 */
abstract class AbstractBiblePassageService
{
    /** @var PassageReferenceModel|null */
    protected ?PassageReferenceModel $passageReference = null;

    protected BibleModel $bible;
    protected DatabaseService $databaseService;
    protected PassageRepository $passageRepository;
    /** @var array<string,mixed>|string|null */
    protected array|string|null $webpage = null;
    protected ?string $bpid = null;
    protected ?string $passageText = null;
    protected ?string $referenceLocalLanguage = null;
    protected ?string $passageUrl = null;

    /**
     * Constructor for initializing the BiblePassageService.
     *
     * @param BibleModel $bible
     * @param DatabaseService $databaseService
    */

    public function __construct(
        BibleModel $bible,
        DatabaseService $databaseService
    ) {

        $this->bible = $bible;
        $this->databaseService = $databaseService;

        // Initialize the passage repository using the provided database service.
        $this->passageRepository = new PassageRepository($this->databaseService);
    }

    /**
     * Get the URL for the passage.
     * Subclasses must implement this method.
     *
     * @return string The URL for the passage.
     */
    abstract public function getPassageUrl(): string;

    /**
     * Get the webpage content for the passage.
     * Subclasses must implement this method.
     *
     * @return  array<string,mixed>|string.
     */
    abstract public function getWebPage(): array|string;

    /**
     * Get the text of the Bible passage.
     * Subclasses must implement this method.
     *
     * @return string The text of the passage.
     */
    abstract public function getPassageText(): string;

    /**
     * Get the local language reference for the passage.
     * Subclasses must implement this method.
     *
     * @return string The local language reference.
     */
    abstract public function getReferenceLocalLanguage(): string;

    /**
     * Create and save a passage model based on the implemented methods.
     *
     * @return PassageModel The created passage model.
     */
    public function createPassageModel(
         PassageReferenceModel $reference
    ): PassageModel
    {
       // Bind the reference to this instance for downstream abstract methods.
        $this->passageReference = $reference;

        // Fetch necessary data by calling abstract methods.
        $this->passageUrl = $this->getPassageUrl();
        $this->webpage = $this->getWebPage();
        $this->passageText = $this->getPassageText();
        $this->referenceLocalLanguage = $this->getReferenceLocalLanguage();

        // Generate a Bible Passage ID (BPID) based on the Bible ID and passage reference.
        $bpid = $this->bible->getBid() . '-' . $this->passageReference->getPassageID();

        // Prepare data for creating the PassageModel.
        $data = new stdClass();
        $data->bpid = $bpid;
        $data->dateChecked = date('Y-m-d');
        $data->dateLastUsed = date('Y-m-d');
        $data->passageText = (string) $this->passageText;
        $data->passageUrl = (string) $this->passageUrl;
        $data->referenceLocalLanguage = (string) $this->referenceLocalLanguage;
        $data->timesUsed = 1;

        // Create a PassageModel instance using the factory and save it to the repository if it has data
        $passageModel = PassageFactory::createFromData($data);
        if (
            $data->passageText !== '' &&
            strlen(trim($data->passageText)) >= 5
        ) {
            $this->passageRepository->savePassageRecord($passageModel);
        }
        

        return $passageModel;
    }
}
