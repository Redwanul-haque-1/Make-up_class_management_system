<?php

session_start();

require_once "../config/database.php";


/* Check Teacher Login */

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["user_role"] != "Teacher"
) {
    echo json_encode([]);
    exit;
}


$slot = $_GET["slot"] ?? "";


$allowed_slots = [
    "Mon 2 to 3pm",
    "Tue 10 to 11am",
    "Wed 1 to 2pm",
    "Thu 3 to 4pm",
    "Fri 11 to 12pm",
    "Sat 9 to 10am"
];


if (!in_array($slot, $allowed_slots)) {

    echo json_encode([]);

    exit;
}


/* =====================================
   Get Next Date
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
    substr($slot, 0, 3);


$requested_day =
    $day_map[$day_short];


$requested_date =
    date(
        "d-M-Y",
        strtotime(
            "next " . $requested_day
        )
    );


/* =====================================
   Get Time
   ===================================== */

$proposed_time =
    substr(
        $slot,
        strpos(
            $slot,
            " "
        ) + 1
    );


/* =====================================
   Find Available Rooms
   ===================================== */

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

        WHERE requested_date =
            TO_DATE(
                :requested_date,
                'DD-MON-YYYY'
            )

        AND proposed_time =
            :proposed_time

        AND UPPER(status) IN
            (
                'PENDING',
                'APPROVED',
                'CONFIRMED'
            )
    )

    ORDER BY room_id
";


$stmt =
    oci_parse(
        $conn,
        $sql
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


$rooms = [];


while (
    $row =
    oci_fetch_assoc($stmt)
) {

    $rooms[] = [

        "room_id" =>
            $row["ROOM_ID"],

        "building_name" =>
            $row["BUILDING_NAME"],

        "capacity" =>
            $row["CAPACITY"]

    ];

}


header(
    "Content-Type: application/json"
);


echo json_encode($rooms);

?>