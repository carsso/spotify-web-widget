<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';

if(!class_exists('SQLite3'))
    die("SQLite 3 NOT supported.");

$session = new SpotifyWebAPI\Session(
    $spotify_api_key,
    $spotify_api_secret,
    $spotify_api_callback
);

$api = new SpotifyWebAPI\SpotifyWebAPI();

function connectDb()
{
    $db = new SQLite3('database.sqlite');
    if (!$db) {
        die($db->lastErrorMsg());
    }
    return $db;
}

function connectMemcache()
{
    $memcache = new Memcache;
    $memcache->connect('127.0.0.1', 11211);
    return $memcache;
}

function getMemcacheKey($account) {
    $memcache_key = 'spotify_carsso_account_'.$account;
    global $memcache_prefix;
    if(isset($memcache_prefix)) {
        $memcache_key = $memcache_prefix.'_'.$memcache_key;
    }
    return $memcache_key;
}

function getMemcacheData($account)
{
    $memcache_key = getMemcacheKey($account);
    $memcache = connectMemcache();
    return $memcache->get($memcache_key);
}

function pushMemcacheData($account, $data)
{
    $memcache_key = getMemcacheKey($account);
    $memcache = connectMemcache();
    $memcache->set($memcache_key, $data, 0, 9);
    $memcache_data = $memcache->get($memcache_key);
    if(!$memcache_data)
    {
        echo 'Error while getting data from memcache';
        die();
    }
    return $memcache_data;
}

function clearMemcacheData($account)
{
    $memcache_key = getMemcacheKey($account);
    $memcache = connectMemcache();
    $memcache->delete($memcache_key);
}

function checkAndCreateTables()
{
    $db = connectDb();
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
}

function addAccount($id, $email, $name, $token)
{
    $db = connectDb();
    checkAndCreateTables();
    $req = $db->prepare('INSERT OR REPLACE INTO spotify(username, email, display_name, token) VALUES (:username, :email, :display_name, :token);');
    if(!$req){
        die($db->lastErrorMsg());
    }
    $req->bindValue(':username', $id);
    $req->bindValue(':email', $email);
    $req->bindValue(':display_name', $name);
    $req->bindValue(':token', $token);
    $ret = $req->execute();
    if(!$ret){
        die($db->lastErrorMsg());
    }
}

function getToken($account)
{
    $token = null;
    $db = connectDb();
    $ret = $db->query('SELECT * FROM spotify');
    if(!$ret){
        die($db->lastErrorMsg());
    }
    while ($row = $ret->fetchArray()) {
        if($row['username'] == $account) {
            $token = $row['token'];
            break;
        }
    }
    return $token;
}
