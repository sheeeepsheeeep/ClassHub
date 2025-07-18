<?php
// DATABASE CONNECTION
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ClassHubDB";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");

    exit();
}


$page = $_GET['page'] ?? 'user_CheckStatus';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users Page - ClassHub</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Raleway:wght@400;500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: rgba(207, 207, 207, 0.96);
        }
        .sidebar {
            height: 100vh;
            width: 240px;
            background: rgb(32, 49, 72);
            color: white;
            position: fixed;
            display: flex;
            flex-direction: column;
            padding: 20px;
        }
        .logo {
            font-family: 'Poppins', sans-serif;
            font-size: 40px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 30px;
            margin-right: 5px;
            margin-left: 10px;
            color: #ffffff; 
            letter-spacing: 1.5px;
            text-shadow: 10px 8px 5px rgba(0, 0, 0, 0.25);
            1px 1px 2px rgba(0, 0, 0, 0.6),
            2px 2px 4px rgba(0, 0, 0, 0.4);
        }
        .sidebar b {
            font-size: 13px;
            font-family: 'Raleway', sans-serif;
            margin-right: 10px;
            margin-left: 80px;
            margin-bottom: 20px;
            color:rgba(202, 223, 244, 0.72);
        }
        .sidebar a {
            color: white;
            padding: 12px 0;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background-color: #34495e;
            padding-left: 10px;
        }
        .has-submenu {
            display: flex;
            flex-direction: column;
        }
        .submenu {
            display: none;
            flex-direction: column;
            margin-left: 20px;
        }
        .has-submenu.active .submenu {
            display: flex;
        }
        .submenu a {
            font-size: 15px;
            padding: 8px 0 8px 10px;
            text-decoration: none;
            color: white;
            transition: background-color 0.3s, padding-left 0.3s;
        }
        .submenu a:hover,
        .submenu a.active {
            background-color: #2c3e50;
            padding-left: 20px;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        .logout-link {
            position: absolute;
            bottom: 80px;
            left: 20px;
            color: white;
            text-decoration: none;
            padding: 12px 10px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .logout-link:hover {
            background-color: #34495e;
            padding-left: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">ClassHub</div>
    <b>Welcome</b>
    <br>
    <div class="has-submenu <?= in_array($page, ['calendar_G1', 'calendar_G2', 'calendar_G3' ]) ? 'active' : '' ?>">
        <a href="#" onclick="toggleSubmenu(event)">Check Classroom Availability</a>
        <div class="submenu">
            <a href="?page=calendar_G1" class="<?= $page === 'calendar_G1' ? 'active' : '' ?>">Room G1</a>
            <a href="?page=calendar_G2" class="<?= $page === 'calendar_G2'  ? 'active' : '' ?>">Room G2</a>
            <a href="?page=calendar_G3" class="<?= $page === 'calendar_G3'  ? 'active' : '' ?>">Room G3</a>
        </div>
    </div>
    <a href="?page=user_booking" class="<?= $page === 'user_booking' ? 'active' : '' ?>">Classroom Booking</a>
    <a href="?page=user_CheckStatus" class="<?= $page === 'user_CheckStatus' ? 'active' : '' ?>">Check Booking Status</a>
    <a href="login.php" class="logout-link"> Logout <i class="fas fa-sign-out-alt"></i></a>
</div>

<div class="main-content">
    <?php
        if ($page === 'calendar_G1') {
            include 'calendar_G1.php';
        } elseif ($page === 'calendar_G2') {
            include 'calendar_G2.php';
        } elseif ($page === 'calendar_G3') {
            include 'calendar_G3.php';
        } elseif ($page === 'user_booking') {
            include 'user_booking.php';
        } elseif ($page === 'user_CheckStatus') {
            include 'user_CheckStatus.php';
        } else {
            echo "<h2>Page Not Found</h2>";
        }
    ?>
</div>

<script>
function toggleSubmenu(event) {
    event.preventDefault();
    const parent = event.target.closest('.has-submenu');
    parent.classList.toggle('active');
}
</script>


</body>
</html>
