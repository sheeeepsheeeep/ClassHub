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

// HELPER FUNCTIONS
function getUserId($conn, $email) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($user_id);
    $stmt->fetch();
    $stmt->close();
    return $user_id;
}

function getClassroomId($conn, $room_name, $date, $time_slot) {
    $stmt = $conn->prepare("SELECT classroom_id FROM classrooms WHERE room_name = ? AND date = ? AND time_slot = ?");
    $stmt->bind_param("sss", $room_name, $date, $time_slot);
    $stmt->execute();
    $stmt->bind_result($classroom_id);
    $stmt->fetch();
    $stmt->close();

    if (!$classroom_id) {
        $insertStmt = $conn->prepare("INSERT INTO classrooms (room_name, date, time_slot) VALUES (?, ?, ?)");
        $insertStmt->bind_param("sss", $room_name, $date, $time_slot);
        $insertStmt->execute();
        $classroom_id = $insertStmt->insert_id;
        $insertStmt->close();
    }

    return $classroom_id;
}

function insertBooking($conn, $name, $email, $room_name, $date, $time_slot, $reason, $status = 'Pending') {
    $user_id = getUserId($conn, $email);
    $classroom_id = getClassroomId($conn, $room_name, $date, $time_slot);

    if (!$user_id || !$classroom_id) {
        return "<div style='color: red;'>Booking failed: User or classroom not found.</div>";
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings 
        WHERE classroom_id = ? AND booking_date = ? AND time_slot = ? 
        AND status IN ('Pending', 'Approved')");
    $stmt->bind_param("iss", $classroom_id, $date, $time_slot);
    $stmt->execute();
    $stmt->bind_result($existing);
    $stmt->fetch();
    $stmt->close();

    if ($existing > 0) {
        return "<div style='color: red;'>Booking failed: Time slot already taken.</div>";
    }

    $conn->autocommit(false);
    try {
        $stmt = $conn->prepare("INSERT INTO bookings (user_id, classroom_id, name, email, booking_date, time_slot, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssss", $user_id, $classroom_id, $name, $email, $date, $time_slot, $reason, $status);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return "<div style='color: green;'>Booking submitted successfully. Status: $status</div>";
    } catch (Exception $e) {
        $conn->rollback();
        return "<div style='color: red;'>Booking failed. Please try again.</div>";
    } finally {
        $conn->autocommit(true);
    }
}

// Handle AJAX for booked time slots
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $room = $_GET['classroom'];
    $date = $_GET['date'];

    $stmt = $conn->prepare("SELECT DISTINCT bookings.time_slot FROM bookings 
        INNER JOIN classrooms ON bookings.classroom_id = classrooms.classroom_id 
        WHERE classrooms.room_name = ? AND bookings.booking_date = ? 
        AND bookings.status IN ('Pending', 'Approved')");
    $stmt->bind_param("ss", $room, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row['time_slot'];
    }
    echo json_encode($slots);
    exit;
}

// Handle AJAX for room availability
if (isset($_GET['ajax']) && $_GET['ajax'] == 'room_availability') {
    $date = $_GET['date'];
    $rooms = ['Room G1', 'Room G2', 'Room G3'];
    $roomStatus = [];

    foreach ($rooms as $room) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings 
            INNER JOIN classrooms ON bookings.classroom_id = classrooms.classroom_id 
            WHERE classrooms.room_name = ? AND bookings.booking_date = ? 
            AND bookings.status IN ('Pending', 'Approved')");
        $stmt->bind_param("ss", $room, $date);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        $roomStatus[$room] = $count;
    }

    echo json_encode($roomStatus);
    exit;
}

// Handle Form Submission
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_booking"])) {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $classroom = $_POST["classroom"];
    $date = $_POST["date"];
    $time_slot = $_POST["time_slot"];
    $reason = $_POST["reason"];
    $message = insertBooking($conn, $name, $email, $classroom, $date, $time_slot, $reason);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ClassHub Booking</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: rgba(207, 207, 207, 0.96);
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .main-content {
            margin-left: 150px;
            padding: 30px;
        }
        .container {
            background: rgba(255, 250, 250, 0.86);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        label {
            display: block;
            margin-top: 20px;
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .required::after {
            content: " *";
            color: red;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        textarea {
            resize: none;
            height: 80px;
        }
        .time_slot_container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .time {
            padding: 10px 15px;
            border: 2px solid rgb(86, 112, 140);
            border-radius: 8px;
            cursor: pointer;
            background: white;
            color: rgb(0, 53, 110);
        }
        .time:hover {
            background: #e6f0ff;
        }
        .time.selected {
            background: rgb(106, 156, 208);
            color: white;
        }
        .time.booked {
            background: #ccc;
            color: #666;
            pointer-events: none;
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
    </style>
</head>
<body>
<div class="main-content">
    <div class="container">
        <h1>Book a Classroom</h1>
        <form id="bookingForm" action="" method="POST">
            <label class="required" for="name">Name :</label>
            <input type="text" name="name" id="name" required>

            <label class="required" for="email">Email :</label>
            <input type="email" name="email" id="email" required>

            <label class="required" for="date">Select Date :</label>
            <input type="date" id="date" name="date" required>

            <label class="required" for="classroom">Select Classroom :</label>
            <select name="classroom" id="classroom" required>
                <option value="">-- Choose Classroom --</option>
                <option value="Room G1">Room G1</option>
                <option value="Room G2">Room G2</option>
                <option value="Room G3">Room G3</option>
            </select>

            <label class="required">Time Slot :</label>
            <input type="hidden" name="time_slot" id="time_slot" required>
            <div class="time_slot_container">
                <div class="time" data-value="8:00 AM - 10:00 AM">8:00 AM - 10:00 AM</div>
                <div class="time" data-value="10:00 AM - 12:00 PM">10:00 AM - 12:00 PM</div>
                <div class="time" data-value="1:00 PM - 3:00 PM">1:00 PM - 3:00 PM</div>
                <div class="time" data-value="3:00 PM - 5:00 PM">3:00 PM - 5:00 PM</div>
            </div>

            <label class="required" for="reason">Reason for Booking :</label>
            <textarea name="reason" id="reason" required></textarea>

            <button type="submit" name="submit_booking" class="btn">Book Classroom</button>

            <!-- Show message here -->
            <?php if (!empty($message)) echo "<div style='margin-top:20px; text-align:center; fornt-align: bold;'>$message</div>"; ?>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const classroom = document.getElementById("classroom");
    const date = document.getElementById("date");
    const timeSlotDivs = document.querySelectorAll(".time");
    const timeSlotInput = document.getElementById("time_slot");

    function updateBookedSlots() {
        const room = classroom.value;
        const selectedDate = date.value;
        if (!room || !selectedDate) return;

        fetch(`user_booking.php?ajax=1&classroom=${encodeURIComponent(room)}&date=${encodeURIComponent(selectedDate)}`)
            .then(res => res.json())
            .then(data => {
                timeSlotDivs.forEach(div => {
                    const val = div.dataset.value;
                    div.classList.remove("booked", "selected");
                    if (data.includes(val)) {
                        div.classList.add("booked");
                    }
                });
            });
    }

    function updateRoomOptions() {
        const selectedDate = date.value;
        if (!selectedDate) return;

        fetch(`user_booking.php?ajax=room_availability&date=${encodeURIComponent(selectedDate)}`)
            .then(res => res.json())
            .then(data => {
                for (const room in data) {
                    const option = [...classroom.options].find(opt => opt.value === room);
                    if (option) {
                        option.disabled = data[room] >= 4;
                    }
                }
            });
    }

    timeSlotDivs.forEach(div => {
        div.addEventListener("click", () => {
            if (div.classList.contains("booked")) return;
            timeSlotDivs.forEach(d => d.classList.remove("selected"));
            div.classList.add("selected");
            timeSlotInput.value = div.dataset.value;
        });
    });

    classroom.addEventListener("change", updateBookedSlots);
    date.addEventListener("change", () => {
        updateRoomOptions();
        updateBookedSlots();
    });

    const today = new Date().toISOString().split("T")[0];
    date.setAttribute("min", today);

    document.getElementById("bookingForm").addEventListener("submit", function (e) {
        if (!timeSlotInput.value) {
            alert("Please select a time slot.");
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>
