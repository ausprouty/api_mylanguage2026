<?php

namespace App\Factories;

use App\Models\Bible\BibleModel;
use App\Repositories\BibleRepository;

/**
 * Factory for creating and populating BibleModel instances.
 */
class BibleFactory
{
    private $repository;

    /**
     * Constructor to initialize the repository dependency.
     */
    public function __construct(BibleRepository $repository)
    {
        $this->repository = $repository;
    }

        /**
     * Create a new instance of BibleModel with default values.
     *
     * @return BibleModel
     */
    public static function createWithDefaults(): BibleModel
    {
        $bible = new BibleModel();
        $bible->populate([
            'bid' => 0,
            'source' => '',
            'externalId' => '',
            'volumeName' => '',
            'volumeNameAlt' => '',
            'languageName' => '',
            'languageEnglish' => '',
            'languageCodeHL' => '',
            'languageCodeIso' => '',
            'idBibleGateway' => '',
            'collectionCode' => '',
            'direction' => '',
            'numerals' => '',
            'spacePdf' => '',
            'noBoldPdf' => '',
            'format' => '',
            'text' => 0,
            'audio' => 0,
            'video' => 0,
            'weight' => 0,
            'dateVerified' => '',
        ]);
        return $bible;
    }

    /**
     * Creates a BibleModel with media type properties set.
     */
    public function createWithMediaType(
        string $type,
        array $audioTypes,
        array $textTypes,
        array $videoTypes
    ): BibleModel {
        $model = new BibleModel($this->repository);
        $model->resetMediaFlags();

        if (in_array($type, $textTypes, true)) {
            $model->setText(true);
        }
        if (in_array($type, $audioTypes, true)) {
            $model->setAudio(true);
        }
        if (in_array($type, $videoTypes, true)) {
            $model->setVideo(true);
        }

        return $model;
    }

    /**
     * Prepares and populates a BibleModel for saving.
     */
    public function createPreparedForSave(
        string $source,
        string $externalId,
        string $volume,
        string $collectionCode,
        string $format
    ): BibleModel {
        $model = new BibleModel($this->repository);
        $model->populate([
            'source' => $source,
            'externalId' => $externalId,
            'volumeName' => $volume,
            'collectionCode' => $collectionCode,
            'dateVerified' => date('Y-m-d'),
            'format' => $format
        ]);

        return $model;
    }


}

