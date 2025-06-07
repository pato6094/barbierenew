<?php
include 'connessione.php';

$nome = $_POST['nome'];
$email = $_POST['email'];
$telefono = $_POST['telefono'];
$servizio = $_POST['servizio'];
$data = $_POST['data_prenotazione'];
$orario = $_POST['orario'];

$day = date('N', strtotime($data));
if ($day == 1) {
    die("Prenotazioni non disponibili il lunedì.");
}

$stmt = $conn->prepare("INSERT INTO prenotazioni (nome, email, telefono, servizio, data_prenotazione, orario) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $nome, $email, $telefono, $servizio, $data, $orario);
$stmt->execute();
$stmt->close();
$conn->close();

echo "Prenotazione effettuata con successo!";
?>