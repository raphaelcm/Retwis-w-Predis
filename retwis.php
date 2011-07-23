<?php
require_once '../predis/lib/Predis.php';

function getrand() {
    $fd = fopen("/dev/urandom","r");
    $data = fread($fd,16);
    fclose($fd);
    return md5($data);
}

function isLoggedIn() {
    global $User, $_COOKIE;

    if (isset($User)) return true;

    if (isset($_COOKIE['auth'])) {
        $r = redisLink();
        $authcookie = $_COOKIE['auth'];
        if ($userid = $r->get("auth:$authcookie")) {
            if ($r->get("uid:$userid:auth") != $authcookie) return false;
            loadUserInfo($userid);
            return true;
        }
    }
    return false;
}

function loadUserInfo($userid) {
    global $User;

    $r = redisLink();
    $User['id'] = $userid;
    $User['username'] = $r->get("uid:$userid:username");
    return true;
}


function redisLink() {
    static $r = false;

    if ($r) return $r;
    
	$single_server = array(
	    'host'     => '127.0.0.1', 
	    'port'     => 6379, 
	    'database' => 15
	);

	$r = new Predis\Client($single_server);

    return $r;
}


# Access to GET/POST/COOKIE parameters the easy way
function g($param) {
    global $_GET, $_POST, $_COOKIE;

    if (isset($_COOKIE[$param])) return $_COOKIE[$param];
    if (isset($_POST[$param])) return $_POST[$param];
    if (isset($_GET[$param])) return $_GET[$param];
    return false;
}

function gt($param) {
    $val = g($param);
    if ($val === false) return false;
    return trim($val);
}

function utf8entities($s) {
    return htmlentities($s,ENT_COMPAT,'UTF-8');
}

function goback($msg) {
    include("header.php");
    echo('<div id ="error">'.utf8entities($msg).'<br>');
    echo('<a href="javascript:history.back()">Please go back and try again</a></div>');
    include("footer.php");
    exit;
}

function strElapsed($t) {
    $d = time()-$t;
    if ($d < 60) return "$d seconds";
    if ($d < 3600) {
        $m = (int)($d/60);
        return "$m minute".($m > 1 ? "s" : "");
    }
    if ($d < 3600*24) {
        $h = (int)($d/3600);
        return "$h hour".($h > 1 ? "s" : "");
    }
    $d = (int)($d/(3600*24));
    return "$d day".($d > 1 ? "s" : "");
}

function showPost($id) {
    $r = redisLink();
    $postdata = $r->get("post:$id");
    if (!$postdata) return false;

    $aux = explode("|",$postdata);
    $id = $aux[0];
    $time = $aux[1];
    $username = $r->get("uid:$id:username");
    $post = join(array_splice($aux,2,count($aux)-2),"|");
    $elapsed = strElapsed($time);
    $userlink = "<a class=\"username\" href=\"profile.php?u=".urlencode($username)."\">".utf8entities($username)."</a>";

    echo('<div class="post">'.$userlink.' '.utf8entities($post)."<br>");
    echo('<i>posted '.$elapsed.' ago via web</i></div>');
    return true;
}

/*
addPostsToTimeline

Merge $userid's posts with existing set of posts and sort chronologically (by PostID)
*/
function addPostsToTimeline($userid, $timeline, $start, $count) {
	$r = redisLink();
	$posts = $r->lrange("uid:$userid:posts",$start,$start+$count);
	foreach($posts as $p) {
		$timeline[] = $p;
	}
	rsort($timeline, SORT_NUMERIC);
	return($timeline);
}

/*
getRelevantPosts

Returns array of user's and followee's posts, sorted chronologically.
*/
function getRelevantPosts($userid,$start,$count) {
	$r = redisLink();
	$followees = $r->smembers("uid:"."$userid".":following"); //get userids of all followees
	$posts = $r->lrange("uid:$userid:posts",$start,$start+$count);
	foreach($followees as $f) {
		$posts = addPostsToTimeline($f, $posts, $start, $count);
	}
	
	return $posts;
}

/*
showUserPosts

Show only the user's posts if $includeFollowees if false.
Otherwise, show user's and followees' posts.
*/
function showUserPosts($userid,$start,$count,$includeFollowees) {
    $r = redisLink();
    $key = ($userid == -1) ? "global:timeline" : "uid:$userid:posts";
	if($includeFollowees)
		$posts = getRelevantPosts($userid,$start,$count);
	else
		$posts = $r->lrange($key,$start,$start+$count);
    $c = 0;
    foreach($posts as $p) {
        if (showPost($p)) $c++;
        if ($c == $count) break;
    }
    return count($posts) == $count+1;
}

/*
showUserPostsWithPagination

Shows own and followee's posts, formatted and paginated.

$username - User's names
$userid - User's id
$start - Where to start from
$count - How many to show per page
$includeFollowees - include user's followees?
*/
function showUserPostsWithPagination($username,$userid,$start,$count,$includeFollowees) {
    global $_SERVER;
    $thispage = $_SERVER['PHP_SELF'];

    $navlink = "";
    $next = $start+10;
    $prev = $start-10;
    $nextlink = $prevlink = false;
    if ($prev < 0) $prev = 0;

    $u = $username ? "&u=".urlencode($username) : "";
	if (showUserPosts($userid,$start,$count,$includeFollowees))
        $nextlink = "<a href=\"$thispage?start=$next".$u."\">Older posts &raquo;</a>";
    if ($start > 0) {
        $prevlink = "<a href=\"$thispage?start=$prev".$u."\">&laquo; Newer posts</a>".($nextlink ? " | " : "");
    }
    if ($nextlink || $prevlink)
        echo("<div class=\"rightlink\">$prevlink $nextlink</div>");
}

function showLastUsers() {
    $r = redisLink();
    $users = $r->sort("global:users", array('GET' => 'uid:*:username', 
											'DESC' => 0,
											'LIMIT' => array(0 => '0',
															 1 => '10')));
	
    echo("<div>");
	foreach($users as $u) {
        echo("<a class=\"username\" href=\"profile.php?u=".urlencode($u)."\">".utf8entities($u)."</a> ");
    }
    echo("</div><br>");
}

?>
