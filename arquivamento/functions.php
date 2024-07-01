<?php
function get_user_full_name($conn, $username) {
    $stmt = $conn->prepare("SELECT nome_completo FROM funcionarios WHERE usuario = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nome_completo'];
    } else {
        return null;
    }
}
?>
