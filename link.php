<?php
require 'functions.php';

if(isset($_GET['code']) or isset($_GET['token'])) {
    if(isset($_GET['code'])) {
        $session->requestAccessToken($_GET['code']);
    } else {
        $session->refreshAccessToken($_GET['token']);
    }
    $accessToken = $session->getAccessToken();
    $refreshToken = $session->getRefreshToken();
    $api->setAccessToken($accessToken);

    $me = json_decode(json_encode($api->me()), true);
    $playback = json_decode(json_encode($api->getMyCurrentPlaybackInfo()), true);

    addAccount($me['id'], $me['email'], $me['display_name'], $refreshToken);
    
    $_SESSION['account'] = $me['id']; 
    if(isset($_SESSION['redirect'])) {
        $redirect = $_SESSION['redirect'];
        unset($_SESSION['redirect']);
        header('Location: '.$redirect);
        die;
    }
    echo 'Account '.$me['id'].' ('.$me['display_name'].' - '.$me['email'].') has been successfully linked : <a href="playing.php?account='.$me['id'].'">See your widget</a><br />';
    die();
} else {
    $options = [
        'scope' => [
            'user-read-email',
            'user-read-playback-state',
            'user-read-recently-played',
            'user-modify-playback-state',
        ],
    ];

    header('Location: ' . $session->getAuthorizeUrl($options));
    die();
}
