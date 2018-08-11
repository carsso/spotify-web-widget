<?php
require 'config.php';

$api = new SpotifyWebAPI\SpotifyWebAPI();
if(!class_exists('SQLite3'))
      die("SQLite 3 NOT supported.");

$db = new SQLite3('database.sqlite');
if (!$db) {
    die($db->lastErrorMsg());
}
$ret = $db->query('SELECT * FROM spotify');
if(!$ret){
    die($db->lastErrorMsg());
}
while ($row = $ret->fetchArray()) {
    $token = $row['token'];
    $id = $row['id'];
        $session->refreshAccessToken($token);
        $accessToken = $session->getAccessToken();
        $api->setAccessToken($accessToken);
        $me = json_decode(json_encode($api->me()), true);
        $playback = json_decode(json_encode($api->getMyCurrentPlaybackInfo()), true);
        echo 'Account '.$me['id'].' ('.$me['display_name'].' - '.$me['email'].') is linked and working : <a href="playing.php?account='.$me['id'].'">See widget</a><br />';
    try {
    } catch(Exception $e) {
        echo 'Account '.$row['id'].' token is invalid : '.$e->getMessage().'<br />';
    }
}
die();
