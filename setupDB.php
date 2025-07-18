<?php
$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "ClassHubDB";

// Create connection to MySQL (without selecting a database)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if (!$conn->query($sql)) {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbname);




// Create 'admins' table
$sql = "CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
$conn->query($sql);

// Create 'users' table
$sql = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
$conn->query($sql);

// Create 'classrooms' table 
$sql = "CREATE TABLE IF NOT EXISTS classrooms (
    classroom_id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    availability ENUM('Available', 'Booked') DEFAULT 'Available',
    UNIQUE (room_name, date, time_slot)
)";
$conn->query($sql);

// Create 'bookings' table
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    classroom_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    booking_date DATE NOT NULL,
    time_slot VARCHAR(50) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id) ON DELETE CASCADE
)";
$conn->query($sql);

// Auto-generate classrooms and time slots
$rooms = ['Room G1', 'Room G2', 'Room G3'];
$startDate = strtotime("2025-06-30");
$endDate = strtotime("2025-08-01");

$time_slots = ['8:00 AM - 10:00 AM', '10:00 AM - 12:00 PM', '1:00 PM - 3:00 PM', '3:00 PM - 5:00 PM'];

$stmt = $conn->prepare("INSERT IGNORE INTO classrooms (room_name, date, time_slot, availability) VALUES (?, ?, ?, 'Available')");

for ($date = $startDate; $date <= $endDate; $date += 86400) { 
    $formatted_date = date("Y-m-d", $date);
    foreach ($rooms as $room) {
        foreach ($time_slots as $slot) {
            $stmt->bind_param("sss", $room, $formatted_date, $slot);
            $stmt->execute();
        }
    }
}
$stmt->close();




// Function to get user ID by email
function getUserId($conn, $email) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($user_id);
    $stmt->fetch();
    $stmt->close();
    return $user_id;
}
 
// Function to get classroom ID by room name, date, and time slot
function getClassroomId($conn, $room_name, $date, $time_slot) {
    $stmt = $conn->prepare("SELECT classroom_id FROM classrooms WHERE room_name = ? AND date = ? AND time_slot = ?");
    $stmt->bind_param("sss", $room_name, $date, $time_slot);
    $stmt->execute();
    $stmt->bind_result($classroom_id);
    $stmt->fetch();
    $stmt->close();
    return $classroom_id;
}
 
function insertBooking($conn, $name, $user_email, $room_name, $date, $time_slot, $reason, $status) {
    $user_id = getUserId($conn, $user_email);
    $classroom_id = getClassroomId($conn, $room_name, $date, $time_slot);
    
    if (!$user_id || !$classroom_id) {
        return "Booking failed: User or classroom not found.";
    }

    $stmt = $conn->prepare("SELECT availability FROM classrooms WHERE classroom_id = ?");
    $stmt->bind_param("i", $classroom_id);
    $stmt->execute();
    $stmt->bind_result($availability);
    $stmt->fetch();
    $stmt->close();

    if ($availability === 'Available') {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO bookings (user_id, classroom_id, name, email, booking_date, time_slot, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $user_id, $classroom_id, $name, $user_email, $date, $time_slot, $reason, $status);
            $stmt->execute();
            $stmt->close();

            if ($status === 'Approved') {
                $stmt = $conn->prepare("UPDATE classrooms SET availability = 'Booked' WHERE classroom_id = ?");
                $stmt->bind_param("i", $classroom_id);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            return "Booking successful!";
        } catch (Exception $e) {
            $conn->rollback();
            return "Booking failed: " . $e->getMessage();
        }
    } else {
        return "Booking failed: Classroom is already booked.";
    }
}

// Function to update booking and mark room as 'Booked'
function approveBooking($conn, $booking_id) {
    $conn->begin_transaction();

    try {
        // Get classroom_id associated with the booking
        $stmt = $conn->prepare("SELECT classroom_id FROM bookings WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->bind_result($classroom_id);
        $stmt->fetch();
        $stmt->close();

        if (!$classroom_id) {
            throw new Exception("No classroom found for this booking.");
        }

        // Debugging: Check if classroom_id is correct
        echo "Updating classroom_id: " . $classroom_id . "<br>";

        // Update the booking status
        $stmt = $conn->prepare("UPDATE bookings SET status='Approved' WHERE booking_id=?");
        $stmt->bind_param("i", $booking_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating booking status: " . $stmt->error);
        }
        $stmt->close();
        
        // Update the classroom availability
        $stmt = $conn->prepare("UPDATE classrooms SET availability='Booked' WHERE classroom_id=?");
        $stmt->bind_param("i", $classroom_id);
        if (!$stmt->execute()) {
            throw new Exception("Error updating classroom availability: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo "Classroom updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
}


 
// Function to check available classrooms
function getAvailableClassrooms($conn, $date, $time_slot) {
    $stmt = $conn->prepare("SELECT room_name FROM classrooms WHERE date=? AND time_slot=? AND availability='Available'");
    $stmt->bind_param("ss", $date, $time_slot);
    $stmt->execute();
    $stmt->bind_result($room_name);
 
    $rooms = [];
    while ($stmt->fetch()) {
        $rooms[] = $room_name;
    }
    $stmt->close();
    return $rooms;
}
 
echo "Classroom setup completed with auto-generated schedules. <br>";

 
// Function to hash passwords
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
 
// Insert admins
$admins = [
    ['Jennie', 'jennie@gmail.com', 'admin123'],
    ['Jisoo', 'jisoo@gmail.com', 'admin123'],
    ['Rosie', 'rosie@gmail.com', 'admin123'],
    ['Xuan Ling', 'xuanling@gmail.com', 'admin123'],
    ['Yen Zhi', 'yenzhi@gmail.com', 'admin123'],
   
    
];
 
$stmt = $conn->prepare("INSERT IGNORE INTO admins (name, email, password) VALUES (?, ?, ?)");
foreach ($admins as $admin) {
    $hashed_password = hashPassword($admin[2]);
    $stmt->bind_param("sss", $admin[0], $admin[1], $hashed_password);
    $stmt->execute();
}
$stmt->close();

 
// Insert users
$users = [
    ['Alden', 'alden@gmail.com', 'user123'],
    ['Adeline', 'adeline@gmail.com', 'user123'],
    ['Alice', 'alice@gmail.com', 'user123'],
    ['Bryan', 'bryan@gmail.com', 'user123'],
    ['Bernice', 'bernice@gmail.com', 'user123'],
    ['Blackie', 'blackie@gmail.com', 'user123'],
    ['Brownie', 'brownie@gmail.com', 'user123'],
    ['Ehang', 'ehang@gmail.com', 'user123'],
    ['Jasmine', 'jasmine@gmail.com', 'user123'],
    ['Jessica', 'jessica@gmail.com', 'user123'],
    ['Pi Zhen', 'pizhen@gmail.com', 'user123'],
    ['Jia Yin', 'jiayin@gmail.com', 'user123'],
    ['Jie Ren', 'jieren@gmail.com', 'user123'],
    ['Juhi', 'juhi@gmail.com', 'user123'],
    ['Isabelle', 'isabelle@gmail.com', 'user123'],
    ['Qi Yun', 'qiyun@gmail.com', 'user123'],
    ['Vivian', 'vivian@gmail.com', 'user123'],
    ['Yong Shu', 'yongshu@gmail.com', 'user123'],
    ['Zhi Heng', 'zhiheng@gmail.com', 'user123'],
    ['Ze Hang', 'zehang@gmail.com', 'user123'],

];
 
$stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password) VALUES (?, ?, ?)");
foreach ($users as $user) {
    $hashed_password = hashPassword($user[2]);
    $stmt->bind_param("sss", $user[0], $user[1], $hashed_password);
    $stmt->execute();
}
$stmt->close();
 
// Sample bookings data
$bookings = [
    //bookings for 2025/07/22 (TO SHOW BOOKED IN CALENDAR)
    ['Alden', 'alden@gmail.com', 'Room G1', '2025-07-22', '8:00 AM - 10:00 AM', 'Study group meeting', 'Approved'],
    ['Blackie', 'blackie@gmail.com', 'Room G1', '2025-07-22', '10:00 AM - 12:00 PM', 'Pets caring event', 'Approved'],
    ['Juhi', 'juhi@gmail.com', 'Room G1', '2025-07-22', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Vivian', 'vivian@gmail.com', 'Room G1', '2025-07-22', '3:00 PM - 5:00 PM', 'Project video shooting', 'Approved'],


    ['Jia Yin', 'jiayin@gmail.com', 'Room G2', '2025-07-22', '8:00 AM - 10:00 AM', 'Project showcase', 'Approved'],
    ['Ehang', 'ehang@gmail.com', 'Room G2', '2025-07-22', '10:00 AM - 12:00 PM', 'VR game event', 'Approved'],
    ['Qi Yun', 'qiyun@gmail.com', 'Room G2', '2025-07-22', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Yong Shu', 'yongshu@gmail.com', 'Room G2', '2025-07-22', '3:00 PM - 5:00 PM', 'Lecture replacement', 'Approved'],

    ['Jasmine', 'jasmine@gmail.com', 'Room G3', '2025-07-22', '8:00 AM - 10:00 AM', 'Web programming presentation', 'Approved'],
    ['Isabelle', 'isabelle@gmail.com', 'Room G3', '2025-07-22', '10:00 AM - 12:00 PM', 'Design workshops', 'Approved'],
    ['Alice', 'alice@gmail.com', 'Room G3', '2025-07-22', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Adeline', 'adeline@gmail.com', 'Room G3', '2025-07-22', '3:00 PM - 5:00 PM', 'E-commerce video shooting', 'Approved'],


    //bookings for 2025/07/23(TO SHOW BOOKED IN CALENDAR)
    ['Bernice', 'bernice@gmail.com', 'Room G1', '2025-07-23', '8:00 AM - 10:00 AM', 'MPU class replacement', 'Approved'],
    ['Bernice', 'bernice@gmail.com', 'Room G1', '2025-07-23', '10:00 AM - 12:00 PM', 'MPU class replacement', 'Approved'],
    ['Juhi', 'juhi@gmail.com', 'Room G1', '2025-07-23', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Vivian', 'vivian@gmail.com', 'Room G1', '2025-07-23', '3:00 PM - 5:00 PM', 'Project video shooting', 'Approved'],


    ['Jessica', 'jessica@gmail.com', 'Room G2', '2025-07-23', '8:00 AM - 10:00 AM', 'Assignment discussion', 'Approved'],
    ['Ehang', 'ehang@gmail.com', 'Room G2', '2025-07-23', '10:00 AM - 12:00 PM', 'Club committee discussion', 'Approved'],
    ['Qi Yun', 'qiyun@gmail.com', 'Room G2', '2025-07-23', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Yong Shu', 'yongshu@gmail.com', 'Room G2', '2025-07-23', '3:00 PM - 5:00 PM', 'Lecture replacement', 'Approved'],

    ['Jia Yin', 'jiayin@gmail.com', 'Room G3', '2025-07-23', '8:00 AM - 10:00 AM', 'Algorithm presentation', 'Approved'],
    ['Isabelle', 'isabelle@gmail.com', 'Room G3', '2025-07-23', '10:00 AM - 12:00 PM', 'Design showcase', 'Approved'],
    ['Alice', 'alice@gmail.com', 'Room G3', '2025-07-23', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Adeline', 'adeline@gmail.com', 'Room G3', '2025-07-23', '3:00 PM - 5:00 PM', 'E-commerce video shooting', 'Approved'],



    //Bookings for 2025/07/24(TO SHOW BOOKED IN CALENDAR)
    ['Bryan', 'bryan@gmail.com', 'Room G1', '2025-07-24', '8:00 AM - 10:00 AM', 'Language practice', 'Approved'],
    ['Ehang', 'ehang@gmail.com', 'Room G1', '2025-07-24', '10:00 AM - 12:00 PM', 'Self study', 'Approved'],
    ['Jie Ren', 'jieren@gmail.com', 'Room G1', '2025-07-24', '1:00 PM - 3:00 PM', 'Hackathon session', 'Approved'],
    ['Jie Ren', 'jieren@gmail.com', 'Room G1', '2025-07-24', '3:00 PM - 5:00 PM', 'Hackathon session', 'Approved'],


    ['Alice', 'alice@gmail.com', 'Room G2', '2025-07-24', '8:00 AM - 10:00 AM', 'Assignment discussion', 'Approved'],
    ['Zhi Heng', 'zhiheng@gmail.com', 'Room G2', '2025-07-24', '10:00 AM - 12:00 PM', 'Club committee discussion', 'Approved'],
    ['Qi Yun', 'qiyun@gmail.com', 'Room G2', '2025-07-24', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Yong Shu', 'yongshu@gmail.com', 'Room G2', '2025-07-24', '3:00 PM - 5:00 PM', 'Lecture replacement', 'Approved'],

    ['Brownie', 'brownie@gmail.com', 'Room G3', '2025-07-24', '8:00 AM - 10:00 AM', 'Algorithm presentation', 'Approved'],
    ['Ze Hang', 'zehang@gmail.com', 'Room G3', '2025-07-24', '10:00 AM - 12:00 PM', 'Photography session', 'Approved'],
    ['Ze Hang', 'zehang@gmail.com', 'Room G3', '2025-07-24', '1:00 PM - 3:00 PM', 'Photography session', 'Approved'],
    ['Alden', 'alden@gmail.com', 'Room G3', '2025-07-24', '3:00 PM - 5:00 PM', 'Assignment discussion', 'Approved'],

    // Bookings for 2025-07-25(TO SHOW BOOKED IN CALENDAR)
    ['Bernice', 'bernice@gmail.com', 'Room G1', '2025-07-25', '8:00 AM - 10:00 AM', 'MPU class replacement', 'Approved'],
    ['Juhi', 'juhi@gmail.com', 'Room G1', '2025-07-25', '10:00 AM - 12:00 PM', 'Presentation preparation', 'Approved'],
    ['Vivian', 'vivian@gmail.com', 'Room G1', '2025-07-25', '1:00 PM - 3:00 PM', 'Project video shooting', 'Approved'],
    ['Vivian', 'vivian@gmail.com', 'Room G1', '2025-07-25', '3:00 PM - 5:00 PM', 'Project video shooting', 'Approved'],

    ['Alden', 'alden@gmail.com', 'Room G2', '2025-07-25', '8:00 AM - 10:00 AM', 'Assignment discussion', 'Approved'],
    ['Pi Zhen', 'pizhen@gmail.com', 'Room G2', '2025-07-25', '10:00 AM - 12:00 PM', 'Club committee discussion', 'Approved'],
    ['Qi Yun', 'qiyun@gmail.com', 'Room G2', '2025-07-25', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Zhi Heng', 'zhiheng@gmail.com', 'Room G2', '2025-07-25', '3:00 PM - 5:00 PM', 'Thesis discussion', 'Approved'],

    ['Jasmine', 'jiayin@gmail.com', 'Room G3', '2025-07-25', '8:00 AM - 10:00 AM', 'Algorithm presentation', 'Approved'],
    ['Isabelle', 'isabelle@gmail.com', 'Room G3', '2025-07-25', '10:00 AM - 12:00 PM', 'Design meetings', 'Approved'],
    ['Alice', 'alice@gmail.com', 'Room G3', '2025-07-25', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Adeline', 'adeline@gmail.com', 'Room G3', '2025-07-25', '3:00 PM - 5:00 PM', 'E-commerce video shooting', 'Approved'],


    //2025/07/26(TO SHOW BOOKED IN CALENDAR)
    ['Bryan', 'bryan@gmail.com', 'Room G1', '2025-07-26', '8:00 AM - 10:00 AM', 'LEO club meetings', 'Approved'],
    ['Bernice', 'bernice@gmail.com', 'Room G1', '2025-07-26', '10:00 AM - 12:00 PM', 'MPU class replacement', 'Approved'],
    ['Juhi', 'juhi@gmail.com', 'Room G1', '2025-07-26', '1:00 PM - 3:00 PM', 'Exam preparation', 'Approved'],
    ['Vivian', 'vivian@gmail.com', 'Room G1', '2025-07-26', '3:00 PM - 5:00 PM', 'Group study', 'Approved'],


    ['Jessica', 'jessica@gmail.com', 'Room G2', '2025-07-26', '8:00 AM - 10:00 AM', 'Assignment discussion', 'Approved'],
    ['Ehang', 'ehang@gmail.com', 'Room G2', '2025-07-26', '10:00 AM - 12:00 PM', 'Club committee discussion', 'Approved'],
    ['Pi Zhen', 'pizhen@gmail.com', 'Room G2', '2025-07-26', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Ze Hang', 'zehang@gmail.com', 'Room G2', '2025-07-26', '3:00 PM - 5:00 PM', 'Lecture replacement', 'Approved'],

    ['Jia Yin', 'jiayin@gmail.com', 'Room G3', '2025-07-26', '8:00 AM - 10:00 AM', 'Algorithm presentation', 'Approved'],
    ['Jie Ren', 'jieren@gmail.com', 'Room G3', '2025-07-26', '10:00 AM - 12:00 PM', 'AI-robotics showcase', 'Approved'],
    ['Alden', 'alden@gmail.com', 'Room G3', '2025-07-26', '1:00 PM - 3:00 PM', 'Presentation preparation', 'Approved'],
    ['Brownie', 'brownie@gmail.com', 'Room G3', '2025-07-26', '3:00 PM - 5:00 PM', 'I-food event', 'Approved'],


//--Bookings after 2025/07/26-----------------------------------------------------------------------------------------------/
    ['Alice', 'alice@gmail.com', 'Room G1', '2025-07-27', '8:00 AM - 10:00 AM', 'Study group meeting', 'Approved'],
    ['Alden', 'alden@gmail.com', 'Room G1', '2025-07-27', '10:00 AM - 12:00 PM', 'Study group meeting', 'Approved'],
    ['Jessica', 'jessica@gmail.com', 'Room G1', '2025-07-27', '8:00 AM - 10:00 AM', 'Assignment discussion', 'Approved'],
    ['Ehang', 'ehang@gmail.com', 'Room G1', '2025-07-27', '10:00 AM - 12:00 PM', 'Club committee discussion', 'Approved'],
    ['Blackie', 'blackie@gmail.com', 'Room G2', '2025-07-27', '8:00 AM - 10:00 AM', 'Project discussion', 'Approved'],
    ['Brownie', 'brownie@gmail.com', 'Room G2', '2025-07-27', '10:00 AM - 12:00 PM', 'Project discussion', 'Approved'],
    ['Jie Ren', 'blackie@gmail.com', 'Room G3', '2025-07-27', '8:00 AM - 10:00 AM', 'Tech club meetings', 'Approved'],
    ['Juhi', 'brownie@gmail.com', 'Room G3', '2025-07-27', '10:00 AM - 12:00 PM', 'Presentation preparation', 'Approved'],
    ['Brownie', 'brownie@gmail.com', 'Room G2', '2025-07-28', '10:00 AM - 12:00 PM', 'Presentation preparation', 'Approved'],
    ['Jasmine', 'jasmine@gmail.com', 'Room G1', '2025-07-28', '3:00 PM - 5:00 PM', 'E-commerce event', 'Approved'],
    ['Ehang', 'ehang@gmail.com', 'Room G2', '2025-07-28', '1:00 PM - 3:00 PM', 'Tech seminar', 'Approved'],
    ['Jia Yin', 'jiayin@gmail.com', 'Room G2', '2025-07-28', '8:00 AM - 10:00 AM', 'Extra class', 'Approved'],
    ['Jie Ren', 'jieren@gmail.com', 'Room G2', '2025-07-28', '10:00 AM - 12:00 PM', 'Multilanguage workshops', 'Pending'],
    ['Vivian', 'vivian@gmail.com', 'Room G2', '2025-07-28', '3:00 PM - 5:00 PM', 'Coding event', 'Approved'],
    ['Isabelle', 'isabelle@gmail.com', 'Room G3', '2025-07-29', '3:00 PM - 5:00 PM', 'Fashion show', 'Approved'],
    ['Pi Zhen', 'pizhen@gmail.com', 'Room G1', '2025-07-30', '1:00 PM - 3:00 PM', 'Group study', 'Approved'],   
    ['Qi Yun', 'qiyun@gmail.com', 'Room G3', '2025-07-30', '10:00 AM - 12:00 PM', 'Exam preparation', 'Pending'],
    ['Ze Hang', 'zehang@gmail.com', 'Room G2', '2025-07-30', '1:00 PM - 3:00 PM', 'Exam preparation', 'Pending'],
    ['Zhi Heng', 'zhiheng@gmail.com', 'Room G2', '2025-07-30', '3:00 PM - 5:00 PM', 'Exam preparation', 'Pending'],
    ['Jessica', 'jessica@gmail.com', 'Room G3', '2025-07-31', '3:00 PM - 5:00 PM', 'Self study', 'Pending'],
    ['Jasmine', 'jasmine@gmail.com', 'Room G1', '2025-07-31', '1:00 PM - 3:00 PM', 'Group study', 'Pending'],
    ['Jia Yin', 'jiayin@gmail.com', 'Room G1', '2025-07-31', '3:00 PM - 5:00 PM', 'Self study', 'Pending'],


];
 
 
// Insert bookings using our function
foreach ($bookings as $booking) {
    insertBooking($conn, $booking[0], $booking[1], $booking[2], $booking[3], $booking[4], $booking[5], $booking[6]);
}
 
 
$conn->query("ALTER TABLE admins AUTO_INCREMENT = 1");
$conn->query("ALTER TABLE users AUTO_INCREMENT = 1");
$conn->query("ALTER TABLE classrooms AUTO_INCREMENT = 1");
$conn->query("ALTER TABLE bookings AUTO_INCREMENT = 1");
 
echo "<br>Database setup completed successfully!";


$conn->close();
?>
