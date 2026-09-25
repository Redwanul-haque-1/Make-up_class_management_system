<?php

session_start();

require_once "../config/database.php";


/* =====================================
   Check HOD Login
   ===================================== */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "HOD"
) {
    header("Location: ../auth/login.php");
    exit;
}

$hod_id = $_SESSION["user_id"];


/* =====================================
   Messages
   ===================================== */

$success_message = "";
$error_message = "";


/* =====================================
   Approve / Reject Request
   ===================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $req_id = $_POST["req_id"] ?? "";
    $action = $_POST["action"] ?? "";


    if ($req_id == "") {

        $error_message = "Invalid request.";

    }

    elseif (
        $action != "approve" &&
        $action != "reject"
    ) {

        $error_message = "Invalid action.";

    }

    else {

        $req_id = (int)$req_id;


        /* =====================================
           Get Request
           ===================================== */

        $sql = "
            SELECT
                r.req_id,
                r.requested_date,
                r.proposed_time,
                r.status,
                r.class_id,
                r.room_id,
                r.hod_id,

                c.course_name,
                c.section,

                t.t_id,
                t.t_name,

                room.room_id AS selected_room,
                room.building_name,
                room.capacity

            FROM MakeupClassRequest r

            INNER JOIN Class c
                ON r.class_id = c.class_id

            INNER JOIN Teacher t
                ON c.t_id = t.t_id

            LEFT JOIN Classroom room
                ON r.room_id = room.room_id

            WHERE r.req_id = :req_id
            AND r.hod_id = :hod_id
        ";

        $stmt = oci_parse($conn, $sql);

        oci_bind_by_name(
            $stmt,
            ":req_id",
            $req_id
        );

        oci_bind_by_name(
            $stmt,
            ":hod_id",
            $hod_id
        );

        oci_execute($stmt);

        $request = oci_fetch_assoc($stmt);


        if (!$request) {

            $error_message =
                "Request not found or it does not belong to you.";

        }

        elseif (
            strtoupper($request["STATUS"]) != "PENDING"
        ) {

            $error_message =
                "This request has already been processed.";

        }

        else {


            /* =====================================
               APPROVE
               ===================================== */

            if ($action == "approve") {


                /* =====================================
                   Check Room Availability
                   ===================================== */

                $sql = "
                    SELECT COUNT(*) AS conflict_count

                    FROM MakeupClassRequest

                    WHERE room_id = :room_id

                    AND requested_date =
                        TRUNC(:requested_date)

                    AND proposed_time = :proposed_time

                    AND req_id != :req_id

                    AND UPPER(status) IN
                    (
                        'APPROVED',
                        'CONFIRMED'
                    )
                ";

                $stmt = oci_parse(
                    $conn,
                    $sql
                );

                oci_bind_by_name(
                    $stmt,
                    ":room_id",
                    $request["ROOM_ID"]
                );

                oci_bind_by_name(
                    $stmt,
                    ":requested_date",
                    $request["REQUESTED_DATE"]
                );

                oci_bind_by_name(
                    $stmt,
                    ":proposed_time",
                    $request["PROPOSED_TIME"]
                );

                oci_bind_by_name(
                    $stmt,
                    ":req_id",
                    $req_id
                );

                oci_execute($stmt);

                $conflict =
                    oci_fetch_assoc($stmt);


                if (
                    (int)$conflict["CONFLICT_COUNT"] > 0
                ) {

                    $error_message =
                        "The selected classroom is already occupied at this date and time.";

                }

                else {


                    /* =====================================
                       Update Request
                       ===================================== */

                    $sql = "
                        UPDATE MakeupClassRequest

                        SET
                            status = 'Approved',
                            approved_date = SYSDATE

                        WHERE req_id = :req_id
                        AND hod_id = :hod_id
                    ";

                    $stmt = oci_parse(
                        $conn,
                        $sql
                    );

                    oci_bind_by_name(
                        $stmt,
                        ":req_id",
                        $req_id
                    );

                    oci_bind_by_name(
                        $stmt,
                        ":hod_id",
                        $hod_id
                    );

                    oci_execute($stmt);


                    /* =====================================
                       Notify Students
                       ===================================== */

                    $sql = "
                        SELECT s_id
                        FROM Student
                        WHERE class_id = :class_id
                    ";

                    $stmt = oci_parse(
                        $conn,
                        $sql
                    );

                    oci_bind_by_name(
                        $stmt,
                        ":class_id",
                        $request["CLASS_ID"]
                    );

                    oci_execute($stmt);


                    $message =
                        "Make-up class APPROVED — " .
                        $request["COURSE_NAME"] .
                        ", Room " .
                        $request["ROOM_ID"] .
                        ", " .
                        $request["PROPOSED_TIME"];


                    while (
                        $student =
                        oci_fetch_assoc($stmt)
                    ) {

                        $sql = "
                            SELECT notification_seq.NEXTVAL
                            AS notif_id
                            FROM dual
                        ";

                        $notif_stmt =
                            oci_parse(
                                $conn,
                                $sql
                            );

                        oci_execute(
                            $notif_stmt
                        );

                        $notif_row =
                            oci_fetch_assoc(
                                $notif_stmt
                            );

                        $notif_id =
                            $notif_row["NOTIF_ID"];


                        $sql = "
                            INSERT INTO Notification
                            (
                                notif_id,
                                message,
                                sent_datetime,
                                req_id,
                                s_id
                            )
                            VALUES
                            (
                                :notif_id,
                                :message,
                                SYSTIMESTAMP,
                                :req_id,
                                :s_id
                            )
                        ";

                        $notif_stmt =
                            oci_parse(
                                $conn,
                                $sql
                            );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":notif_id",
                            $notif_id
                        );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":message",
                            $message
                        );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":req_id",
                            $req_id
                        );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":s_id",
                            $student["S_ID"]
                        );

                        oci_execute(
                            $notif_stmt
                        );
                    }


                    oci_commit($conn);


                    $success_message =
                        "Make-up class request approved successfully.";

                }

            }


            /* =====================================
               REJECT
               ===================================== */

            elseif ($action == "reject") {


                $sql = "
                    UPDATE MakeupClassRequest

                    SET
                        status = 'Rejected',
                        approved_date = NULL

                    WHERE req_id = :req_id
                    AND hod_id = :hod_id
                ";

                $stmt = oci_parse(
                    $conn,
                    $sql
                );

                oci_bind_by_name(
                    $stmt,
                    ":req_id",
                    $req_id
                );

                oci_bind_by_name(
                    $stmt,
                    ":hod_id",
                    $hod_id
                );

                oci_execute($stmt);


                /* =====================================
                   Notify Students About Rejection
                   ===================================== */

                $sql = "
                    SELECT
                        class_id,
                        course_name

                    FROM Class

                    WHERE class_id =
                    (
                        SELECT class_id
                        FROM MakeupClassRequest
                        WHERE req_id = :req_id
                    )
                ";

                $stmt = oci_parse(
                    $conn,
                    $sql
                );

                oci_bind_by_name(
                    $stmt,
                    ":req_id",
                    $req_id
                );

                oci_execute($stmt);

                $class_row =
                    oci_fetch_assoc($stmt);


                if ($class_row) {

                    $sql = "
                        SELECT s_id
                        FROM Student
                        WHERE class_id = :class_id
                    ";

                    $student_stmt =
                        oci_parse(
                            $conn,
                            $sql
                        );

                    oci_bind_by_name(
                        $student_stmt,
                        ":class_id",
                        $class_row["CLASS_ID"]
                    );

                    oci_execute(
                        $student_stmt
                    );


                    $message =
                        "Make-up class request REJECTED — " .
                        $class_row["COURSE_NAME"];


                    while (
                        $student =
                        oci_fetch_assoc(
                            $student_stmt
                        )
                    ) {

                        $sql = "
                            SELECT notification_seq.NEXTVAL
                            AS notif_id
                            FROM dual
                        ";

                        $notif_stmt =
                            oci_parse(
                                $conn,
                                $sql
                            );

                        oci_execute(
                            $notif_stmt
                        );

                        $notif_row =
                            oci_fetch_assoc(
                                $notif_stmt
                            );

                        $notif_id =
                            $notif_row["NOTIF_ID"];


                        $sql = "
                            INSERT INTO Notification
                            (
                                notif_id,
                                message,
                                sent_datetime,
                                req_id,
                                s_id
                            )
                            VALUES
                            (
                                :notif_id,
                                :message,
                                SYSTIMESTAMP,
                                :req_id,
                                :s_id
                            )
                        ";

                        $notif_stmt =
                            oci_parse(
                                $conn,
                                $sql
                            );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":notif_id",
                            $notif_id
                        );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":message",
                            $message
                        );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":req_id",
                            $req_id
                        );

                        oci_bind_by_name(
                            $notif_stmt,
                            ":s_id",
                            $student["S_ID"]
                        );

                        oci_execute(
                            $notif_stmt
                        );
                    }
                }


                oci_commit($conn);


                $success_message =
                    "Make-up class request rejected.";

            }
        }
    }
}


/* =====================================
   Get HOD Information
   ===================================== */

$sql = "
    SELECT
        h.hod_id,
        h.hod_name,
        h.hod_email,
        h.hod_phone,
        d.dept_id,
        d.dept_name

    FROM HOD h

    LEFT JOIN Department d
        ON d.hod_id = h.hod_id

    WHERE h.hod_id = :hod_id
";

$stmt = oci_parse(
    $conn,
    $sql
);

oci_bind_by_name(
    $stmt,
    ":hod_id",
    $hod_id
);

oci_execute($stmt);

$hod =
    oci_fetch_assoc($stmt);


/* =====================================
   Get Pending Requests
   ===================================== */

$pending_requests = [];

$sql = "
    SELECT
        r.req_id,
        r.requested_date,
        r.proposed_time,
        r.status,

        c.class_id,
        c.course_name,
        c.section,

        t.t_name,

        room.room_id,
        room.building_name,
        room.capacity

    FROM MakeupClassRequest r

    INNER JOIN Class c
        ON r.class_id = c.class_id

    INNER JOIN Teacher t
        ON c.t_id = t.t_id

    LEFT JOIN Classroom room
        ON r.room_id = room.room_id

    WHERE r.hod_id = :hod_id

    AND UPPER(r.status) = 'PENDING'

    ORDER BY
        r.requested_date ASC,
        r.req_id ASC
";

$stmt = oci_parse(
    $conn,
    $sql
);

oci_bind_by_name(
    $stmt,
    ":hod_id",
    $hod_id
);

oci_execute($stmt);

while (
    $row =
    oci_fetch_assoc($stmt)
) {

    $pending_requests[] = $row;
}


/* =====================================
   Get Recently Approved Requests
   ===================================== */

$approved_requests = [];

$sql = "
    SELECT *
    FROM
    (
        SELECT
            r.req_id,
            r.requested_date,
            r.proposed_time,
            r.status,

            c.class_id,
            c.course_name,
            c.section,

            t.t_name,

            room.room_id

        FROM MakeupClassRequest r

        INNER JOIN Class c
            ON r.class_id = c.class_id

        INNER JOIN Teacher t
            ON c.t_id = t.t_id

        LEFT JOIN Classroom room
            ON r.room_id = room.room_id

        WHERE r.hod_id = :hod_id

        AND UPPER(r.status) IN
            ('APPROVED', 'CONFIRMED')

        ORDER BY
            r.approved_date DESC
    )

    WHERE ROWNUM <= 5
";

$stmt = oci_parse(
    $conn,
    $sql
);

oci_bind_by_name(
    $stmt,
    ":hod_id",
    $hod_id
);

oci_execute($stmt);

while (
    $row =
    oci_fetch_assoc($stmt)
) {

    $approved_requests[] = $row;
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
        HOD Dashboard - Make-up Class System
    </title>

    <link
        rel="stylesheet"
        href="../css/hod-dashboard.css"
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

            app.makeupclass.edu/hod/requests

        </div>

    </div>


    <!-- System Header -->

    <div class="system-header">

        <div class="system-title">

            Make-up Class System

        </div>


        <div class="header-right">

            <span>
                HOD Dashboard
            </span>

            <a href="../auth/logout.php">
                Logout
            </a>

        </div>

    </div>


    <!-- Main Content -->

    <main class="dashboard-container">


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


        <!-- =====================================
             Pending Requests
             ===================================== -->

        <section class="dashboard-section">

            <h1>
                Pending Make-up Class Requests
            </h1>


            <?php if (count($pending_requests) > 0): ?>


                <div class="request-list">


                    <?php foreach (
                        $pending_requests
                        as $request
                    ): ?>


                        <div class="request-card">


                            <div class="request-information">

                                <h3>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["T_NAME"]
                                    );
                                    ?>

                                    —

                                    <?php
                                    echo htmlspecialchars(
                                        $request["COURSE_NAME"]
                                    );
                                    ?>

                                </h3>


                                <p>

                                    Section
                                    <?php
                                    echo htmlspecialchars(
                                        $request["SECTION"]
                                    );
                                    ?>

                                    · Room
                                    <?php
                                    echo htmlspecialchars(
                                        $request["ROOM_ID"]
                                    );
                                    ?>

                                    ·

                                    <?php
                                    echo htmlspecialchars(
                                        $request["PROPOSED_TIME"]
                                    );
                                    ?>

                                </p>


                                <small>

                                    <?php
                                    echo htmlspecialchars(
                                        $request["REQUESTED_DATE"]
                                    );
                                    ?>

                                    ·

                                    <?php
                                    echo htmlspecialchars(
                                        $request["BUILDING_NAME"]
                                        ?? ""
                                    );
                                    ?>

                                </small>

                            </div>


                            <div class="request-actions">


                                <form
                                    method="POST"
                                    action=""
                                >

                                    <input
                                        type="hidden"
                                        name="req_id"
                                        value="<?php
                                        echo $request["REQ_ID"];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="action"
                                        value="approve"
                                        class="approve-button"
                                    >

                                        Approve

                                    </button>

                                </form>


                                <form
                                    method="POST"
                                    action=""
                                >

                                    <input
                                        type="hidden"
                                        name="req_id"
                                        value="<?php
                                        echo $request["REQ_ID"];
                                        ?>"
                                    >


                                    <button
                                        type="submit"
                                        name="action"
                                        value="reject"
                                        class="reject-button"
                                    >

                                        Reject

                                    </button>

                                </form>


                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-message">

                    No pending make-up class requests.

                </div>


            <?php endif; ?>


        </section>


        <!-- =====================================
             Room Availability Check
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Room Availability Check
            </h2>

            <p class="section-label">

                Selected room & time

            </p>


            <?php if (
                count($pending_requests) > 0
            ): ?>


                <?php

                $check =
                    $pending_requests[0];

                ?>


                <div class="room-check">

                    <span class="room-check-icon">
                        ✓
                    </span>


                    <div>

                        <strong>

                            Room
                            <?php
                            echo htmlspecialchars(
                                $check["ROOM_ID"]
                            );
                            ?>

                            is assigned for

                            <?php
                            echo htmlspecialchars(
                                $check["REQUESTED_DATE"]
                            );
                            ?>

                            ,

                            <?php
                            echo htmlspecialchars(
                                $check["PROPOSED_TIME"]
                            );
                            ?>

                        </strong>


                        <p>

                            <?php
                            echo htmlspecialchars(
                                $check["BUILDING_NAME"]
                                ?? ""
                            );
                            ?>

                        </p>

                    </div>

                </div>


            <?php else: ?>


                <div class="room-check empty-room">

                    No pending request to check.

                </div>


            <?php endif; ?>


        </section>


        <!-- =====================================
             Recently Approved
             ===================================== -->

        <section class="dashboard-section">

            <h2>
                Recently Approved
            </h2>


            <?php if (
                count($approved_requests) > 0
            ): ?>


                <div class="approved-list">


                    <?php foreach (
                        $approved_requests
                        as $approved
                    ): ?>


                        <div class="approved-card">

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $approved["T_NAME"]
                                );
                                ?>

                                —

                                <?php
                                echo htmlspecialchars(
                                    $approved["COURSE_NAME"]
                                );
                                ?>

                            </strong>


                            <span>

                                Room
                                <?php
                                echo htmlspecialchars(
                                    $approved["ROOM_ID"]
                                );
                                ?>

                                ·

                                <?php
                                echo htmlspecialchars(
                                    $approved["REQUESTED_DATE"]
                                );
                                ?>

                                ·

                                <?php
                                echo htmlspecialchars(
                                    $approved["PROPOSED_TIME"]
                                );
                                ?>

                                · Approved

                            </span>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="empty-message">

                    No recently approved requests.

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


</body>

</html>