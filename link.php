<?php
require 'config.php';

$api = new SpotifyWebAPI\SpotifyWebAPI();
if(!class_exists('SQLite3'))
      die("SQLite 3 NOT supported.");

if(isset($_GET['code']) or isset($_GET['token'])) {
    if(isset($_GET['code'])) {
        $session->requestAccessToken($_GET['code']);
    } else {
        $session->refreshAccessToken($_GET['token']);
    }
    $accessToken = $session->getAccessToken();
    $refreshToken = $session->getRefreshToken();
    $api->setAccessToken($accessToken);

    $db = new SQLite3('database.sqlite');
    if (!$db) {
        die($db->lastErrorMsg());
    }
    $ret = $db->query('SELECT name FROM sqlite_master WHERE type="table" AND name="spotify";');
    if(!$ret){
        die($db->lastErrorMsg());
    }
    if($ret->fetchArray()['name'] != 'spotify')
    {
        $ret = $db->exec('
            CREATE TABLE IF NOT EXISTS spotify (
                id integer PRIMARY KEY,
                username text NOT NULL,
                email text NOT NULL,
                display_name text NOT NULL,
                token text NOT NULL
            );
            CREATE UNIQUE INDEX idx_spotify_username ON spotify (username);
        ');
        if(!$ret){
            die($db->lastErrorMsg());
        }
    }

    $me = json_decode(json_encode($api->me()), true);
    $playback = json_decode(json_encode($api->getMyCurrentPlaybackInfo()), true);

    $req = $db->prepare('INSERT OR REPLACE INTO spotify(username, email, display_name, token) VALUES (:username, :email, :display_name, :token);');
    if(!$req){
        die($db->lastErrorMsg());
    }
    $req->bindValue(':username', $me['id']);
    $req->bindValue(':email', $me['email']);
    $req->bindValue(':display_name', $me['display_name']);
    $req->bindValue(':token', $refreshToken);
    $ret = $req->execute();
    if(!$ret){
        die($db->lastErrorMsg());
    }
    

    echo 'Account '.$me['id'].' ('.$me['display_name'].' - '.$me['email'].') has been successfully linked : <a href="playing.php?account='.$me['id'].'">See your widget</a><br />';
    die();
} else {
    $options = [
        'scope' => [
            'user-read-email',
            'user-read-playback-state',
        ],
    ];

    header('Location: ' . $session->getAuthorizeUrl($options));
    die();
}
