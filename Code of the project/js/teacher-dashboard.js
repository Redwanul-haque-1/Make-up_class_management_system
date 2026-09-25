function changeClass(classId) {

    const currentUrl = new URL(
        window.location.href
    );

    currentUrl.searchParams.set(
        "class_id",
        classId
    );

    window.location.href =
        currentUrl.toString();
}


/* =====================================
   Time Slot Selection
   ===================================== */

const timeSlotInputs =
    document.querySelectorAll(
        'input[name="selected_slot"]'
    );


timeSlotInputs.forEach(function (input) {

    input.addEventListener(
        "change",
        function () {

            loadClassrooms(
                this.value
            );

        }
    );

});


/* =====================================
   Load Available Classrooms
   ===================================== */

function loadClassrooms(timeSlot) {

    const classroomList =
        document.getElementById(
            "classroom-list"
        );


    const roomId =
        document.getElementById(
            "room_id"
        );


    roomId.value = "";


    classroomList.innerHTML =
        '<p class="room-placeholder">' +
        'Checking available classrooms...' +
        '</p>';


    fetch(
        "get_available_classrooms.php?slot=" +
        encodeURIComponent(timeSlot)
    )
    .then(function (response) {

        return response.json();

    })
    .then(function (rooms) {

        classroomList.innerHTML = "";


        if (rooms.length === 0) {

            classroomList.innerHTML =
                '<p class="room-placeholder">' +
                'No classroom is available for this time slot.' +
                '</p>';

            return;
        }


        rooms.forEach(function (room) {

            const div =
                document.createElement(
                    "label"
                );


            div.className =
                "room-option";


            div.innerHTML =
                '<span>' +
                '<input type="radio" ' +
                'name="room_choice" ' +
                'value="' +
                room.room_id +
                '">' +
                'Room ' +
                room.room_id +
                ' — ' +
                room.building_name +
                '</span>' +

                '<span class="room-details">' +
                'Capacity: ' +
                room.capacity +
                '</span>';


            const radio =
                div.querySelector(
                    "input"
                );


            radio.addEventListener(
                "change",
                function () {

                    roomId.value =
                        this.value;


                    document
                        .querySelectorAll(
                            ".room-option"
                        )
                        .forEach(
                            function (item) {

                                item.classList.remove(
                                    "selected"
                                );

                            }
                        );


                    div.classList.add(
                        "selected"
                    );

                }
            );


            classroomList.appendChild(
                div
            );

        });

    })
    .catch(function () {

        classroomList.innerHTML =
            '<p class="room-placeholder">' +
            'Unable to load classrooms.' +
            '</p>';

    });

}