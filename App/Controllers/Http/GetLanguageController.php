<?php

/*

PassageService — main use case (DB cache + external source).

PassageSourceInterface + adapters — “how to fetch from X”.

PassageSourceResolver — picks the right adapter for a Bible.

TextDirectionService — handles direction & wrapping when you
reintroduce RTL, etc.

*/

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\BibleRepository;
use App\Factories\PassageReferenceFactory;
use App\Services\BiblePassage\PassageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetPassageController
{
    public function __construct(
        private BibleRepository $bibleRepository,
        private PassageReferenceFactory $referenceFactory,
        private PassageService $passageService
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request
    ): ResponseInterface {
        $q      = $request->getQueryParams();
        $bid    = (int) ($q['bid'] ?? 0);
        $entry  = (string) ($q['entry'] ?? '');

        $bible     = $this->bibleRepository->findByBid($bid);
        $reference = $this->referenceFactory
            ->createFromEntry($bible, $entry);

        $passage = $this->passageService
            ->getPassage($bible, $reference);

        // transform PassageModel to whatever JSON you like
        $payload = [
            'text'   => $passage->getPassageText(),
            'url'    => $passage->getPassageUrl(),
            'ref'    => $passage->getReferenceLocalLanguage(),
        ];

        return new JsonResponse($payload);
    }
}
