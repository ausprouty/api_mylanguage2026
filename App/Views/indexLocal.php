<?php
writeLogDebug('indexLocal', 'We are in IndexLocal');

echo "
<p><a href= '/api_mylanguage/translate/words/eng/deu'>Test Translation</a></p>
<p><a href= '/api_mylanguage/tests/createQrCode'>CreateQRCode</a></p>

<p><a href = '/api_mylanguage/api/life/view/2/eng00/frn00'>Principles 2 French-English</a></p>

<h2>DBS</h2>
<p><a href = '/import/bible/externalId'>Update ExternalID</a></p>
<p><a href = '/api_mylanguage/test/dbs/bilingual'>Bi-lingual DBS</a></p>
<p><a href = '/api_mylanguage/test/dbs/pdf'>Can Create Pdf</a></p>


<h1>API</h1>
<p><a href = '/api_mylanguage/api/dbs/languages'>Language options for DBS</a></p>
<p><a href = '/api_mylanguage/api/dbs/studies'>Study options for DBS</a></p>
<p><a href = '/api_mylanguage/api/bibles/eng'>Bibles for Language</a></p>
<p><a href = '/api_mylanguage/api/dbs/2/eng00/frn00'>DBS 2 French-English</a></p>
<p><a href = '/api_mylanguage/api/dbs2/2/eng00/frn00'>New DBS 2 French-English</a></p>

<h1> Translation </h1>
<p><a href = '/api_mylanguage/translate/video/words'>Import Translation of JESUS video words</a></p>
<p><a href = '/api_mylanguage/translate/dbs/words'>Import Translation of DBS words</a></p>
<p><a href = '/api_mylanguage/translate/life/words'>Import Translation of Life Principle words</a></p>
<p><a href = '/api_mylanguage/translate/leadership/words'>Import Translation of Leadership words</a></p>

<h1> Import Routines for MyLanguage OOPHP</h1>
<p><a href = '/api_mylanguage/import/country/languages/jproject'>Import Country Languages from JProject</a></p>
<p><a href = '/api_mylanguage/import/country/languages/jproject2'>Import Country Languages from JProject2</a></p>

<p><a href = '/api_mylanguage/import/lumo'>Import Lumo Videos</a></p>

<p><a href = '/api_mylanguage/import/tracts'>Verify Bi-lingual Tract Information</a></p>
<p><a href = '/api_mylanguage/import/bible/externalId'>Update ExternalID</a></p>

<p><a href = '/api_mylanguage/import/bible/languages'>Import HL Language Codes into Bibles</a></p>
<p><a href = '/api_mylanguage/import/bibleBookNames/languages'>Import HL Language Codes into Bible Book Names</a></p>


<p><a href = '/api_mylanguage/import/biblebrain/languages'>Import Languages from BibleBrain</a></p>
<br>
<p><a href = '/api_mylanguage/import/biblebrain/setup'>Reset Bible Brain Check Date for below</a></p>
<p><a href = '/api_mylanguage/import/biblebrain/bibles'>Import Bibles from BibleBrain</a></p>
<p><a href = '/api_mylanguage/import/biblegateway/bibles'>Import Bibles from BibleGateway</a></p>
<p><a href = '/api_mylanguage/import/dbs/words'>Import DBS Words from local file</a></p>
<p><a href = '/api_mylanguage/import/dbs/database'>Update DBS Database to show languages we have questions and Bibles for</a></p>

Tests by Alphabet

<p><a href = '/api_mylanguage/test/biblebrain/bible/default'>Default Bible for Language   </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/bible/formats'>Format Types </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/languages/country'>Languages for Country -ok   </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/formatted'>Bible Text Formatted </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/json'>Bible Text Json  </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/plain'>Bible Text Plain</a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/usx'>Bible Text USX</a></p>
<p> <a href= '/api_mylanguage/test/biblegateway'>Get Passage   </a></p>
<p><a href = '/api_mylanguage/test/bibles/best'> Best Bible for Language</a></p>
<p><a href = '/api_mylanguage/test/passage/select'>Select Passage</a></p>
<p> <a href= '/api_mylanguage/test/word/passage/al'>Get af Passage from external id</a></p>
<p> <a href= '/api_mylanguage/test/youversion/link'>Get Passage Link</a></p>
<p><a href = '/api_mylanguage/test/bibles/best'> Best Bible for Language</a></p>
<p><a href = '/api_mylanguage/test/biblebrain/bible/default'>Default Bible for Language   </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/bible/formats'>Format Types </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/languages/country'>Languages for Country -ok   </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/formatted'>Bible Text Formatted </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/json'>Bible Text Json  </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/plain'>Bible Text Plain</a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/usx'>Bible Text USX</a></p>
<p><a href= '/api_mylanguage/test/biblegateway'>Get Passage   </a></p>
<p><a href = '/api_mylanguage/test/passage/select'>Select Passage</a></p>
<p><a href= '/api_mylanguage/test/word/passage/al'>Get af Passage from external id</a></p>
<p><a href= '/api_mylanguage/test/youversion/link'>Get Passage Link</a></p>


<h1> Tests for MyLanguage OOPHP</h1>
<p><a href= '/api_mylanguage/test'>Current Test</a></p>

<h2>Web Access</h2>
<p><a href= '/api_mylanguage/webpage'>Access Webpage -ok </a></p>

<h2>Bible Brain</h2>

<p><a href = '/api_mylanguage/test/biblebrain/languages/country'>Languages for Country -ok   </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/bible/default'>Default Bible for Language   </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/bible/formats'>Format Types </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/json'>Bible Text Json  </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/plain'>Bible Text Plain</a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/formatted'>Bible Text Formatted </a></p>
<p><a href = '/api_mylanguage/test/biblebrain/passage/usx'>Bible Text USX</a></p>

<h2>Bible Gateway</h2>
<p> <a href= '/api_mylanguage/test/biblegateway'>Get Passage   </a></p>
<h2>Word(local)</h2>
<p> <a href= '/api_mylanguage/test/word/passage/al'>Get af Passage from external id</a></p>

<h2>YOuVersion</h2>
<p> <a href= '/api_mylanguage/test/youversion/link'>Get Passage Link</a></p>



<h2>Bibles</h2>
<p><a href = '/api_mylanguage/test/bibles/best'> Best Bible for Language</a></p>
<p><a href = '/api_mylanguage/test/passage/select'>Select Passage</a></p>



";
