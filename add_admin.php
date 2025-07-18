<?php  
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "ClassHubDB";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$messageClass = "";

// Add Admin
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_admin'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $check_sql = "SELECT * FROM admins WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $message = "⚠️ Admin already exists!";
        $messageClass = "warning";
    } else {
        $sql = "INSERT INTO admins (name, email, password) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $name, $email, $password);

        if ($stmt->execute()) {
            $message = "✅ Admin added successfully!";
            $messageClass = "success";
        } else {
            $message = "❌ Error: " . $stmt->error;
            $messageClass = "error";
        }
        $stmt->close();
    }
    $check_stmt->close();
}
$conn->close();
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Add New Admin</title>
    <style>
        .main-content {
            margin-left: 150px;
            padding: 30px;
        }
        .add_container {
            background: rgba(255, 250, 250, 0.86);
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(255,255,255,0.1);
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
            color: rgb(32, 49, 72);
            font-family: Arial, sans-serif;
        }
        h1 {
            color:rgb(0, 0, 0);
            text-align: center;
            margin-bottom: 20px;
        }

        .message {
            text-align: center;
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
            font-size: 15px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .add_container form {
            display: flex;
            flex-direction: column;
        }
        .add_container label {
            margin: 8px 0 4px;
            font-weight: bold;
        }
        .add_container input {
            padding: 8px;
            border-radius: 5px;
            border: none;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .add_container input[type="text"],
        .add_container input[type="email"],
        .add_container input[type="password"] {
            background-color: rgba(162, 162, 162, 0.3);
        }
        .add_container button {
            padding: 10px;
            border: none;
            border-radius: 5px;
            background-color: rgb(32, 49, 72);
            color: white;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .add_container button:hover {
            background-color: rgba(48, 56, 65, 0.65);
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="add_container">
            <h1>Add Admin</h1>

            <form method="POST" action="">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit" name="add_admin">Add Admin</button>
            </form>

            <?php if ($message): ?>
                <div class="message <?= $messageClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
        </div>
    </div>
</body>
</html>

