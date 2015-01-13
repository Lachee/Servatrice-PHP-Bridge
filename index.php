<?php 

include_once('servatrice.class.php');

$servatrice = new Servatrice("localhost", "cockatrice", "root", "");

$errors = $servatrice->registerUser("Bacon", "bacon@bacons.com", "baconistasty", "", 'r', '', true, '');
var_dump($errors);



$user = $servatrice->getAuthicatedUser("Bacon", "baconistasty");
var_dump($user);

?>