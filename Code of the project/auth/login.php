<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - Make-up Class System</title>

    <link rel="stylesheet" href="../css/login.css">
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
                app.makeupclass.edu/login
            </div>

        </div>

        <!-- System Header -->
        <div class="system-header">
            Make-up Class System
        </div>

        <!-- Login Container -->
        <div class="login-container">

            <h1>Log In</h1>

            <p class="subtitle">Login As</p>

            <form action="login_process.php" method="POST">

                <!-- Role -->
                <select name="role" required>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                    <option value="hod">Head of Department</option>
                </select>

                <!-- ID / Email -->
                <label for="identifier">ID / Email</label>

                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    placeholder="e.g. 21-12345-1"
                    required
                >

                <!-- Password -->
                <label for="password">Password</label>

                <div class="password-container">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="********"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        onclick="togglePassword()"
                        aria-label="Show or hide password"
                    >
                        👁
                    </button>

                </div>

                <!-- Forgot Password -->
                <div class="forgot-password">
                    <a href="#">Forgot password?</a>
                </div>

                <!-- Login Button -->
                <button type="submit" class="login-button">
                    Log In
                </button>

            </form>

            <!-- Sign Up -->
            <div class="signup-section">

                <p>Don't have an account?</p>

                <a href="signup.php">Sign Up</a>

            </div>

        </div>

    </div>

    <script src="../js/login.js"></script>

</body>
</html>