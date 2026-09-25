function changeRole() {

    const role = document.getElementById("role").value;

    const studentSection =
        document.getElementById("student-class-section");

    const teacherSection =
        document.getElementById("teacher-department-section");

    const classField =
        document.getElementById("class_id");

    const departmentField =
        document.getElementById("dept_id");


    /* Hide both first */

    studentSection.style.display = "none";
    teacherSection.style.display = "none";

    classField.required = false;
    departmentField.required = false;


    /* Student */

    if (role === "Student") {

        studentSection.style.display = "block";

        classField.required = true;
    }


    /* Teacher */

    else if (role === "Teacher") {

        teacherSection.style.display = "block";

        departmentField.required = true;
    }
}


/* Password Show / Hide */

function togglePassword(fieldId, button) {

    const passwordField =
        document.getElementById(fieldId);

    if (passwordField.type === "password") {

        passwordField.type = "text";

        button.textContent = "🙈";

    } else {

        passwordField.type = "password";

        button.textContent = "👁";
    }
}