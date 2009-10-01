<?php

define("DATABASE_NAME", 'foxy-orders');
$couchdb = new CouchDB(DATABASE_NAME);
try {
    $response = $couchdb->getDoc('preferences');
} catch(CouchDBException $e) {
    die($e->getMessage()."\n");
}

$preferences = $response->getBodyAsObject();

if (!$preferences || !$preferences->shared_secret || !$preferences->install_url) {
    die("Foxycouch hasn't been set up properly -- please make sure there is a document with ID 'preferences' that has 'shared_secret' and 'install_url' fields in the " . DATABASE_NAME . " database");
}

?>
