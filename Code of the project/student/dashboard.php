<?php

session_start();

require_once "../config/database.php";


/* =====================================
   Check Student Login
   ===================================== */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "Student"
) {
    header("Location: ../auth/login.php");
    exit;
}

$student_id = $_SESSION["user_id"];


/* =====================================
   Available Time Slots
   ===================================== */

$time_slots = [
    "Mon 2 to 3pm",
    "Tue 10 to 11am",
    "Wed 1 to 2pm",
    "Thu 3 to 4pm",
    "Fri 11 to 12pm",
    "Sat 9 to 10am"
];


/* =====================================
   Submit Availability
   ===================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST["availability"])) {

        $selected_slots = $_POST["availability"];

        try {

            /*
             * Remove previous availability
             */

            $sql = "
                DELETE FROM StudentAvailability
                WHERE s_id = :student_id
            ";

            $stmt = oci_parse($conn, $sql);

            oci_bind_by_name(
                $stmt,
                ":student_id",
                $student_id
            );

            oci_execute($stmt);


            /*
             * Insert new availability
             */

            foreach ($selected_slots as $slot) {

                if (!in_array($slot, $time_slots)) {
                    continue;
                }

                $sql = "
                    INSERT INTO StudentAvailability
                    (
                        s_id,
                        time_slot
                    )
                    VALUES
                    (
                        :student_id,
                        :time_slot
                    )
                ";

                $stmt = oci_parse($conn, $sql);

                oci_bind_by_name(
                    $stmt,
                    ":student_id",
                    $student_id
                );

                oci_bind_by_name(
                    $stmt,
                    ":time_slot",
                    $slot
                );

                oci_execute($stmt);
            }


            oci_commit($conn);

            $availability_message =
                "Your availability has been submitted successfully.";

        } catch (Exception $e) {

            oci_rollback($conn);

            $availability_error =
                "Failed to submit availability.";
        }

    } else {

        /*
         * Student submitted no selected slots.
         */

        $sql = "
            DELETE FROM StudentAvailability
            WHERE s_id = :student_id
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name(
            $stmt,
            ":student_id",
            $student_id
        );

        oci_execute($stmt);

        oci_commit($conn);

        $availability_message =
            "Your availability has been cleared.";
    }
}


/* =====================================
   Get Student Information
   ===================================== */

$sql = "
    SELECT
        s.s_id,
        s.s_name,
        s.s_address,
        s.class_id,
        e.s_email
    FROM Student s
    LEFT JOIN StudentEmail e
        ON s.s_id = e.s_id
    WHERE s.s_id = :student_id
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":student_id",
    $student_id
);

oci_execute($stmt);

$student = oci_fetch_assoc($stmt);


if (!$student) {
    die("Student information not found.");
}


/* =====================================
   Get Student Class Information
   ===================================== */

$class = null;

if ($student["CLASS_ID"] != "") {

    $class_id = $student["CLASS_ID"];

    $sql = "
        SELECT
            class_id,
            course_name,
            section,
            schedule
        FROM Class
        WHERE class_id = :class_id
    ";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name(
        $stmt,
        ":class_id",
        $class_id
    );

    oci_execute($stmt);

    $class = oci_fetch_assoc($stmt);
}


/* =====================================
   Get Student Availability
   ===================================== */

$selected_slots = [];

$sql = "
    SELECT time_slot
    FROM StudentAvailability
    WHERE s_id = :student_id
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":student_id",
    $student_id
);

oci_execute($stmt);

while ($row = oci_fetch_assoc($stmt)) {

    $selected_slots[] = $row["TIME_SLOT"];
}


/* =====================================
   Get Notifications
   ===================================== */

$notifications = [];

$sql = "
    SELECT
        notif_id,
        message,
        sent_datetime
    FROM Notification
    WHERE s_id = :student_id
    ORDER BY sent_datetime DESC
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":student_id",
    $student_id
);

oci_execute($stmt);

while ($row = oci_fetch_assoc($stmt)) {

    $notifications[] = $row;
}


/* =====================================
   Get Upcoming Make-up Classes
   ===================================== */

$upcoming_classes = [];

$sql = "
    SELECT
        r.req_id,
        r.requested_date,
        r.proposed_time,
        r.status,

        c.class_id,
        c.course_name,
        c.section,

        room.room_id,
        room.building_name

    FROM MakeupClassRequest r

    INNER JOIN Class c
        ON r.class_id = c.class_id

    LEFT JOIN Classroom room
        ON r.room_id = room.room_id

    INNER JOIN Student s
        ON s.class_id = c.class_id

    WHERE s.s_id = :student_id

    AND UPPER(r.status) IN ('APPROVED', 'CONFIRMED')

    ORDER BY
        r.requested_date ASC,
        r.proposed_time ASC
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":student_id",
    $student_id
);

oci_execute($stmt);

while ($row = oci_fetch_assoc($stmt)) {

    $upcoming_classes[] = $row;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Student Dashboard - Make-up Class System
    </title>

    <link
        rel="stylesheet"
        href="../css/student-dashboard.css"
    >

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

            app.makeupclass.edu/student/home

        </div>

    </div>


    <!-- System Header -->

    <div class="system-header">

        <div class="system-title">

            Make-up Class System

        </div>


        <div class="header-right">

            <span>
                Student Dashboard
            </span>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>


    <!-- Main Content -->

    <main class="dashboard-container">


        <!-- Welcome -->

        <section class="welcome-section">

            <div>

                <h1>
                    Welcome,
                    <?php echo htmlspecialchars($student["S_NAME"]); ?>!
                </h1>

                <p>
                    Manage your availability and stay updated
                    with your make-up classes.
                </p>

            </div>


            <div class="student-badge">

                <div class="student-icon">
                    👤
                </div>

                <div>

                    <strong>
                        ID:
                        <?php echo htmlspecialchars($student["S_ID"]); ?>
                    </strong>

                    <span>
                        Student
                    </span>

                </div>

            </div>

        </section>


        <!-- =====================================
             Set My Free Time
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Set My Free Time
            </h2>

            <p class="section-description">
                Select the time slots when you are available
                for a make-up class.
            </p>


            <?php if (isset($availability_message)): ?>

                <div class="success-message">

                    <?php
                    echo htmlspecialchars($availability_message);
                    ?>

                </div>

            <?php endif; ?>


            <?php if (isset($availability_error)): ?>

                <div class="error-message">

                    <?php
                    echo htmlspecialchars($availability_error);
                    ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                id="availability-form"
            >


                <div class="time-slots">


                    <?php foreach ($time_slots as $slot): ?>

                        <?php
                        $is_selected =
                            in_array($slot, $selected_slots);
                        ?>


                        <label
                            class="time-slot
                            <?php
                            if ($is_selected) {
                                echo " selected";
                            }
                            ?>"
                        >

                            <input
                                type="checkbox"
                                name="availability[]"
                                value="<?php
                                echo htmlspecialchars($slot);
                                ?>"
                                <?php
                                if ($is_selected) {
                                    echo "checked";
                                }
                                ?>
                            >

                            <span>
                                <?php
                                echo htmlspecialchars($slot);
                                ?>
                            </span>

                        </label>

                    <?php endforeach; ?>


                </div>


                <button
                    type="submit"
                    class="submit-availability"
                >
                    Submit Availability
                </button>


            </form>

        </section>


        <!-- =====================================
             Student Information
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Student Information
            </h2>


            <div class="info-grid">


                <div class="info-card">

                    <span>
                        Student ID
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["S_ID"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card">

                    <span>
                        Full Name
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["S_NAME"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card">

                    <span>
                        Email
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["S_EMAIL"]
                            ?? "Not available"
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card">

                    <span>
                        Address
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $student["S_ADDRESS"]
                        );
                        ?>
                    </strong>

                </div>


            </div>

        </section>


        <!-- =====================================
             My Class Information
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                My Class Information
            </h2>


            <?php if ($class): ?>


                <div class="class-information">


                    <div>

                        <span>
                            Class ID
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $class["CLASS_ID"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Course Name
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $class["COURSE_NAME"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Section
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $class["SECTION"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div>

                        <span>
                            Schedule
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $class["SCHEDULE"]
                            );
                            ?>
                        </strong>

                    </div>


                </div>


            <?php else: ?>


                <div class="empty-message">

                    No class has been assigned yet.

                </div>


            <?php endif; ?>


        </section>


        <!-- =====================================
             Notifications
             ===================================== -->

        <section class="dashboard-section">

            <div class="section-heading">

                <h2>
                    Notifications
                </h2>


                <?php if (count($notifications) > 0): ?>

                    <span class="notification-count">

                        <?php
                        echo count($notifications);
                        ?>

                    </span>

                <?php endif; ?>


            </div>


            <?php if (count($notifications) > 0): ?>


                <div class="notification-list">


                    <?php foreach ($notifications as $notification): ?>


                        <div class="notification-item">


                            <span class="notification-dot">
                            </span>


                            <div class="notification-content">

                                <p>

                                    <?php
                                    echo htmlspecialchars(
                                        $notification["MESSAGE"]
                                    );
                                    ?>

                                </p>


                                <small>

                                    <?php

                                    if (
                                        !empty(
                                            $notification["SENT_DATETIME"]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $notification["SENT_DATETIME"]
                                        );

                                    }

                                    ?>

                                </small>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-message">

                    No new notifications.

                </div>


            <?php endif; ?>


        </section>


        <!-- =====================================
             Upcoming Make-up Classes
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Upcoming Make-up Classes
            </h2>


            <?php if (count($upcoming_classes) > 0): ?>


                <div class="upcoming-list">


                    <?php foreach ($upcoming_classes as $makeup): ?>


                        <div class="upcoming-card">


                            <div class="upcoming-information">


                                <h3>

                                    <?php
                                    echo htmlspecialchars(
                                        $makeup["CLASS_ID"]
                                    );
                                    ?>

                                    —

                                    <?php
                                    echo htmlspecialchars(
                                        $makeup["COURSE_NAME"]
                                    );
                                    ?>

                                </h3>


                                <p>

                                    <?php
                                    echo htmlspecialchars(
                                        $makeup["REQUESTED_DATE"]
                                    );
                                    ?>


                                    <?php
                                    if (
                                        !empty(
                                            $makeup["PROPOSED_TIME"]
                                        )
                                    ):
                                    ?>

                                        ,
                                        <?php
                                        echo htmlspecialchars(
                                            $makeup["PROPOSED_TIME"]
                                        );
                                        ?>

                                    <?php endif; ?>


                                    <?php
                                    if (
                                        !empty(
                                            $makeup["ROOM_ID"]
                                        )
                                    ):
                                    ?>

                                        — Room
                                        <?php
                                        echo htmlspecialchars(
                                            $makeup["ROOM_ID"]
                                        );
                                        ?>

                                    <?php endif; ?>


                                </p>


                                <?php
                                if (
                                    !empty(
                                        $makeup["BUILDING_NAME"]
                                    )
                                ):
                                ?>

                                    <small>

                                        <?php
                                        echo htmlspecialchars(
                                            $makeup["BUILDING_NAME"]
                                        );
                                        ?>

                                    </small>

                                <?php endif; ?>


                            </div>


                            <span class="status-badge">

                                <?php
                                echo htmlspecialchars(
                                    $makeup["STATUS"]
                                );
                                ?>

                            </span>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-message">

                    No upcoming make-up classes.

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


</body>

</html>