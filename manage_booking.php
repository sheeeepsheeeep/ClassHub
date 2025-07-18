<?php
session_start();
ob_start(); // Prevents header errors


// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ClassHubDB";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Approve/Reject Actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id']);
    $status = $_POST['action'] == "approve" ? "Approved" : "Rejected";

    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
    $stmt->bind_param("si", $status, $booking_id);
    $stmt->execute();
    $stmt->close();

    // If approved, update classroom availability
    if ($status === "Approved") {
        $updateClassroomQuery = "
            UPDATE classrooms 
            SET availability = 'Booked' 
            WHERE classroom_id = (SELECT classroom_id FROM bookings WHERE booking_id = ?)
            AND date = (SELECT booking_date FROM bookings WHERE booking_id = ?)
            AND time_slot = (SELECT time_slot FROM bookings WHERE booking_id = ?)";
        
        $stmt2 = $conn->prepare($updateClassroomQuery);
        $stmt2->bind_param("iii", $booking_id, $booking_id, $booking_id);
        $stmt2->execute();
        $stmt2->close();
    }
}


    // Default: Fetch all bookings
    $sql = "SELECT b.booking_id, b.user_id, b.classroom_id, c.room_name AS classroom_name, 
                   b.name, b.email, b.booking_date, b.time_slot, b.reason, b.status 
            FROM bookings b
            JOIN classrooms c ON b.classroom_id = c.classroom_id
            ORDER BY b.booking_id DESC";


$result = $conn->query($sql);
?>


<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Manage Booking</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color:rgba(207, 207, 207, 0.96);
        }
        .main-content {
            margin-left: 150px;
            padding: 30px;

        }
        .container {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background:rgba(255, 250, 250, 0.86);
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .manage_containermessage {
            font-weight: bold;
            color: #f39c12;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
           background:rgb(32, 49, 72);
           border: 1px solid #333;
           padding: 8px;
           text-align: left;
           color:  white;
        }
        #action {
            background:rgb(32, 49, 72);
            border: 1px solid #333;
            padding: 8px;
            text-align: center;
            color:  white;
        }
        td:last-child {
            text-align: center;
            vertical-align: middle;
        }
        .approved {
            color: rgb(2, 144, 85); 
            font-weight: bold; 
        }
        .rejected {
            color: rgb(255, 5, 5);
            font-weight: bold; 
        }
        .pending {
            color: rgb(229, 141, 25);
            font-weight: bold; 
        }
        .action-btn {
            padding: 6px 10px;
            border: none;
            color: white;
            cursor: pointer;
             border-radius: 5px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            transition: 0.3s ease-in-out;
        }
        .approve-btn {
            background-color: rgb(2, 144, 85);
        }
        .approve-btn:hover {
            background:rgb(37, 73, 53)
        }
        .reject-btn {
            background: #e74c3c;
        }
        .reject-btn:hover {
            background: #c0392b;
        }
        form {
            display: inline;
        }
    
    </style>
    <script>
        function confirmDelete() {
            return confirm('Are you sure you want to delete this user?');
        }
    </script>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <h1>Manage Booking</h1>

            <table>
                <tr>
                    <th>BOOKING ID</th>
                    <th>USER EMAIL</th>
                    <th>REQUEST DETAILS</th>
                    <th>STATUS</th>
                    <th id="action">ACTION</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $row["booking_id"]; ?></td>
                        <td><?php echo htmlspecialchars($row["email"]); ?></td>
                        <td>
                            <strong>Booking ID:</strong> <?php echo $row["booking_id"]; ?><br>
                            <strong>User ID:</strong> <?php echo $row["user_id"]; ?><br>
                            <strong>Classroom ID:</strong> <?php echo $row["classroom_id"]; ?><br>
                            <strong>Classroom Name:</strong> <?php echo htmlspecialchars($row["classroom_name"]); ?><br>
                            <strong>Name:</strong> <?php echo htmlspecialchars($row["name"]); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($row["email"]); ?><br>
                            <strong>Booking Date:</strong> <?php echo $row["booking_date"]; ?><br>
                            <strong>Time Slot:</strong> <?php echo $row["time_slot"]; ?><br>
                            <strong>Reason:</strong> <?php echo htmlspecialchars($row["reason"]); ?>
                        </td>
                        <td class="status <?php echo strtolower($row["status"]); ?>"><?php echo $row["status"]; ?></td>
                        <td>
                            <?php if ($row["status"] == "Pending") { ?>
                                <form method="POST">
                                    <input type="hidden" name="booking_id" value="<?php echo $row["booking_id"]; ?>">
                                    <button type="submit" name="action" value="approve" class="action-btn approve-btn">Approve</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="booking_id" value="<?php echo $row["booking_id"]; ?>">
                                    <button type="submit" name="action" value="reject" class="action-btn reject-btn">Reject</button>
                                </form>
                            <?php } else { echo "—"; } ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>

