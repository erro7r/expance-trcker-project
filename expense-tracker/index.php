<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - Home</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Welcome to Expense Tracker</h1>
        <div class="auth-options">
            <?php if (isset($_SESSION['user_id'])): ?>
                <p>You are already logged in. <a href="dashboard.php">Go to Dashboard</a> or <a href="logout.php">Logout</a></p>
            <?php else: ?>
                <a href="login.php" class="btn">Login</a>
                <a href="signup.php" class="btn">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>