<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Méthode non autorisée.']);
}

// Honeypot anti-spam : ce champ doit rester vide.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

$nom        = trim((string)($_POST['nom'] ?? ''));
$entreprise = trim((string)($_POST['entreprise'] ?? ''));
$email      = trim((string)($_POST['email'] ?? ''));
$telephone  = trim((string)($_POST['telephone'] ?? ''));
$type       = trim((string)($_POST['type'] ?? ''));
$budget     = trim((string)($_POST['budget'] ?? ''));
$message    = trim((string)($_POST['message'] ?? ''));

if ($nom === '' || $entreprise === '' || $email === '' || $type === '' || $message === '') {
    respond(422, ['ok' => false, 'message' => 'Veuillez compléter tous les champs obligatoires.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'message' => 'Adresse e-mail invalide.']);
}

if (strlen($nom) > 120 || strlen($entreprise) > 200 || strlen($email) > 254 || strlen($telephone) > 40 || strlen($message) > 5000) {
    respond(422, ['ok' => false, 'message' => 'Un ou plusieurs champs sont trop longs.']);
}

$typesAutorises = ['Nouveau site', 'Refonte', 'Prise de rendez-vous', 'Autre'];
$budgetsAutorises = ["< 1'000 CHF", "1'000–1'500 CHF", "1'500–2'500 CHF", "2'500 CHF+", 'Je ne sais pas encore', ''];
if (!in_array($type, $typesAutorises, true) || !in_array($budget, $budgetsAutorises, true)) {
    respond(422, ['ok' => false, 'message' => 'Valeur de formulaire invalide.']);
}

// Neutralise les retours à la ligne dans les valeurs utilisées dans les en-têtes.
$nomEntete = preg_replace('/[\r\n]+/', ' ', $nom) ?? $nom;
$emailEntete = preg_replace('/[\r\n]+/', '', $email) ?? $email;

$destinataire = 'info@grilo-digital.ch';
$sujet = 'Nouvelle demande de projet — GRILO Digital';

$corps = "Nouvelle demande reçue depuis grilo-digital.ch\n\n";
$corps .= "Nom : {$nom}\n";
$corps .= "Entreprise : {$entreprise}\n";
$corps .= "E-mail : {$email}\n";
$corps .= "Téléphone : " . ($telephone !== '' ? $telephone : 'Non renseigné') . "\n";
$corps .= "Type de projet : {$type}\n";
$corps .= "Budget : " . ($budget !== '' ? $budget : 'Non renseigné') . "\n\n";
$corps .= "Description du projet :\n{$message}\n";

$headers = [
    'From: GRILO Digital <info@grilo-digital.ch>',
    'Reply-To: ' . $nomEntete . ' <' . $emailEntete . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];

$envoye = @mail($destinataire, $sujet, $corps, implode("\r\n", $headers));

if (!$envoye) {
    respond(500, ['ok' => false, 'message' => 'Le message n’a pas pu être envoyé.']);
}

respond(200, ['ok' => true]);
