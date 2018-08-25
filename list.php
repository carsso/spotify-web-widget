<?php
require 'functions.php';

$db = connectDb();
$ret = $db->query('SELECT * FROM spotify');
if(!$ret){
    die($db->lastErrorMsg());
}
while ($row = $ret->fetchArray()) {
    $token = $row['token'];
    $username = $row['username'];
    $name = $row['display_name'];
    $email = $row['email'];
    try {
        $session->refreshAccessToken($token);
        $accessToken = $session->getAccessToken();
        $api->setAccessToken($accessToken);
        $me = json_decode(json_encode($api->me()), true);
        $playback = json_decode(json_encode($api->getMyCurrentPlaybackInfo()), true);
        echo 'Account '.$username.' ('.$me['display_name'].' - '.$me['email'].') is linked and working : <a href="playing.php?account='.$me['id'].'">See widget</a><br />';
    } catch(Exception $e) {
        echo 'Account '.$username.' ('.$name.' - '.$email.') API ERROR  : '.$e->getMessage().' <a href="playing.php?account='.$username.'">See widget</a><br />';
    }
}
die();
