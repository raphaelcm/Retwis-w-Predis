<?php
require_once 'retwis.php';

//echo json_encode(getAllUsers($_POST["prefix"]));
$allUsers = getAllUsers();
foreach($allUsers as $user) {
	echo $user." ";
}
?>