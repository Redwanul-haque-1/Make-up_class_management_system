<?php

$username = "MAKEUP_CLASS_MANAGEMENT";
$password = "makeup123";
$connection_string = "localhost:1521/XE";

$conn = oci_connect($username, $password, $connection_string);

if (!$conn) {
    $error = oci_error();
    die("Database connection failed: " . $error['message']);
}

// echo "Oracle Database Connected Successfully!";
?>