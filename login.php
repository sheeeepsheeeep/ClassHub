<?php 
session_start();
ob_start(); // Prevents header() errors due to prior output

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ClassHubDB";

$conn = new mysqli($servername, $username, $password, $dbname);


// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Login Logic
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role = $_POST['role']; // 'user' or 'admin'

    if ($role == 'user') {
        $stmt = $conn->prepare("SELECT user_id AS id, email, password FROM users WHERE email = ?");
    } else {
        $stmt = $conn->prepare("SELECT admin_id AS id, email, password FROM admins WHERE email = ?");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $role;
        
        // Redirect based on role
        header("Location: " . ($role == 'admin' ? "admin_dashboard.php" : "user_dashboard.php"));
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}
$conn->close();
?>

<html>

<head>
  <title>ClassHub Login Page</title>
  <link rel = "stylesheet"  href = "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel = "stylesheet"  href = "https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Raleway:wght@400;500&display=swap">

  <style>
    
    body {
      font-family: 'Raleway', sans-serif;
      margin: 0;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      background: linear-gradient(135deg, rgb(32, 49, 72) 0%, rgba(183, 188, 194, 0.76) 100%);
    }
    .logo {
      font-family: 'Poppins', sans-serif;
      font-size: 30px;
      font-weight: 800;
      color: #ffffff; 
      letter-spacing: 1.5px;
      text-shadow: 10px 8px 5px rgba(0, 0, 0, 0.25);
      1px 1px 2px rgba(0, 0, 0, 0.6),
      2px 2px 4px rgba(0, 0, 0, 0.4);
    }
    .main_box {
      display: flex;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      position: absolute;
      align-items: stretch;
    }
    .left_box {
      background: rgb(32, 49, 72);
      color: rgb(183, 188, 194);
      backdrop-filter: blur(10px);
      padding: 40px 30px;
      width: 320px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .home-icon {
      position: absolute;
      top: 20px;
      left: 20px;
      margin-top: 5px;
      font-size: 20px;
      color:rgb(166, 195, 221); 
      text-decoration: none;
      z-index: 10;
      transition: color 0.3s;
    }
    .home-icon:hover {
      color:rgb(223, 232, 239); 
    }
    .left_box h1 {
      font-size: 30px;
      text-align: center;
    }
    .role_selection {
      margin-top: 30px;
      display: flex;
      gap: 20px;
    }
    .role_selection label {
      color: white;
      font-size: 16px;
      font-family: Arial, sans-serif;
    }
    .login {
      background: rgba(183, 188, 194, 0.76);
      backdrop-filter: blur(10px);
      padding: 40px 30px;
      width: 320px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      text-align: center;
    }
    .user_icon {
      font-size: 60px;
      color: rgb(32, 49, 72);
      margin-bottom: 20px;
    }
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border: none;
      border-radius: 5px;
      background-color: rgba(101, 119, 143, 0.61);  
    }
    input::placeholder {
      color: rgb(32, 49, 72);
    }
    button {
      margin-top: 20px;
      margin-bottom: 15px;
      padding: 12px;
      width: 100%;
      background-color:rgb(32, 49, 72);
      color: white;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      cursor: pointer;
      transition: background 0.3s;
    }
    button:hover {
      background-color:rgba(68, 80, 95, 0.89);
    }
    .error {
      color: red;
      font-size: 14px;
      margin-top: 10px;
    }
    .tab-container {
      margin-top: 20px;
    }
    .tab {
      display: inline-block;
      padding: 10px 20px;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      font-weight: bold;
      color: #555;
    }
    .tab.active {
      border-bottom: 2px solid #333;
      color: #000;
    }
    .form-container {
      display: none;
    }
    .form-container.active {
      display: block;
    }
  </style>

</head>

<body>

  <div class="main_box">

    <div class="left_box">
      <a href="index.html" class="home-icon" title="Back to Home">
        <i class="fa-solid fa-house"></i>
      </a>
      <h1>Welcome Back to</h1>
      <div class="logo">ClassHub</div>
      <div class="role_selection">
        <label>
          <input type="checkbox" name="role" value="user" id="user_checkbox" checked /> User
        </label>
        <label>
          <input type="checkbox" name="role" value="admin" id="admin_checkbox" /> Admin
        </label>
      </div>
    </div>

    <div class="login">
      <div class="user_icon"><i class="fa-regular fa-circle-user"></i></div>
      <form method="POST">
        <input type="hidden" id="selected_role" name="role" value="user">
        <input type="email" name="email" placeholder="Email" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">LOGIN</button>
        <a id="register-link" href="user_register.php" style="color: #203148; font-family: Arial, sans-serif; font-weight: bold; font-size: 14px; text-decoration: underline;">Don't have an account? Register here</a>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
      </form>
    </div>
    
  </div>

  <script>
    const userCheckbox = document.getElementById('user_checkbox');
    const adminCheckbox = document.getElementById('admin_checkbox');
    const roleInput = document.getElementById('selected_role');

    function updateRoleSelection(clickedCheckbox) {
      if (clickedCheckbox === userCheckbox) {
        user_checkbox.checked = true;
        admin_checkbox.checked = false;
        roleInput.value = "user";
      } else {
        user_checkbox.checked = false;
        admin_checkbox.checked = true;
        roleInput.value = "admin";
      }
    }

    user_checkbox.addEventListener('change', () => updateRoleSelection(user_checkbox));
    admin_checkbox.addEventListener('change', () => updateRoleSelection(admin_checkbox));

    const registerLink = document.getElementById('register-link');

    function updateRoleSelection(clickedCheckbox) {
      if (clickedCheckbox === userCheckbox) {
        user_checkbox.checked = true;
        admin_checkbox.checked = false;
        roleInput.value = "user";
        registerLink.style.display = "block"; // Show register for users
      } else {
        user_checkbox.checked = false;
        admin_checkbox.checked = true;
        roleInput.value = "admin";
        registerLink.style.display = "none"; // Hide for admins
      }
    }

  </script>
</body>
</html>