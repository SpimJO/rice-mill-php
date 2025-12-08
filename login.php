<?php
session_start();
include "db.php"; // database connection
include "blockchain_api.php"; // Include Hyperledger Fabric API helper

date_default_timezone_set('Asia/Manila');


// ---------------- BLOCKCHAIN FUNCTIONS (Hyperledger Fabric) ---------------- //

function logBlockchainEvent($conn, $user_id, $action, $target_user, $data) {
    // Use Hyperledger Fabric API with database fallback
    $dataArray = is_string($data) ? json_decode($data, true) : $data;
    if ($dataArray === null && is_string($data)) {
        $dataArray = $data; // Use as-is if not JSON
    }
    addBlockchainLogWithFallback($conn, $user_id, $action, $target_user, $dataArray);
}

// ---------------- LOGIN PROCESS ---------------- //

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username == "" || $password == "") {
        $error = "Please enter both username and password.";
    } else {

        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                // Store session data
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Log blockchain event
                $data = json_encode(["role" => $user['role'], "name" => $user['name']]);
                logBlockchainEvent($conn, $user['user_id'], "LOGIN_SUCCESS", $user['user_id'], $data);

                // Role-based redirection
                $role = strtolower($user['role']);

                switch ($role) {
                    case 'admin':
                        $redirect = 'dashboard.php';
                        break;
                    case 'operator':
                        $redirect = 'milling.php';
                        break;
                    case 'cashier':
                        $redirect = 'pos.php';
                        break;
                    default:
                        $redirect = 'dashboard.php';
                }

                header("Location: $redirect");
                exit();

            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Username not found.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login Page</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: url('bg.png') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .login-container {
      background: rgba(255, 255, 255, 0.9);
      width: 380px;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      text-align: center;
      backdrop-filter: blur(4px);
    }

    h2 {
      margin-bottom: 25px;
      color: #2b411a;
      font-weight: 700;
      font-size: 26px;
    }

    .input-field {
      margin-bottom: 20px;
    }

    .input-field input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }

    .login-btn {
      width: 100%;
      padding: 12px;
      background-color: #6d8b33;
      border: none;
      border-radius: 6px;
      color: white;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
    }

    .login-btn:hover {
      background-color: #577226;
    }

    .error {
      color: red;
      margin-bottom: 10px;
      font-size: 14px;
    }
  </style>
</head>

<body>
  <div class="login-container">
    <h2>LOGIN PAGE</h2>

    <?php if (!empty($error)): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" onsubmit="return validateForm()">
      <div class="input-field">
        <input type="text" name="username" id="username" placeholder="Username" />
      </div>
      <div class="input-field">
        <input type="password" name="password" id="password" placeholder="Password" />
      </div>

      <button type="submit" class="login-btn">LOG IN</button>
    </form>
  </div>

  <script>
    function validateForm() {
      const user = document.getElementById('username').value.trim();
      const pass = document.getElementById('password').value.trim();
      if (user === '' || pass === '') {
        alert('Please fill in both fields.');
        return false;
      }
      return true;
    }
  </script>

</body>
</html>
