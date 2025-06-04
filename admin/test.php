<?php
$hash = '$2y$10$0fb8R5LF0jY2bdEimhoNE.YKVJwdWn2LHM2oJJp35i6S.z2bxasf2'; // Remplacez par le hash actuel
$password = 'test';

if (password_verify($password, $hash)) {
    echo "Le mot de passe est correct.\n";
    // Remplacez 'admin' par un entier valide pour la colonne 'user_id'
    $user_id = 1; // Exemple : ID utilisateur correspondant à 'admin'
    $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token) VALUES (:user_id, :session_token)");
    $stmt->execute(['user_id' => $user_id, 'session_token' => $session_token]);
} else {
    echo "Le mot de passe est incorrect.\n";
}
?>