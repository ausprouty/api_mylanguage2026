<?php

/**
* Class Dbt
*
* DBT_API_SDK_2.php
*
* DBT API Client SDK for DPT API V 2
*
* LICENSE: For use only by formal agreement with Faith Comes By Hearing.
*
* @category HiDef
* @package HiDef_Magic
* @subpackage Wand
* @copyright Copyright (c) 2011 HiDef Web Inc. (http://www.hidefweb.com)
* @version $Id:$
* @link none
* @since File available since Release
*
* Documentation for DBT API calls is at http://www.digitalbibleplatform.com
*/

class Dbt {
    // Configuration

    const REPLY = 'json';

    const API_URI = 'http://dbt.io';

    /**
    * API Version
    *
    * @var string
    */
    protected $apiVersion = '2';

    /**
    * URI to which to GET.
    *
    * @var string
    */
    protected $apiUri;

    /**
    * Params which are shared on every API call.
    *
    * @var array
    */
    protected $dbtParams = array();

    /**
    * Pointer to method that returns the response format constructed for the object
    * made public so that a user can change response type after initialization (mostly for debugging)
    * @var string
    */
    protected $response;

    /**
    * Construct Dbt object
    *
    * @param string $applicationKey The identity of the app in the form of an
    * application key
    * @param string $apiUrl URL to use instead of default url
    * @param string $reply reply protocol.
    * @param string $responseType return type of function (json[default]|
    * array[php array]|url[only returns api url])
    * @param string $echo [true|false] whether or not to echo the call parameters
    * @param string $callback function name to use for JSONP reply.
    */
    public function __construct( $applicationKey, $apiUri = null, $reply = null, $responseType = null, $echo = null, $callback = null )
    {
        $this->apiUri = ($apiUri == null) ? self::API_URI : $apiUri;

        $this->dbtParams = array(
            'v' => $this->apiVersion,
            'key' => $applicationKey,
            'reply' => (($reply == null) ? self::REPLY : $reply),
            'callback' => $callback,
            'echo' => $echo
        );

        if ($responseType == 'array') {
            $this->response = 'getArrayResponse';
        } elseif ($responseType == 'url') {
            $this->response = 'getApiUri';
        } else {
            $this->response = 'getJSONResponse';
        }
    }

    //	public function __set($name, $value)
    // {
    // if ($name == 'response' && ($value === "json" || $value === "array" || $value === "url"))
    //	 $this->{$name} = $value;
    // }

    /**
    * Imports a JSON api response to a PHP array to be used by the server.
    *
    * @param $resourceGroup api resource group to call
    * @param $resource api resource to call
    * @param $params api resource group resource method params
    * @return array|null return from API as PHP array or NULL
    */
    protected function getArrayResponse( $resourceGroup, $resource, $params )
    {
        $feed = $this->getJSONResponse($resourceGroup, $resource, $params);
        if ($feed != '') {
            return json_decode($feed, TRUE);
        } else {
            return null;
        }
    }

    /**
    * Queries dbt api and returns the response in JSON format.
    *
    * @param $resourceGroup api resource group to call
    * @param $resource api resource to call
    * @param $params api resource group resource method params
    * @return string|JSON return from API or NULL
    */
    protected function getJSONResponse( $resourceGroup, $resource, $params )
    {
        $feed = null;
        $uri = $this->getApiUri($resourceGroup, $resource, $params);
        if ($uri != null) {
            @$feed = file_get_contents($uri);
        }
        return $feed;
    }

    /**
    * Builds a specific api call URL depending on passed parameters.
    *
    * @param $resourceGroup api resource group to call
    * @param $resource api resource to call
    * @param $params api resource group resource method params
    */
    protected function getApiUri( $resourceGroup, $resource, $params )
    {
        $requestParams = array_merge($this->dbtParams, $params);
        $requestStrings = array();

        foreach ($requestParams as $name => $value) {
            if ($value != null) {
                $requestStrings[] = $name."=".urlencode($value); //updated to urlencode param values automatically
            }
        }

        return $this->apiUri.'/'.$resourceGroup.'/'.$resource.'?'.implode("&", $requestStrings);
    }

    /**
    * Wrapper method for /api/apiversion call
    *
    */
    public function getApiVersion( )
    {
        $params = array();
        return $this->{$this->response}('api', 'apiversion', $params);
    }

    /**
    * Wrapper method for /api/reply call
    *
    */
    public function getApiReply( )
    {
        $params = array();
        return $this->{$this->response}('api', 'reply', $params);
    }

    /**
    * Wrapper method for /audio/location call
    *
    * @param string $protocol Allows the caller to restrict potential servers
    * from being returned that don't support a specified protocol.
    * Examples: http, http, rtmp, rtmp-amazon
    */
    public function getAudioLocation( $protocol = null )
    {
        $params = array('protocol' => $protocol);
        return $this->{$this->response}('audio', 'location', $params);
    }

    /**
    * Wrapper method for /audio/path call
    *
    * @param string $damId DAM ID of volume
    * @param string $bookId book id of the book to get chapters for
    * @param int $chapterId chapter id of the chapter to get audio for
    * @param string $encoding audio encoding format
    * @param int $bookOrder numerical order of book in volume
    */
    public function getAudioPath( $damId, $bookId = null, $chapterId = null, $encoding = null, $bookOrder = null )
    {
        $params = array(
            'dam_id' => $damId,
            'encoding' => $encoding,
            'book_order' => $bookOrder,
            'book_id' => $bookId,
            'chapter_id' => $chapterId
        );

        return $this->{$this->response}('audio', 'path', $params);
    }

    /**
    * Wrapper method for /audio/zippath call
    *
    * @param string $damId DAM ID of volume
    */
    public function getAudioZipPath( $damId )
    {
        $params = array('dam_id' => $damId);
        return $this->{$this->response}('audio', 'zippath', $params);
    }

    /**
    * Wrapper method for /audio/versestart call
    *
    * @param string $damId DAM ID of volume
    * @param string $bookId book id of the book to get chapters for
    * @param int $chapterId chapter id of the chapter to get audio for
    */
    public function getVerseStart( $damId, $bookId, $chapterId )
    {
        $params = array(
            'dam_id' => $damId,
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
        );

        return $this->{$this->response}('audio', 'versestart', $params);
    }

    /**
    *
    * Wrapper method for /library/language call
    *
    * @param string $code language code on which to filter
    * @param string $name language name in either native language or English on which to filter
    * @param string $sortBy [code|name|english]
    * @param string $fullWord [true|false] interpret $name as full words only
    * @param string $familyOnly [true|false] return only language families
    */
    public function getLibraryLanguage( $code = null, $name = null, $sortBy = null, $fullWord = null, $familyOnly = null )
    {
        $params = array(
            'code' => $code,
            'name' => $name,
            'full_word' => $fullWord,
            'family_only' => $familyOnly,
            'sort_by' => $sortBy
        );

        return $this->{$this->response}('library', 'language', $params);
    }

    /**
    *
    * Wrapper method for /library/version call
    *
    * @param string $code version code on which to filter
    * @param string $name version name in either native language or English on which to filter
    * @param string $sortBy [code|name|english]
    */
    public function getLibraryVersion( $code = null, $name = null, $sortBy = null )
    {
        $params = array(
            'code' => $code,
            'name' => $name,
            'sort_by' => $sortBy
        );

        return $this->{$this->response}('library', 'version', $params);
    }

    /**
    * Wrapper method for /library/volume call
    *
    * @param string $damId DAM ID of volume
    * @param string $fcbhId FCBH ID of volume
    * @param string $media the format of languages the caller
    * is interested in. This specifies if you want languages available in
    * text or languages available in audio or only languages available in
    * both. All are returned by default.
    * @param string $delivery a criteria
    * for approved delivery method. 'any' means any of the supported
    * methods (this list may change over time). 'none' means assets that
    * are not approved for any of the supported methods. All returned by
    * default.
    * @param string $language Filter the versions returned to a specified
    * language. For example return all the 'English' volumes.
    * @param string $languageCode Filter the volumes returned to a specified
    * language code. For example return all the 'eng' volumes.
    * @param string $versionCode Filter the volumes returned to a specified
    * version code. For example return all the 'ESV' volumes.
    * @param timestamp $updated This is a unix timestamp in UTC to restrict
    * volumes returned only if they were modified since the specified time.
    * @param string $status publishing status of volume
    * @param string $expired [true|false] whether or not the volume is expired
    * @param integer $orgId Id of organization to which volume belongs
    * @param string $fullWord interpret $name as full words only
    * @param string $languageFamilyCode Filter the volumes returned to a specified
    * language code for language family
    * @param boolean $dbpAgreement whether or not a DBP Agreement is in place
    * @param string $resolution resolution if applicable to media
    */
    public function getLibraryVolume( $damId = null, $fcbhId = null, $media = null, $delivery = null,
        $language = null, $languageCode = null, $versionCode = null,
        $updated = null, $status = null, $expired = null, $orgId = null,
        $fullWord = null, $languageFamilyCode = null, $dbpAgreement = null,
        $resolution = null )
        {
            $params = array(
            'dam_id' => $damId,
            'fcbh_id' => $fcbhId,
            'media' => $media,
            'delivery' => $delivery,
            'language' => $language,
            'full_word' => $fullWord,
            'language_code' => $languageCode,
            'language_family_code' => $languageFamilyCode,
            'version_code' => $versionCode,
            'updated' => $updated,
            'status' => $status,
            'dbp_agreement' => $dbpAgreement,
            'expired' => $expired,
            'resolution' => $resolution,
            'organization_id' => $orgId
        );

        return $this->{$this->response}('library', 'volume', $params);
    }

    /**
    * Wrapper method for /library/volumelanguage call.
    *
    * @param string $root the language name root. Can be used to restrict the
    * response to only languages that start with 'Quechua' for example
    * @param string $languageCode (optional) 3 letter language code
    * @param string $media [text|audio|both] the format of languages the caller
    * is interested in. This specifies if you want languages available in
    * text or languages available in audio or only languages available in
    * both. All are returned by default.
    * @param string $delivery a criteria
    * for approved delivery method. 'any' means any of the supported
    * methods (this list may change over time). 'none' means assets that
    * are not approved for any of the supported methods. All returned by
    * default.
    * @param string $status
    * @param integer $orgId
    * @param boolean $fullWord interpret $name as full words only
    * @param string $resolution resolution if applicable to media
    */
    public function getLibraryVolumeLanguage(
        $root = null, $languageCode = null, $media = null,
        $delivery = null, $status = null, $orgId = null,
        $fullWord = null, $resolution = null )
    {
        $params = array(
            'root' => $root,
            'full_word' => $fullWord,
            'language_code' => $languageCode,
            'media' => $media,
            'delivery' => $delivery,
            'status' => $status,
            'resolution' => $resolution,
            'organization_id' => $orgId
        );

        return $this->{$this->response}('library', 'volumelanguage', $params);
    }

    /**
    * Wrapper method for /library/volumelanguagefamily call.
    *
    * @param string $root the language name root. Can be used to restrict the
    * response to only languages that start with 'Quechua' for example
    * @param string $languageCode (optional) 3 letter language code
    * @param string $media the format of languages the caller
    * is interested in. This specifies if you want languages available in
    * text or languages available in audio or only languages available in
    * both. All are returned by default.
    * @param string $delivery a criteria
    * for approved delivery method. 'any' means any of the supported
    * methods (this list may change over time). 'none' means assets that
    * are not approved for any of the supported methods. All returned by
    * default.
    * @param string $status
    * @param integer $orgId
    * @param string $fullWord [true|false] interpret $name as full words only
    * @param string $resolution resolution if applicable to media
    */
    public function getLibraryVolumeLanguageFamily( $root = null, $languageCode = null, $media = null,
    $delivery = null, $status = null, $orgId = null,
    $fullWord = null, $resolution = null )
    {
        $params = array(
            'root' => $root,
            'full_word' => $fullWord,
            'language_code' => $languageCode,
            'media' => $media,
            'delivery' => $delivery,
            'status' => $status,
            'resolution' => $resolution,
            'organization_id' => $orgId
        );

        return $this->{$this->response}('library', 'volumelanguagefamily', $params);
    }

    /**
    * Wrapper method for /library/volumeorganization call
    *
    * @param string $media the format of languages the caller
    * is interested in.
    * @param string $delivery a criteria
    * for approved delivery method. 'any' means any of the supported
    * methods (this list may change over time). 'none' means assets that
    * are not approved for any of the supported methods. All returned by
    * default.
    * @param string $language Filter the versions returned to a specified
    * language. For example return all the 'English' volumes.
    * @param string $fullWord [true|false] interpret $name as full words only
    * @param string $languageCode Filter the volumes returned to a specified
    * language code. For example return all the 'eng' volumes.
    * @param string $languageFamilyCode Filter the volumes returned to a specified
    * language code for language family
    * @param string $versionCode Filter the volumes returned to a specified
    * version code. For example return all the 'ESV' volumes.
    * @param timestamp $updated This is a unix timestamp in UTC to restrict
    * volumes returned only if they were modified since the specified time.
    * @param string $status publishing status of volume
    * @param string $expired [true|false] whether or not the volume is expired
    * @param string $resolution resolution if applicable to media
    * @param integer $orgId Id of organization to which volume belongs
    * @param string $orgName name of organizaiton to which volume belongs
    */
    public function getLibraryVolumeOrganization(
        $media = null, $delivery = null,
        $language = null, $fullWord = null, $languageCode = null,
        $languageFamilyCode = null, $versionCode = null,
        $updated = null, $status = null, $expired = null,
        $resolution = null, $orgId = null, $orgName = null )
    {
        $params = array(
            'media' => $media,
            'delivery' => $delivery,
            'language' => $language,
            'full_word' => $fullWord,
            'language_code' => $languageCode,
            'language_family_code' => $languageFamilyCode,
            'version_code' => $versionCode,
            'updated' => $updated,
            'status' => $status,
            'expired' => $expired,
            'resolution' => $resolution,
            'organization_id' => $orgId,
            'organization_name' => $orgName
        );

        return $this->{$this->response}('library', 'volumeorganization', $params);
    }

    /**
    * Wrapper method for /library/bookorder call
    *
    * @param string $damId DAM ID of volume
    */
    public function getLibraryBookOrder( $damId )
    {
        $params = array(
            'dam_id' => $damId
        );

        return $this->{$this->response}('library', 'bookorder', $params);
    }

    /**
    * Wrapper method for /library/book call
    *
    * @param string $damId DAM ID of volume
    */
    public function getLibraryBook( $damId )
    {
        $params = array(
            'dam_id' => $damId
        );

        return $this->{$this->response}('library', 'book', $params);
    }

    /**
    * Wrapper method for /library/bookname call
    *
    * @param string $languageCode language code for book names
    */
    public function getLibraryBookName( $languageCode )
    {
        $params = array(
            'language_code' => $languageCode
        );

        return $this->{$this->response}('library', 'bookname', $params);
    }

    /**
    * Wrapper method for /library/chapter call
    *
    * @param string $damId DAM ID of volume
    * @param string $bookId book id of the book to get chapters for
    */
    public function getLibraryChapter( $damId, $bookId = null )
    {
        $params = array(
            'dam_id' => $damId,
            'book_id' => $bookId
        );

        return $this->{$this->response}('library', 'chapter', $params);
    }

    /**
    * Wrapper method for /library/verseinfo call
    *
    * @param string $damId DAM ID of volume
    * @param string $bookId book id of the book to get text for
    * @param int $chapterId chapter id of the chapter to get text for
    * @param int $verse_start verse id of the verse to get text for (start position)
    * @param int $verse_end verse id of the verse to get text for (end position)
    */
    public function getLibraryVerseinfo(
        $damId, $bookId = null, $chapterId = null,
        $verseStart = null, $verseEnd = null )
    {
        $params = array(
            'dam_id'=> $damId,
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'verse_start' => $verseStart,
            'verse_end' => $verseEnd
        );

        return $this->{$this->response}('library', 'verseinfo', $params);
    }

    /**
    * Wrapper method for /library/numbers call
    *
    * @param string $languageCode language code for book names
    * @param int $start first number for series of consecutive numbers returned
    * @param int $end last number for series of consecutive numbers returned
    */
    public function getLibraryNumbers( $languageCode, $start, $end )
    {
        $params = array(
            'language_code' => $languageCode,
            'start' => $start,
            'end' => $end
        );

        return $this->{$this->response}('library', 'numbers', $params);
    }

    /**
    * Wrapper method for /library/metadata call
    *
    * @param string $damId DAM ID of volume
    * @param int $organizationId ID for organization by which to filter
    */
    public function getLibraryMeta( $damId = null, $organizationId = null )
    {
        $params = array(
            'dam_id' => $damId,
            'organization_id' => $organizationId
        );

        return $this->{$this->response}('library', 'metadata', $params);
    }

    /**
    * Wrapper method for /library/asset call
    *
    * @param string $damId DAM ID of volume
    */
    public function getLibraryAsset( $damId = null )
    {
        $params = array(
            'dam_id' => $damId
        );

        return $this->{$this->response}('library', 'asset', $params);
    }

    /**
    * Wrapper method for /library/organization call
    *
    * @param string $organizationName name of organization
    * @param int $organizationId ID for organization by which to filter
    * @param boolean $enabled whether or not the organization is active
    */
    public function getLibraryOrganization( $organizationName = null, $organizationId = null,
    $enabled = null )
    {
        $params = array(
            'name' => $organizationName,
            'id' => $organizationId,
            'enabled' => $enabled
        );

        return $this->{$this->response}('library', 'organization', $params);
    }

    /**
    * Wrapper method for /programs/list call
    *
    * @param string $name program name search
    */
    public function getProgramsList( $name = null )
    {
        $params = array(
            'name' => $name
        );

        return $this->{$this->response}('programs', 'list', $params);
    }

    /**
    * Wrapper method for /programs/program call
    *
    * @param string $id ID of program
    * @param int $day day in program
    */
    public function getProgramsProgram( $id, $day = null )
    {
        $params = array(
            'id' => $id,
            'day' => $day
        );

        return $this->{$this->response}('programs', 'program', $params);
    }

    /**
    * Wrapper method for /text/verse call
    *
    * @param string $damId DAM ID of volume
    * @param string $bookId book id of the book to get text for
    * @param int $chapterId chapter id of the chapter to get text for
    * @param int $verseStart verse id of the verse to get text for (start position)
    * @param int $verseEnd verse id of the verse to get text for (end position)
    * @param string $markup If specified returns the verse text in a variety of
    * standarized formats. By default the internal DBT format is returned.
    * Current options include OSIS, and native (the default DBT format).
    */
    public function getTextVerse( $damId, $bookId = null, $chapterId = null, $verseStart = null,
    $verseEnd = null, $markup = null )
    {
        $params = array(
            'dam_id'=> $damId,
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'verse_start' => $verseStart,
            'verse_end' => $verseEnd,
            'markup' => $markup
        );

        return $this->{$this->response}('text', 'verse', $params);
    }

    /**
    * Wrapper method for /text/search call
    *
    * @param string $damId DAM ID of volume
    * @param string $query The text that the caller wishes to search for in the
    * specified text.
    * @param string $bookId The book id to limit the search to.
    * @param int $offset The offset for the set of results to return to start from.
    * @param int $limit The number of results to return. Default is 50.
    */
    public function getTextSearch( $damId, $query, $bookId = null, $offset = null, $limit = null )
    {
        $params = array(
            'dam_id' => $damId,
            'query' => $query,
            'book_id' => $bookId,
            'offset' => $offset,
            'limit' => $limit
        );

        return $this->{$this->response}('text', 'search', $params);
    }

    /**
    * Wrapper method for /text/searchgroup call
    *
    * @param string $damId DAM ID of volume (null to use default from the
    * class init)
    * @param string $query The text that the caller wishes to search for in the
    * specified text.
    */
    public function getTextSearchgroup( $damId, $query )
    {
        $params = array(
            'dam_id' => $damId,
            'query' => $query
        );

        return $this->{$this->response}('text', 'searchgroup', $params);
    }

    /**
    * Wrapper method for /video/videopath call
    *
    * @param string $damId DAM ID of volume
    * @param string $encoding encoding for video
    * @param string $resolution resolution of video
    * @param int $segmentOrder segment number
    * @param string $bookId book id of the book to get text for
    * @param int $chapterId chapter id of the chapter to get text for
    * @param int $verseId verse id of the verse to get text for
    */
    public function getVideoVideoPath( $damId, $encoding, $resolution = null, $segmentOrder = null,
    $bookId = null, $chapterId = null, $verseId = null )
    {
        $params = array(
            'dam_id'=> $damId,
            'encoding' => $encoding,
            'resolution' => $resolution,
            'segment_order' => $segmentOrder,
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'verse_id' => $verseId
        );

        return $this->{$this->response}('video', 'videopath', $params);
    }

    /**
    * Wrapper method for /video/videolocation call
    *
    * @param string $protocol for location access
    */
    public function getVideoVideoLocation( $protocol = null )
    {
        $params = array(
            'protocol'=> $protocol
        );

        return $this->{$this->response}('video', 'videolocation', $params);
    }

    /**
    * Wrapper method for /video/jesusfilm call
    *
    * @param string $damId DAM ID of volume
    * @param string $encoding encoding for video
    * @param string $bookId book id of the book to get text for
    * @param int $chapterId chapter id of the chapter to get text for
    * @param int $verseId verse id of the verse to get text for
    */
    public function getVideoJesusfilm( $damId, $encoding, $bookId = null, $chapterId = null, $verseId = null )
    {
        $params = array(
            'dam_id'=> $damId,
            'encoding' => $encoding,
            'book_id' => $bookId,
            'chapter_id' => $chapterId,
            'verse_id' => $verseId
        );

        return $this->{$this->response}('video', 'jesusfilm', $params);
    }
}
