<?php

session_start();

require_once "../config/database.php";

/* Get form data */

$class_id = $_POST["class_id"] ?? "";
$dept_id = $_POST["dept_id"] ?? "";

$role = $_POST["role"] ?? "";
$user_id = trim($_POST["user_id"] ?? "");
$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");
$password = $_POST["password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";


/* Check required fields */

if (
    $role == "" ||
    $user_id == "" ||
    $name == "" ||
    $email == "" ||
    $phone == "" ||
    $address == "" ||
    $password == "" ||
    $confirm_password == ""
) {
    die("Please fill in all fields.");
}


/* Check password */

if ($password !== $confirm_password) {
    die("Passwords do not match.");
}


/* Check password length */

if (strlen($password) < 6) {
    die("Password must contain at least 6 characters.");
}


/* Check valid role */

if (
    $role != "Student" &&
    $role != "Teacher" &&
    $role != "HOD"
) {
    die("Invalid user role.");
}


/* Check class for Student */

if ($role == "Student" && $class_id == "") {
    die("Please select a class.");
}


/* Check department for Teacher */

if ($role == "Teacher" && $dept_id == "") {
    die("Please select a department.");
}


/* Check numeric user ID */

if (!is_numeric($user_id)) {
    die("User ID must contain numbers only.");
}


/* Check numeric class ID */

if ($class_id != "" && !is_numeric($class_id)) {
    die("Invalid class ID.");
}


/* Check numeric department ID */

if ($dept_id != "" && !is_numeric($dept_id)) {
    die("Invalid department ID.");
}


/* Convert IDs to numbers */

$user_id = (int)$user_id;

if ($class_id != "") {
    $class_id = (int)$class_id;
}

if ($dept_id != "") {
    $dept_id = (int)$dept_id;
}


/* Check whether account already exists */

$sql = "
    SELECT account_id
    FROM UserAccount
    WHERE email = :email
       OR (user_role = :role AND user_id = :user_id)
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name($stmt, ":email", $email);
oci_bind_by_name($stmt, ":role", $role);
oci_bind_by_name($stmt, ":user_id", $user_id);

oci_execute($stmt);

if (oci_fetch($stmt)) {
    die("An account with this email or ID already exists.");
}


/* Start registration */

try {

    /* =====================================
       1. Insert into Student
       ===================================== */

    if ($role == "Student") {

        /* Check whether class exists */

        $sql = "
            SELECT class_id
            FROM Class
            WHERE class_id = :class_id
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":class_id", $class_id);

        oci_execute($stmt);

        if (!oci_fetch($stmt)) {
            die("Selected class does not exist.");
        }


        /* Insert Student */

        $sql = "
            INSERT INTO Student
            (
                s_id,
                s_name,
                s_address,
                class_id
            )
            VALUES
            (
                :user_id,
                :name,
                :address,
                :class_id
            )
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":name", $name);
        oci_bind_by_name($stmt, ":address", $address);
        oci_bind_by_name($stmt, ":class_id", $class_id);

        oci_execute($stmt);


        /* Student Email */

        $sql = "
            INSERT INTO StudentEmail
            (
                s_id,
                s_email
            )
            VALUES
            (
                :user_id,
                :email
            )
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":email", $email);

        oci_execute($stmt);


        /* Student Phone */

        $sql = "
            INSERT INTO StudentPhone
            (
                s_id,
                s_phone
            )
            VALUES
            (
                :user_id,
                :phone
            )
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":phone", $phone);

        oci_execute($stmt);
    }


    /* =====================================
       2. Insert into Teacher
       ===================================== */

    elseif ($role == "Teacher") {

        /* Check whether department exists */

        $sql = "
            SELECT dept_id
            FROM Department
            WHERE dept_id = :dept_id
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":dept_id", $dept_id);

        oci_execute($stmt);

        if (!oci_fetch($stmt)) {
            die("Selected department does not exist.");
        }


        $designation = "Teacher";


        /* Insert Teacher */

        $sql = "
            INSERT INTO Teacher
            (
                t_id,
                t_name,
                designation,
                t_address,
                t_email,
                dept_id
            )
            VALUES
            (
                :user_id,
                :name,
                :designation,
                :address,
                :email,
                :dept_id
            )
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":name", $name);
        oci_bind_by_name($stmt, ":designation", $designation);
        oci_bind_by_name($stmt, ":address", $address);
        oci_bind_by_name($stmt, ":email", $email);
        oci_bind_by_name($stmt, ":dept_id", $dept_id);

        oci_execute($stmt);


        /* Teacher Phone */

        $sql = "
            INSERT INTO TeacherPhone
            (
                t_id,
                t_phone
            )
            VALUES
            (
                :user_id,
                :phone
            )
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":phone", $phone);

        oci_execute($stmt);
    }


    /* =====================================
       3. Insert into HOD
       ===================================== */

    elseif ($role == "HOD") {

        $sql = "
            INSERT INTO HOD
            (
                hod_id,
                hod_name,
                hod_email,
                hod_phone
            )
            VALUES
            (
                :user_id,
                :name,
                :email,
                :phone
            )
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":name", $name);
        oci_bind_by_name($stmt, ":email", $email);
        oci_bind_by_name($stmt, ":phone", $phone);

        oci_execute($stmt);
    }


    /* =====================================
       4. Create password hash
       ===================================== */

    $password_hash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    /* =====================================
       5. Get new account ID
       ===================================== */

    $sql = "
        SELECT useraccount_seq.NEXTVAL AS account_id
        FROM dual
    ";

    $stmt = oci_parse($conn, $sql);

    oci_execute($stmt);

    $row = oci_fetch_assoc($stmt);

    $account_id = $row["ACCOUNT_ID"];


    /* =====================================
       6. Insert into UserAccount
       ===================================== */

    $sql = "
        INSERT INTO UserAccount
        (
            account_id,
            user_role,
            user_id,
            email,
            password_hash
        )
        VALUES
        (
            :account_id,
            :role,
            :user_id,
            :email,
            :password_hash
        )
    ";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":account_id", $account_id);
    oci_bind_by_name($stmt, ":role", $role);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_bind_by_name($stmt, ":email", $email);
    oci_bind_by_name($stmt, ":password_hash", $password_hash);

    oci_execute($stmt);


    /* =====================================
       7. Save everything
       ===================================== */

    oci_commit($conn);


    /* Redirect to login */

    header("Location: signup.php?success=Signup successfully done");

    exit;


} catch (Exception $e) {

    oci_rollback($conn);

    die("Registration failed: " . $e->getMessage());
}

?>