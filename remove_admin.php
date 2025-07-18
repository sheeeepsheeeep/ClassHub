<?php  
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "ClassHubDB";

$conn = new mysqli($servername, $username, $password, $dbname);


$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_admin'])) {
    $admin_id = $_POST['admin_id'];

    $check_sql = "SELECT * FROM admins WHERE admin_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $admin_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $sql = "DELETE FROM admins WHERE admin_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $admin_id);

        if ($stmt->execute()) {
            $message = "✅ Admin removed successfully!";
            $messageClass = "success";
        } else {
            $message = "❌ Error: " . $stmt->error;
            $messageClass = "error";
        }
        $stmt->close();
    } else {
        $message = "⚠️ Admin ID not found!";
        $messageClass = "warning";
    }

    $check_stmt->close();
}


$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare SQL with search filter
if ($search !== "") {
    $search_param = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT admin_id, name, email FROM admins 
                            WHERE CAST(admin_id AS CHAR) LIKE ? OR name LIKE ? 
                            ORDER BY admin_id ASC");
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $users = $stmt->get_result();
    $stmt->close();
} else {
    $users = $conn->query("SELECT admin_id, name, email FROM admins ORDER BY admin_id ASC");
}


$conn->close();
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Remove Admin</title>
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
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .message {
            font-weight: bold;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 15px;
            text-align: center;
        }

        .message.success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }

        .message.warning {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
        }

        .message.error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color:rgb(32, 49, 72);
            color: white;
            text-align: left;
        }
        /* Center Remove button column */
        td:last-child {
            text-align: center;
            vertical-align: middle;
        }
        button {
            background-color: #e74c3c;
            border: none;
            color: white;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        button:hover {
            background-color: #c0392b;
        }
        form {
            display: inline;
        }
        .search-bar {
            display: flex;             
            align-items: center;        
            margin-bottom: 25px;
            margin-top: 20px;
        }
        .search-bar input[type="text"] {
            padding: 8px 12px;
            width: 250px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .search-bar button {
            padding: 8px 15px;
            margin-left: 5px;
            background-color: #3498db;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
        }
        .search-bar button:hover {
            background-color: #2980b9;
        }

    </style>
    <script>
        function confirmDelete() {
            return confirm('Are you sure you want to delete this admin?');
        }
    </script>
</head>
<body>
    <div class="main-content">
        <div class="container">
            <h1>Remove Admin</h1>
        
            <!-- Search bar -->
            <form method="GET" class="search-bar" action="">
                <input type="hidden" name="page" value="remove_admin">
                <input type="text" name="search" placeholder="Search by Admin ID or Name" value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
            </form>
            
            <?php if (!empty($message)): ?>
                <div class="message <?= $messageClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($users && $users->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Admin ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $user['admin_id'] ?></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirmDelete();">
                                        <input type="hidden" name="admin_id" value="<?= $user['admin_id'] ?>">
                                        <button type="submit" name="remove_admin">Remove</button>

                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No admins found<?= $search ? " for '".htmlspecialchars($search)."'" : "" ?>.</p>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>
