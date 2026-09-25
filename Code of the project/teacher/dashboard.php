<?php

session_start();

require_once "../config/database.php";


/* =====================================
   Check Teacher Login
   ===================================== */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "Teacher"
) {
    header("Location: ../auth/login.php");
    exit;
}

$teacher_id = $_SESSION["user_id"];


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
   Message Variables
   ===================================== */

$success_message = "";
$error_message = "";


/* =====================================
   Handle Make-up Class Request
   ===================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST["send_request"])) {

        $class_id = $_POST["class_id"] ?? "";
        $selected_slot = $_POST["selected_slot"] ?? "";
        $room_id = $_POST["room_id"] ?? "";


        /* Check required fields */

        if (
            $class_id == "" ||
            $selected_slot == "" ||
            $room_id == ""
        ) {

            $error_message =
                "Please select a class, time slot and classroom.";

        }

        elseif (!in_array($selected_slot, $time_slots)) {

            $error_message =
                "Invalid time slot.";

        }

        else {

            /* Convert class and room IDs */

            $class_id = (int)$class_id;
            $room_id = (int)$room_id;


            /* =====================================
               Check Teacher Owns This Class
               ===================================== */

            $sql = "
                SELECT class_id
                FROM Class
                WHERE class_id = :class_id
                AND t_id = :teacher_id
            ";

            $stmt = oci_parse($conn, $sql);

            oci_bind_by_name(
                $stmt,
                ":class_id",
                $class_id
            );

            oci_bind_by_name(
                $stmt,
                ":teacher_id",
                $teacher_id
            );

            oci_execute($stmt);


            if (!oci_fetch($stmt)) {

                $error_message =
                    "You are not assigned to this class.";

            }

            else {

                /* =====================================
                   Check Student Availability
                   ===================================== */

                $sql = "
                    SELECT COUNT(*) AS available_students
                    FROM Student s
                    INNER JOIN StudentAvailability sa
                        ON s.s_id = sa.s_id
                    WHERE s.class_id = :class_id
                    AND sa.time_slot = :time_slot
                ";

                $stmt = oci_parse($conn, $sql);

                oci_bind_by_name(
                    $stmt,
                    ":class_id",
                    $class_id
                );

                oci_bind_by_name(
                    $stmt,
                    ":time_slot",
                    $selected_slot
                );

                oci_execute($stmt);

                $availability_row =
                    oci_fetch_assoc($stmt);

                $available_students =
                    (int)$availability_row["AVAILABLE_STUDENTS"];


                if ($available_students == 0) {

                    $error_message =
                        "No student from this class is available at the selected time.";

                }

                else {

                    /* =====================================
                       Calculate Next Date
                       ===================================== */

                    $day_map = [
                        "Mon" => "Monday",
                        "Tue" => "Tuesday",
                        "Wed" => "Wednesday",
                        "Thu" => "Thursday",
                        "Fri" => "Friday",
                        "Sat" => "Saturday"
                    ];


                    $day_short =
                        substr($selected_slot, 0, 3);

                    $requested_day =
                        $day_map[$day_short];


                    /*
                     * Find the next occurrence
                     * of the selected weekday.
                     */

                    $requested_date =
                        date(
                            "d-M-Y",
                            strtotime(
                                "next " . $requested_day
                            )
                        );


                    /* =====================================
                       Extract Proposed Time
                       ===================================== */

                    $time_part =
                        substr(
                            $selected_slot,
                            strpos(
                                $selected_slot,
                                " "
                            ) + 1
                        );


                    /*
                     * Convert 2–3pm format into
                     * a database-friendly time.
                     */

                    $proposed_time =
                        $time_part;


                    /* =====================================
                       Check Classroom Availability
                       ===================================== */

                    $sql = "
                        SELECT room_id
                        FROM Classroom
                        WHERE room_id = :room_id
                        AND room_id NOT IN
                        (
                            SELECT room_id
                            FROM MakeupClassRequest
                            WHERE requested_date =
                                  TO_DATE(
                                      :requested_date,
                                      'DD-MON-YYYY'
                                  )
                            AND proposed_time = :proposed_time
                            AND UPPER(status) IN
                                ('APPROVED', 'CONFIRMED', 'PENDING')
                        )
                    ";

                    $stmt = oci_parse($conn, $sql);

                    oci_bind_by_name(
                        $stmt,
                        ":room_id",
                        $room_id
                    );

                    oci_bind_by_name(
                        $stmt,
                        ":requested_date",
                        $requested_date
                    );

                    oci_bind_by_name(
                        $stmt,
                        ":proposed_time",
                        $proposed_time
                    );

                    oci_execute($stmt);


                    if (!oci_fetch($stmt)) {

                        $error_message =
                            "The selected classroom is no longer available.";

                    }

                    else {

                        /* =====================================
                           Get HOD of Teacher's Department
                           ===================================== */

                        $sql = "
                            SELECT
                                d.hod_id
                            FROM Teacher t
                            INNER JOIN Department d
                                ON t.dept_id = d.dept_id
                            WHERE t.t_id = :teacher_id
                        ";

                        $stmt = oci_parse($conn, $sql);

                        oci_bind_by_name(
                            $stmt,
                            ":teacher_id",
                            $teacher_id
                        );

                        oci_execute($stmt);

                        $hod_row =
                            oci_fetch_assoc($stmt);


                        if (!$hod_row) {

                            $error_message =
                                "No Head of Department is assigned to your department.";

                        }

                        else {

                            $hod_id =
                                $hod_row["HOD_ID"];


                            /* =====================================
                               Insert Make-up Request
                               ===================================== */

                            $sql = "
                                SELECT makeup_request_seq.NEXTVAL
                                AS req_id
                                FROM dual
                            ";

                            $stmt =
                                oci_parse($conn, $sql);

                            oci_execute($stmt);

                            $request_row =
                                oci_fetch_assoc($stmt);

                            $req_id =
                                $request_row["REQ_ID"];


                            $status = "Pending";


                            $sql = "
                                INSERT INTO MakeupClassRequest
                                (
                                    req_id,
                                    requested_date,
                                    proposed_time,
                                    status,
                                    class_id,
                                    room_id,
                                    hod_id
                                )
                                VALUES
                                (
                                    :req_id,
                                    TO_DATE(
                                        :requested_date,
                                        'DD-MON-YYYY'
                                    ),
                                    :proposed_time,
                                    :status,
                                    :class_id,
                                    :room_id,
                                    :hod_id
                                )
                            ";

                            $stmt =
                                oci_parse($conn, $sql);


                            oci_bind_by_name(
                                $stmt,
                                ":req_id",
                                $req_id
                            );

                            oci_bind_by_name(
                                $stmt,
                                ":requested_date",
                                $requested_date
                            );

                            oci_bind_by_name(
                                $stmt,
                                ":proposed_time",
                                $proposed_time
                            );

                            oci_bind_by_name(
                                $stmt,
                                ":status",
                                $status
                            );

                            oci_bind_by_name(
                                $stmt,
                                ":class_id",
                                $class_id
                            );

                            oci_bind_by_name(
                                $stmt,
                                ":room_id",
                                $room_id
                            );

                            oci_bind_by_name(
                                $stmt,
                                ":hod_id",
                                $hod_id
                            );


                            if (oci_execute($stmt)) {

                                oci_commit($conn);

                                $success_message =
                                    "Make-up class request sent to Head of Department.";

                            }

                            else {

                                oci_rollback($conn);

                                $error_message =
                                    "Failed to send make-up class request.";
                            }
                        }
                    }
                }
            }
        }
    }
}


/* =====================================
   Get Teacher Information
   ===================================== */

$sql = "
    SELECT
        t.t_id,
        t.t_name,
        t.designation,
        t.t_address,
        t.t_email,
        t.dept_id,
        d.dept_name
    FROM Teacher t
    LEFT JOIN Department d
        ON t.dept_id = d.dept_id
    WHERE t.t_id = :teacher_id
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":teacher_id",
    $teacher_id
);

oci_execute($stmt);

$teacher =
    oci_fetch_assoc($stmt);


if (!$teacher) {

    die("Teacher information not found.");

}


/* =====================================
   Get Teacher Phone
   ===================================== */

$teacher_phone = "";

$sql = "
    SELECT t_phone
    FROM TeacherPhone
    WHERE t_id = :teacher_id
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":teacher_id",
    $teacher_id
);

oci_execute($stmt);

$phone_row =
    oci_fetch_assoc($stmt);

if ($phone_row) {

    $teacher_phone =
        $phone_row["T_PHONE"];
}


/* =====================================
   Get Teacher Classes
   ===================================== */

$classes = [];

$sql = "
    SELECT
        class_id,
        course_name,
        section,
        schedule
    FROM Class
    WHERE t_id = :teacher_id
    ORDER BY class_id
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":teacher_id",
    $teacher_id
);

oci_execute($stmt);

while ($row = oci_fetch_assoc($stmt)) {

    $classes[] = $row;
}


/* =====================================
   Selected Class
   ===================================== */

$selected_class_id =
    $_GET["class_id"] ?? "";

if (
    $selected_class_id == "" &&
    count($classes) > 0
) {

    $selected_class_id =
        $classes[0]["CLASS_ID"];
}


/* =====================================
   Get Student Free Time Slots
   ===================================== */

$student_availability = [];

if ($selected_class_id != "") {

    $selected_class_id =
        (int)$selected_class_id;


    foreach ($time_slots as $slot) {

        $sql = "
            SELECT COUNT(*) AS available_students
            FROM Student s
            INNER JOIN StudentAvailability sa
                ON s.s_id = sa.s_id
            WHERE s.class_id = :class_id
            AND sa.time_slot = :time_slot
        ";

        $stmt =
            oci_parse($conn, $sql);

        oci_bind_by_name(
            $stmt,
            ":class_id",
            $selected_class_id
        );

        oci_bind_by_name(
            $stmt,
            ":time_slot",
            $slot
        );

        oci_execute($stmt);

        $row =
            oci_fetch_assoc($stmt);

        $student_availability[$slot] =
            (int)$row["AVAILABLE_STUDENTS"];
    }
}


/* =====================================
   Get Available Classrooms
   ===================================== */

$classrooms = [];

if ($selected_class_id != "") {

    foreach ($time_slots as $slot) {

        /*
         * This prepares the room list for
         * the selected time slot.
         */

        $sql = "
            SELECT
                room_id,
                building_name,
                capacity
            FROM Classroom
            WHERE room_id NOT IN
            (
                SELECT room_id
                FROM MakeupClassRequest
                WHERE proposed_time = :proposed_time
                AND UPPER(status) IN
                    ('APPROVED', 'CONFIRMED', 'PENDING')
            )
            ORDER BY room_id
        ";

        /*
         * Classroom availability will be
         * refreshed when a time slot is selected.
         */

        break;
    }
}


/* =====================================
   Get Teacher Requests
   ===================================== */

$requests = [];

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

    WHERE c.t_id = :teacher_id

    ORDER BY r.req_id DESC
";

$stmt = oci_parse($conn, $sql);

oci_bind_by_name(
    $stmt,
    ":teacher_id",
    $teacher_id
);

oci_execute($stmt);

while ($row = oci_fetch_assoc($stmt)) {

    $requests[] = $row;
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
        Teacher Dashboard - Make-up Class System
    </title>

    <link
        rel="stylesheet"
        href="../css/teacher-dashboard.css"
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

            app.makeupclass.edu/teacher/home

        </div>

    </div>


    <!-- System Header -->

    <div class="system-header">

        <div class="system-title">

            Make-up Class System

        </div>


        <div class="header-right">

            <span>
                Teacher Dashboard
            </span>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>


    <!-- Dashboard -->

    <main class="dashboard-container">


        <!-- Welcome -->

        <section class="welcome-section">

            <div>

                <h1>
                    Welcome,
                    <?php
                    echo htmlspecialchars(
                        $teacher["T_NAME"]
                    );
                    ?>!
                </h1>

                <p>
                    Manage make-up class requests
                    and view your assigned classes.
                </p>

            </div>


            <div class="teacher-badge">

                <div class="teacher-icon">
                    👨‍🏫
                </div>

                <div>

                    <strong>

                        ID:
                        <?php
                        echo htmlspecialchars(
                            $teacher["T_ID"]
                        );
                        ?>

                    </strong>

                    <span>
                        Teacher
                    </span>

                </div>

            </div>

        </section>


        <!-- =====================================
             Teacher Information
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Teacher Information
            </h2>


            <div class="info-grid">


                <div class="info-card">

                    <span>
                        Teacher ID
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $teacher["T_ID"]
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
                            $teacher["T_NAME"]
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
                            $teacher["T_EMAIL"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card">

                    <span>
                        Designation
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $teacher["DESIGNATION"]
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card">

                    <span>
                        Department
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $teacher["DEPT_NAME"]
                            ?? "Not assigned"
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card">

                    <span>
                        Phone
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $teacher_phone
                            ?: "Not available"
                        );
                        ?>
                    </strong>

                </div>


                <div class="info-card address-card">

                    <span>
                        Address
                    </span>

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $teacher["T_ADDRESS"]
                        );
                        ?>
                    </strong>

                </div>


            </div>

        </section>


        <!-- =====================================
             Request Make-up Class
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Request Make-up Class
            </h2>


            <?php if ($success_message != ""): ?>

                <div class="success-message">

                    <?php
                    echo htmlspecialchars(
                        $success_message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($error_message != ""): ?>

                <div class="error-message">

                    <?php
                    echo htmlspecialchars(
                        $error_message
                    );
                    ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                id="request-form"
            >


                <!-- Class -->

                <label>
                    Class / Section
                </label>


                <select
                    name="class_id"
                    id="class_id"
                    onchange="changeClass(this.value)"
                    required
                >

                    <?php foreach ($classes as $class): ?>

                        <option
                            value="<?php
                            echo $class["CLASS_ID"];
                            ?>"
                            <?php
                            if (
                                $selected_class_id ==
                                $class["CLASS_ID"]
                            ) {
                                echo "selected";
                            }
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $class["COURSE_NAME"]
                            );
                            ?>

                            —

                            Section

                            <?php
                            echo htmlspecialchars(
                                $class["SECTION"]
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>


                <!-- Student Free Time -->

                <label class="sub-label">

                    Student Free Time Slots

                </label>


                <div class="time-slots">


                    <?php foreach ($time_slots as $slot): ?>

                        <?php
                        $count =
                            $student_availability[$slot]
                            ?? 0;
                        ?>


                        <label
                            class="time-slot
                            <?php
                            if ($count == 0) {
                                echo "disabled";
                            }
                            ?>"
                        >

                            <input
                                type="radio"
                                name="selected_slot"
                                value="<?php
                                echo htmlspecialchars(
                                    $slot
                                );
                                ?>"
                                <?php
                                if ($count == 0) {
                                    echo "disabled";
                                }
                                ?>
                                required
                            >

                            <span>
                                <?php
                                echo htmlspecialchars(
                                    $slot
                                );
                                ?>
                            </span>

                        </label>

                    <?php endforeach; ?>


                </div>


                <p class="availability-note">

                    Available students are shown by
                    the selectable time slots.

                </p>


                <!-- Available Rooms -->

                <label class="sub-label">

                    Available Classrooms

                </label>


                <div id="classroom-list">

                    <p class="room-placeholder">

                        Select a free-time slot to see
                        available classrooms.

                    </p>

                </div>


                <!-- Hidden Room -->

                <input
                    type="hidden"
                    name="room_id"
                    id="room_id"
                    value=""
                >


                <!-- Submit -->

                <button
                    type="submit"
                    name="send_request"
                    class="submit-button"
                >

                    Send Request to Head of Department

                </button>


            </form>

        </section>


        <!-- =====================================
             My Requests
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                My Requests
            </h2>


            <?php if (count($requests) > 0): ?>


                <div class="request-list">


                    <?php foreach ($requests as $request): ?>


                        <div class="request-card">


                            <div>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["COURSE_NAME"]
                                    );
                                    ?>

                                    —

                                    Section

                                    <?php
                                    echo htmlspecialchars(
                                        $request["SECTION"]
                                    );
                                    ?>

                                </strong>


                                <p>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["REQUESTED_DATE"]
                                    );
                                    ?>

                                    ·

                                    <?php
                                    echo htmlspecialchars(
                                        $request["PROPOSED_TIME"]
                                    );
                                    ?>

                                    · Room

                                    <?php
                                    echo htmlspecialchars(
                                        $request["ROOM_ID"]
                                    );
                                    ?>

                                </p>

                            </div>


                            <span
                                class="request-status
                                <?php
                                echo strtolower(
                                    $request["STATUS"]
                                );
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $request["STATUS"]
                                );
                                ?>

                            </span>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-message">

                    You have not submitted any
                    make-up class requests yet.

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


<script src="../js/teacher-dashboard.js"></script>

</body>

</html>