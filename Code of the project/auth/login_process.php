<?php

session_start();

require_once "../config/database.php";

/* Get form data */

$role = $_POST["role"] ?? "";
$identifier = trim($_POST["identifier"] ?? "");
$password = $_POST["password"] ?? "";


/* Check required fields */

if ($role == "" || $identifier == "" || $password == "") {
    die("Please fill in all fields.");
}


/* Convert login role to database role */

if ($role == "student") {
    $database_role = "Student";
}
elseif ($role == "teacher") {
    $database_role = "Teacher";
}
elseif ($role == "hod") {
    $database_role = "HOD";
}
else {
    die("Invalid user role.");
}


/* Check whether identifier is ID or email */

if (is_numeric($identifier)) {

    /* Login using User ID */

    $user_id = (int)$identifier;

    $sql = "
        SELECT
            account_id,
            user_role,
            user_id,
            email,
            password_hash
        FROM UserAccount
        WHERE user_role = :role
        AND user_id = :user_id
    ";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":role", $database_role);
    oci_bind_by_name($stmt, ":user_id", $user_id);

}
else {

    /* Login using Email */

    $email = $identifier;

    $sql = "
        SELECT
            account_id,
            user_role,
            user_id,
            email,
            password_hash
        FROM UserAccount
        WHERE user_role = :role
        AND email = :email
    ";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":role", $database_role);
    oci_bind_by_name($stmt, ":email", $email);
}


/* Execute query */

oci_execute($stmt);


/* Check account */

$row = oci_fetch_assoc($stmt);

if (!$row) {
    die("Invalid ID/email or password.");
}


/* Check password */

if (!password_verify($password, $row["PASSWORD_HASH"])) {
    die("Invalid ID/email or password.");
}


/* Create login session */

$_SESSION["account_id"] = $row["ACCOUNT_ID"];
$_SESSION["user_role"] = $row["USER_ROLE"];
$_SESSION["user_id"] = $row["USER_ID"];
$_SESSION["email"] = $row["EMAIL"];


/* Redirect according to role */

if ($row["USER_ROLE"] == "Student") {

    header("Location: ../student/dashboard.php");

}
elseif ($row["USER_ROLE"] == "Teacher") {

    header("Location: ../teacher/dashboard.php");

}
elseif ($row["USER_ROLE"] == "HOD") {

    header("Location: ../hod/dashboard.php");

}

exit;

?>