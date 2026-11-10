<?php

declare(strict_types=1);

namespace App\Services\BiblePassage;

use App\Models\Bible\BibleModel;
use App\Models\Bible\PassageModel;
use App\Models\Bible\PassageReferenceModel;
use App\Repositories\PassageRepository;
use App\Services\LoggerService;
use Psr\Container\ContainerInterface;

final class BiblePassageService
{
    public function __construct(
        private PassageRepository $passageRepository,
        private ReferenceLocaliserService $referenceLocaliser,
        private ContainerInterface $container
    ) {
    }

    /**
     * Retrieve a complete Bible passage.
     *
     * Accepts populated BibleModel and PassageReferenceModel.
     * Returns PassageModel.
     */
    public function getPassage(
        BibleModel $bible,
        PassageReferenceModel $reference
    ): PassageModel {

        // 1. Generate unique Bible Passage ID.
        $bpid = $bible->getBid() . '-'
              . $reference->getPassageID();

        // 2. Look for an existing passage.
        $passage = $this->passageRepository->findByBpid($bpid);

        if (
            $passage instanceof PassageModel &&
            trim($passage->getPassageText()) !== ''
        ) {
            $passage->updateUsage();

            $this->passageRepository->updatePassageUse(
                $passage
            );

            return $passage;
        }

        // 3. Select the external Bible provider.
        $provider = $this->getProvider($bible->getSource());

        // 4. Fetch the passage.
        $passage = $provider->fetchPassage(
            $bible,
            $reference
        );

        // 5. Assign the BPID.
        $passage->setBpid($bpid);

        // Do not save empty passages.
        if (trim($passage->getPassageText()) === '') {
            LoggerService::logError(
                'BiblePassageService',
                'Empty passage: ' . $bpid
            );

            return $passage;
        }

        // 6. Localise reference numerals.
        $languageCodeHL = $bible->getLanguageCodeHL();

        if ($languageCodeHL !== '') {
            $this->referenceLocaliser->applyNumeralSet(
                $passage,
                $languageCodeHL
            );
        }

        // 7. Set passage metadata.
        $today = date('Y-m-d');

        $passage->setDateChecked($today);
        $passage->setDateLastUsed($today);
        $passage->setTimesUsed(1);

        // 8. Save passage.
        $this->passageRepository->savePassageRecord(
            $passage
        );

        return $passage;
    }

    /**
     * Select the provider using the Bible source.
     */
    private function getProvider(
        string $source
    ): BiblePassageProviderInterface {

        $providers = [
            'bible_brain' =>
                BibleBrainPassageService::class,

            'bible_gateway' =>
                BibleGatewayPassageService::class,

            'youversion' =>
                YouVersionPassageService::class,

            'word' =>
                BibleWordPassageService::class,
        ];

        $class = $providers[$source] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException(
                'Unsupported Bible source: ' . $source
            );
        }

        $provider = $this->container->get($class);

        if (!$provider instanceof BiblePassageProviderInterface) {
            throw new \RuntimeException(
                'Invalid Bible passage provider: ' . $class
            );
        }

        return $provider;
    }
}
