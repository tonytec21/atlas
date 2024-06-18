<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['fullName'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Read the existing data from the JSON file
    $dataFilePath = 'data.json';
    if (file_exists($dataFilePath)) {
        $data = json_decode(file_get_contents($dataFilePath), true);
    } else {
        $data = [];
    }

    // Ensure data is an array
    if (!is_array($data)) {
        $data = [];
    }

    // Add the new user to the data array
    $newUser = [
        'fullName' => $fullName,
        'username' => $username,
        'password' => $password
    ];

    $data[] = $newUser;

    // Write the updated data back to the JSON file
    if (file_put_contents($dataFilePath, json_encode($data, JSON_PRETTY_PRINT))) {
        echo 'success';
    } else {
        echo 'failure';
    }
}
?>
