<?php
require 'functions.php';

if(!isset($_GET['account'])) {
    die('Missing account');
}

$account = $_GET['account'];
$memcache_data = getMemcacheData($account);
$from_cache = 1;
$size = mt_rand(100,800);
$default = array(
    'cover' => 'https://placekitten.com/'.$size.'/'.$size,
    'track' => 'Nothing is playing',
    'artist' => '',
    'album' => '',
    'link' => '',
    'progress' => 0,
    'duration' => 0,
);
if(!$memcache_data)
{
    $token = getToken($account);
    if(!$token) {
        die('Invalid account, please <a href="link.php">link it</a> first');
    }
    $playback = null;
    $last_played = null;
    try {
        $session->refreshAccessToken($token);
        $accessToken = $session->getAccessToken();
        $api->setAccessToken($accessToken);
        $playback = json_decode(json_encode($api->getMyCurrentPlaybackInfo()), true);
        if(!$playback['item']) {
            $recently_played = json_decode(json_encode($api->getMyRecentTracks(array('limit' => 1))), true);
            if($recently_played
                and $recently_played['items']
                and count($recently_played['items'])
                and $recently_played['items'][0]
                and $recently_played['items'][0]['track']
                and $recently_played['items'][0]['track']['id']) {
                $last_played = json_decode(json_encode($api->getTrack($recently_played['items'][0]['track']['id'])), true);
            }
        }
    } catch (SpotifyWebAPI\SpotifyWebAPIException $e) {
        $default['cover'] = 'https://i.pinimg.com/236x/53/64/47/536447f45d027600878374901c7d7768--tiger-drawing-pen-drawings.jpg';
        if($e->getMessage() == 'Backend respond with 500') {
            $default['track'] = 'Cannot retreive current track';
            $default['artist'] = 'Spotify API is broken: 500 error';
            $default['album'] = 'If current track is a podcast, this is "normal"';
        } elseif($e->getMessage() == 'Insufficient client scope' or $e->getMessage() == 'Permissions missing' or $e->getMessage() == 'Refresh token revoked') {
            $default['track'] = 'Missing authorization on your account';
            $default['artist'] = 'Please link your spotify account again';
            $default['album'] = 'Click here to re-link your account';
            $default['link'] = 'javascript:openLink(\'action.php?action=relink&account='.$account.'\')';
        } else {
            $default['track'] = 'Spotify API ERROR';
            $default['artist'] = $e->getMessage();
            $default['album'] = $e->getTraceAsString();
        }
    }
    $memcache_data = pushMemcacheData($account, array('playback' => $playback, 'last_played' => $last_played, 'default' => $default, 'at' => time()));
    $from_cache = 0;
}
$playback = $memcache_data['playback']; 
$last_played = $memcache_data['last_played']; 
$default = $memcache_data['default']; 
$at = $memcache_data['at']; 

$cover = $default['cover'];
$trackname = $default['track'];
$artist = $default['artist'];
$album = $default['album'];
$link = $default['link'];
$progress = $default['progress'];
$duration = $default['duration'];

$is_playing = false;
if($playback['is_playing']) {
    $is_playing = true;
}
$is_private_session = false;
if($playback['device']['is_private_session']) {
    $is_private_session = true;
}

$track = $playback['item'];
if(!$track) {
    $track = $last_played;
}
if($track) {
    $artists = array();
    if(isset($track['artists']))
    {
        foreach($track['artists'] as $artist)
        {
            $artists[] = $artist['name'];
        }
    }
    $artist = join(', ', $artists);
    $cover = $track['album']['images'][1]['url'];
    $album = $track['album']['name'];
    $trackname = $track['name'];
    $link = $track['external_urls']['spotify'];
    $duration = $track['duration_ms']/1000;
    if($playback && $playback['progress_ms'])
    {
        $progress = $playback['progress_ms']/1000;
        if($is_playing) {
            $progress += (time()-$at);
        }
    }
    if($is_private_session)
    {
        $cover = $default['cover'];
        $trackname = '*private session*';
        $artist = '*private*';
        $album = '*private*';
        $link = $default['link'];
    }
}
$progress_percent = 0;
if($progress) {
    $progress_percent = ($progress/$duration)*100;
    if($progress_percent > 100) {
        $progress_percent = 100;
    }
}
$duration_human = floor($duration/60).':'.sprintf("%'.02d", round($duration-floor($duration/60)*60));
$progress_human = floor($progress/60).':'.sprintf("%'.02d", round($progress-floor($progress/60)*60));
?>
<html>
<head>
<meta http-equiv="refresh" content="15">
<title>Spotify Now Playing</title>
<script type="text/javascript">
    var progress = <?php echo $progress ?>;
    var duration = <?php echo $duration ?>;
    var is_playing = <?php echo ($is_playing)?'true':'false' ?>;
    if(duration && is_playing) {
        setInterval(function(){
            progress = progress+0.1;
            if(progress > duration) {
                progress = duration;
            }
            var progress_percent = 0;
            progress_percent = (progress/duration)*100;
            var progress_human = Math.floor(progress/60)+':'+(Math.round(progress-Math.floor(progress/60)*60)+'').padStart(2, '0');
            document.getElementById('player-current-progress').style.width = progress_percent+'%';
            document.getElementById('player-progress-text').innerHTML = progress_human;
        }, 100);
    }
    function openLink(url) {
        var width = 400;
        var height = 600;
        var left = screen.width / 2 - width / 2;
        var top = screen.height / 2 - height / 2;
        var popup = window.open(
            url,
            'Spotify',
            'menubar=no,location=no,resizable=no,scrollbars=no,status=no,width='+width+',height='+height+',top='+top+',left='+left
        );
        popup.onunload = function(){
            window.location.reload();
        }
    }
</script>
<style>
    @font-face {
        font-family: 'spotify-circular';
        src: url('https://open.scdn.co/fonts/CircularSpUIv3T-Light.woff2') format('woff2'), url('https://open.scdn.co/fonts/CircularSpUIv3T-Light.woff') format('woff'), url('https://open.scdn.co/fonts/CircularSpUIv3T-Light.ttf') format('ttf');
        font-weight: 200;
        font-style: normal;
    }
    @font-face {
        font-family: 'spotify-circular';
        src: url('https://open.scdn.co/fonts/CircularSpUIv3T-Book.woff2') format('woff2'), url('https://open.scdn.co/fonts/CircularSpUIv3T-Book.woff') format('woff'), url('https://open.scdn.co/fonts/CircularSpUIv3T-Book.ttf') format('ttf');
        font-weight: 400;
        font-style: normal;
    }
    @font-face {
        font-family: 'spotify-circular';
        src: url('https://open.scdn.co/fonts/CircularSpUIv3T-Bold.woff2') format('woff2'), url('https://open.scdn.co/fonts/CircularSpUIv3T-Bold.woff') format('woff'), url('https://open.scdn.co/fonts/CircularSpUIv3T-Bold.ttf') format('ttf');
        font-weight: 600;
        font-style: normal;
    }
    body {
        margin: 0;
        font-family: spotify-circular, sans-serif;
        font-size: 12px;
        font-weight: 300;
    }
    .player {
        display: flex;
        flex-direction: row;
        background-color: #282828;
        color: #fff;
    }
    .track-name {
        color: #fff;
        font-size: 14px;
        height: 18px;
        display: block;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .track-artist {
        color: rgba(255, 255, 255, 0.6);
        font-size: 14px;
        height: 18px;
        display: block;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .track-album {
        color: rgba(255, 255, 255, 0.4);
        margin-top: 2px;
        font-size: 12px;
        height: 15px;
        display: block;
        text-decoration: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .player-left {
        width: 80px;
        height: 80px;
        background-position-x: 50%;
        background-position-y: 50%;
        background-size: contain;
    }
    .player-center {
        height: 80px;
        width: calc(100% - 160px);
    }
    .player-right {
        width: 80px;
        height: 80px;
        position: absolute;
        right: 0;
    }
    .player-content {
        margin: 6px 10px;
    }
    .player-logo {
        margin: 20px 20px 0 20px;
        height: 40px;
        width: 40px;
    }
    .player-progress {
        color: rgba(255, 255, 255, 0.4);
        margin-top: 5px;
        font-size: 8px;
        display: flex;
        text-align: center;
    }
    .player-progressbar {
        margin-top: 3px;
        width: calc(100% - 80px);
        background-color: rgba(255, 255, 255, 0.3);
        height: 4px;
        border-radius: 2px;
    }
    .player-progressbar-inner {
        height: 4px;
        border-radius: 2px;
        width: 0%;
    }
    .player-playing .player-progressbar-inner {
        background-color: rgb(46, 189, 89);
    }
    .player-paused .player-progressbar-inner {
        background-color: rgb(255, 255, 255);
    }
    #player-progress-text {
        width: 40px;
    }
    #player-duration-text {
        width: 40px;
    }
    .player-controls {
        margin: 19px; 
        display: flex;
    }
    .player-control {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.5);
        border-width: 1px;
        border-style: solid;
        border-color: white;
        cursor: pointer;
    }
    .player-control:hover {
        transform: scale(1.1);
    }
    .player-control-inner {
        margin: 8px;
    }
    .player-playing .player-control-pause {
        display: none;
    }
    .player-paused .player-control-play {
        display: none;
    }
    .almost-invisible {
        color: #4a4a4a;
        font-size: 6px;
        text-align: center;
        position: absolute;
        bottom: 4px;
        width: 80px;
    }
</style>
</head>
<body>
<div id="main">
    <div class="player <?php echo ($is_playing)?'player-playing':'player-paused'; ?>">
        <div class="player-left" style="background-image:url(<?php echo $cover ?>)">
            <div class="player-controls">
                <div class="player-control player-control-pause" onclick="openLink('action.php?action=play&account=<?php echo $account ?>')">
                    <div class="player-control-inner">
                        <svg viewBox="0 0 26 26" xmlns="http://www.w3.org/2000/svg"><path d="M7.712 22.04a.732.732 0 0 1-.806.007.767.767 0 0 1-.406-.703V4.656c0-.31.135-.544.406-.703.271-.16.54-.157.806.006l14.458 8.332c.266.163.4.4.4.709 0 .31-.134.546-.4.71L7.712 22.04z" fill="currentColor" fill-rule="evenodd"></path></svg>
                    </div>
                </div>
                <div class="player-control player-control-play" onclick="openLink('action.php?action=pause&account=<?php echo $account ?>')">
                    <div class="player-control-inner">
                        <svg viewBox="0 0 26 26" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor" fill-rule="evenodd"><rect x="5" y="3" width="5" height="20" rx="1"></rect><rect x="16" y="3" width="5" height="20" rx="1"></rect></g></svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="player-center">
            <div class="player-content">
                <a href="<?php echo $link ?>" target="_blank" class="track-name"><?php echo $trackname ?></a>
                <a href="<?php echo $link ?>" target="_blank" class="track-artist"><?php echo $artist ?></a>
                <a href="<?php echo $link ?>" target="_blank" class="track-album"><?php echo $album ?></a>
                <div class="player-progress">
                    <div id="player-progress-text"><?php echo $progress_human ?></div>
                    <div class="player-progressbar">
                        <div class="player-progressbar-inner" id="player-current-progress" style="width: <?php echo $progress_percent ?>%"></div>
                    </div>
                    <div id="player-duration-text"><?php echo $duration_human ?></div>
                </div>
            </div>
        </div>
        <div class="player-right">
            <div class="player-logo">
                <a href="<?php echo $link ?>" target="_blank">
                    <svg viewBox="0 0 64 64" style="filter: drop-shadow(rgba(0, 0, 0, 0.2) 0px 0px 2px);"><path fill="#2ebd59" d="M50.9288482,28.3686995 C40.6139223,22.2429099 23.5996824,21.6796628 13.7528525,24.668235 C12.1712611,25.1477974 10.4991074,24.2551616 10.020312,22.674325 C9.54075242,21.0919599 10.4326187,19.4209426 12.0153564,18.9402338 C23.3188247,15.5091649 42.1091547,16.1717636 53.9838913,23.2207587 C55.4065211,24.0648651 55.8727065,25.9021053 55.0297516,27.3220687 C54.1860323,28.7439428 52.3476568,29.213188 50.9288482,28.3686995 M50.5910548,37.4417921 C49.8677032,38.6160515 48.3323482,38.9840345 47.1592422,38.2633534 C38.5600315,32.9774668 25.4464645,31.4459238 15.2725406,34.5342297 C13.953465,34.9331646 12.5598763,34.1887919 12.1594154,32.8720012 C11.7616293,31.5529179 12.5056154,30.1616135 13.8227803,29.7607681 C25.4453181,26.2337867 39.8932454,27.9418698 49.7702628,34.0118696 C50.9429866,34.7340793 51.3121138,36.2702077 50.5910548,37.4417921 M46.6754793,46.1553085 C46.1003899,47.0983843 44.8726409,47.3937643 43.9330097,46.8190535 C36.4186355,42.2263353 26.9604208,41.1892576 15.8216459,43.7334225 C14.748273,43.9798909 13.6787213,43.3069749 13.4337829,42.2335956 C13.1876981,41.1605985 13.8579352,40.0906583 14.9336008,39.8457184 C27.1232036,37.0589064 37.5791315,38.2583858 46.0140309,43.4124404 C46.9544263,43.986769 47.2505687,45.2152896 46.6754793,46.1553085 M31.9998089,0 C14.3271776,0 0,14.3268811 0,31.9996179 C0,49.6742653 14.3271776,64 31.9998089,64 C49.6732045,64 64,49.6742653 64,31.9996179 C64,14.3268811 49.6732045,0 31.9998089,0"></path></svg>
                </a>
            </div>
            <div class="almost-invisible">From cache: <?php echo ($from_cache)?'yes':'no' ?></div>
        </div>
    </div>
</div>
</body>
</html>
