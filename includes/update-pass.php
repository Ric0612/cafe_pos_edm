<?php
// Include the database connection
include('db-conn.php');

// Select all users from the database
$sql = "SELECT * FROM users";
$result = $conn->query($sql);

// Loop through all users and update their passwords
while ($row = $result->fetch_assoc()) {
    // Hash the user's plain-text password
    $hashed_password = password_hash($row['password'], PASSWORD_DEFAULT);

    // Update the user's password in the database with the hashed password
    $update_sql = "UPDATE users SET password = ? WHERE user_ID = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $hashed_password, $row['user_ID']);
    $stmt->execute();
}

echo "Passwords updated successfully.";
?>
