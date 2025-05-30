<?php
$host = 'localhost';
$db = 'u106289951_rh_bot';
$user = 'u106289951_rh_bot';
$pass = '9M1HS5Io|';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
