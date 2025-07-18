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

$page = $_GET['page'] ?? 'calendar_G1';

$message = "";
$success = false;

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST['action'] === 'processBooking') {
    $room = $_POST['room'];
    $date = $_POST['date'];
    $slot = $_POST['slot'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $reason = $_POST['reason'];
    $page = $_POST['page'] ?? 'user_booking';

    $stmt = $conn->prepare("SELECT classroom_id FROM classrooms WHERE room_name = ? LIMIT 1");
    $stmt->bind_param("s", $room);
    $stmt->execute();
    $stmt->bind_result($classroom_id);
    $stmt->fetch();
    $stmt->close();

    if (!$classroom_id) {
        $message = "Classroom not found.";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE classroom_id = ? AND booking_date = ? AND time_slot = ? AND status IN ('Pending','Approved')");
        $stmt->bind_param("iss", $classroom_id, $date, $slot);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $message = "This slot is already booked.";
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($user_id_found);
            $stmt->fetch();
            $stmt->close();

            if ($user_id_found) {
                $user_id = $user_id_found;
                $stmt = $conn->prepare("INSERT INTO bookings (user_id, classroom_id, name, email, booking_date, time_slot, reason, status)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
                $stmt->bind_param("iisssss", $user_id, $classroom_id, $name, $email, $date, $slot, $reason);

                if ($stmt->execute()) {
                    $message = "Booking submitted successfully. Status: Pending";
                    $success = true;
                } else {
                    $success = false;
                    $message = "An error occurred while processing your booking. Please try again later.";
                }
                $stmt->close();
            } else {
                $message = "The email address you entered is not registered in our system. Please use your ClassHub account email.";
                $success = false;
            }
        }
    }
}

$room = $_GET['room'] ?? $_POST['room'] ?? '';
$date = $_GET['date'] ?? $_POST['date'] ?? '';
$slot = $_GET['slot'] ?? $_POST['slot'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Classroom - ClassHub</title>
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
            margin: 30px 0 10px 10px;
            color: #ffffff;
            letter-spacing: 1.5px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.6),
                         2px 2px 4px rgba(0, 0, 0, 0.4);
        }
        .sidebar b {
            font-size: 13px;
            font-family: 'Raleway', sans-serif;
            margin: 5px 0 20px 80px;
            color: rgba(202, 223, 244, 0.72);
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
            margin-left: 150px;
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
        .form-container {
            background: rgba(255, 250, 250, 0.86);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(255,255,255,0.1);
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
            color: rgb(32, 49, 72);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
    
        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
        }
        input, textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .btn {
            background-color: #203148;
            color: white;
            padding: 12px;
            border: none;
            width: 100%;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
            transition: 0.3s;
        }
        .btn:hover {
            background-color: rgba(48, 56, 65, 0.65);
        }
        .summary {
            font-size: 15px;
            background: rgba(139, 175, 222, 0.2);
            padding: 10px;
            margin-bottom: 20px;
            border-left: 8px solid #4e92cd; 
            line-height: 1.6;
        }
        h4 {
            margin: 0;
            line-height: 1.7;
            font-size: 17px;
        }
        .message {
            text-align: center;
            margin-top: 20px;
            font-weight: bold;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .message a {
            display: inline-block;
            margin-top: 8px;
            color: #004085;
            text-decoration: underline;
            font-weight: normal;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo">ClassHub</div>
    <b>Welcome</b><br>
    <div class="has-submenu <?= in_array($page, ['calendar_G1', 'calendar_G2', 'calendar_G3']) ? 'active' : '' ?>">
        <a href="#" onclick="toggleSubmenu(event)">Check Classroom Availability</a>
        <div class="submenu">
            <a href="user_dashboard.php?page=calendar_G1" class="<?= $page === 'calendar_G1' ? 'active' : '' ?>">Room G1</a>
            <a href="user_dashboard.php?page=calendar_G2" class="<?= $page === 'calendar_G2' ? 'active' : '' ?>">Room G2</a>
            <a href="user_dashboard.php?page=calendar_G3" class="<?= $page === 'calendar_G3' ? 'active' : '' ?>">Room G3</a>
        </div>
    </div>
    <a href="user_dashboard.php?page=user_booking" class="<?= $page === 'user_booking' ? 'active' : '' ?>">Classroom Booking</a>
    <a href="user_dashboard.php?page=user_CheckStatus" class="<?= $page === 'user_CheckStatus' ? 'active' : '' ?>">Check Booking Status</a>
    <a href="login.php" class="logout-link">Logout <i class="fas fa-sign-out-alt"></i></a>
</div>

<div class="main-content">
    <div class="form-container">
        <h1>Book Classroom</h1>
        <div class="summary">
            <h4>Booking Information:</h4>
            <strong>Classroom:</strong> <?= htmlspecialchars($room) ?><br>
            <strong>Date:</strong> <?= htmlspecialchars($date) ?><br>
            <strong>Time Slot:</strong> <?= htmlspecialchars($slot) ?>
        </div>

        <?php if (!$success): ?>
            <form method="POST" action="?page=<?= htmlspecialchars($page) ?>">
                <input type="hidden" name="action" value="processBooking">
                <input type="hidden" name="room" value="<?= htmlspecialchars($room) ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                <input type="hidden" name="slot" value="<?= htmlspecialchars($slot) ?>">
                <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">

                <label>Name:</label>
                <input type="text" name="name" required>

                <label>Email:</label>
                <input type="email" name="email" required>

                <label>Reason:</label>
                <textarea name="reason" required></textarea>

                <button class="btn" type="submit">Confirm Booking</button>

                <?php if ($message): ?>
                    <div class="message <?= $success ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($message) ?>
                        <?php if ($success): ?>
                            <br><a href="user_dashboard.php?page=user_CheckStatus">Check your booking status</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="message success">
                <?= htmlspecialchars($message) ?><br>
                <a href="user_dashboard.php?page=user_CheckStatus">Check your booking status</a>
            </div>
        <?php endif; ?>
    </div>
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





