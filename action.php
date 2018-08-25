<?php
require 'functions.php';

if(!isset($_GET['account'])) {
    die('Missing account');
}

if(!isset($_GET['action'])) {
    die('Missing action');
}

if(!in_array($_GET['action'], array('play','pause','relink'))) {
    die('Invalid action');
}

$account = $_GET['account'];
$action = $_GET['action'];

$token = getToken($account);
if(!$token) {
    die('Unknown account, please link it first');
}
if(!isset($_SESSION['account'])) {
    $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: link.php');
    die;
}
if($_SESSION['account'] != $_GET['account'])
{
    die('Sorry, not allowed to control this account, this is not your account');
}

try {
    $session->refreshAccessToken($token);
    $accessToken = $session->getAccessToken();
    $api->setAccessToken($accessToken);

    if($action == 'play') {
        $api->play();
    } elseif($action == 'pause') {
        $api->pause();
    } elseif($action == 'relink') {
        # nothing to do
    } else {
        die('Invalid action');
    }
    clearMemcacheData($account);
    echo '<html><head><script type="text/javascript">window.close();</script></head><body>Action '.$action.' performed, you can close this window</body></html>';
} catch (SpotifyWebAPI\SpotifyWebAPIException $e) {
    if($e->getMessage() == 'Insufficient client scope' or $e->getMessage() == 'Permissions missing' or $e->getMessage() == 'Refresh token revoked') {
        $_SESSION['redirect'] = $_SERVER['REQUEST_URI'];
        header('Location: link.php');
        die;
    } else {
        throw $e;
    }
}

