<?php
require_once "../config/database.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up - Make-up Class System</title>

    <link rel="stylesheet" href="../css/signup.css">
</head>

<body>

    <div class="browser-window">

        <!-- Browser Header -->
        <div class="browser-header">

            <div class="browser-dots">
                <span class="red"></span>
                <span class="yellow"></span>
                <span class="green"></span>
            </div>

            <div class="address-bar">
                app.makeupclass.edu/signup
            </div>

        </div>

        <!-- System Header -->
        <div class="system-header">
            Make-up Class System
        </div>

        <!-- Sign Up Container -->
        <div class="signup-container">

            <h1>Create Account</h1>

            <p class="subtitle">Register for the Make-up Class System</p>

            <form action="signup_process.php" method="POST">
               

                <!-- Role -->
<label for="role">Register As</label>

<select id="role" name="role" required onchange="changeRole()">
    <option value="" selected disabled>Select your role</option>
    <option value="Student">Student</option>
    <option value="Teacher">Teacher</option>
    <option value="HOD">Head of Department</option>
</select>

<!-- ID -->
<label for="user_id">ID</label>

<input
    type="text"
    id="user_id"
    name="user_id"
    placeholder="e.g. 1001"
    required
>

<!-- Name -->
<label for="name">Full Name</label>

<input
    type="text"
    id="name"
    name="name"
    placeholder="Enter your full name"
    required
>

<!-- Email -->
<label for="email">Email</label>

<input
    type="email"
    id="email"
    name="email"
    placeholder="example@email.com"
    required
>

<!-- Phone -->
<label for="phone">Phone Number</label>

<input
    type="text"
    id="phone"
    name="phone"
    placeholder="01XXXXXXXXX"
    required
>

<!-- Address -->
<label for="address">Address</label>

<input
    type="text"
    id="address"
    name="address"
    placeholder="Enter your address"
    required
>

<!-- =========================
     Student Class
     ========================= -->

<div id="student-class-section" class="conditional-section">

    <label for="class_id">Class</label>

    <select id="class_id" name="class_id">

        <option value="" selected disabled>
            Select your class
        </option>

        <?php

        require_once "../config/database.php";

        $sql = "
            SELECT class_id, course_name, section, schedule
            FROM Class
            ORDER BY class_id
        ";

        $stmt = oci_parse($conn, $sql);

        oci_execute($stmt);

        while ($row = oci_fetch_assoc($stmt)) {

            echo '<option value="' . $row["CLASS_ID"] . '">';

            echo $row["COURSE_NAME"]
                . " - Section "
                . $row["SECTION"]
                . " - "
                . $row["SCHEDULE"];

            echo '</option>';
        }

        ?>

    </select>

</div>

<!-- =========================
     Teacher Department
     ========================= -->

<div id="teacher-department-section" class="conditional-section">

    <label for="dept_id">Department</label>

    <select id="dept_id" name="dept_id">

        <option value="" selected disabled>
            Select your department
        </option>

        <?php

        $sql = "
            SELECT dept_id, dept_name
            FROM Department
            ORDER BY dept_id
        ";

        $stmt = oci_parse($conn, $sql);

        oci_execute($stmt);

        while ($row = oci_fetch_assoc($stmt)) {

            echo '<option value="' . $row["DEPT_ID"] . '">';

            echo $row["DEPT_ID"]
                . " - "
                . $row["DEPT_NAME"];

            echo '</option>';
        }

        ?>

    </select>

</div>

                
                <!-- Password -->
                <label for="password">Password</label>

                <div class="password-container">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('password', this)"
                        aria-label="Show or hide password"
                    >
                        👁
                    </button>

                </div>

                <!-- Confirm Password -->
                <label for="confirm_password">Confirm Password</label>

                <div class="password-container">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword('confirm_password', this)"
                        aria-label="Show or hide password"
                    >
                        👁
                    </button>

                </div>

                <!-- Sign Up Button -->
                <button
                    type="submit"
                    class="signup-button"
                >
                    Sign Up
                </button>

                <?php if (isset($_GET["success"])): ?>

                 <div class="success-message">
                     <?php echo htmlspecialchars($_GET["success"]); ?>
                 </div>

                <?php endif; ?>

            </form>

            <!-- Login Link -->
            <div class="login-section">

                <p>Already have an account?</p>

                <a href="login.php">Log In</a>

            </div>

        </div>

    </div>

    <script src="../js/signup.js"></script>

</body>
</html>