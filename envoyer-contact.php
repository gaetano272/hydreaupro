<?php

declare(strict_types=1);

session_name('HYDREAUPRO_CONTACT');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
    ),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const CONTACT_RECIPIENT = 'contact@hydreaupro.sn';
const CONTACT_SENDER = 'contact@hydreaupro.sn';
const CONTACT_SITE_NAME = 'HYDREAUPRO';
const CONTACT_RATE_LIMIT_SECONDS = 60;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cleanSingleLine(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = str_replace(["\r", "\n", "\0"], ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';

    return mb_substr($value, 0, $maxLength);
}

function cleanMultiline(string $value, int $maxLength): string
{
    $value = trim($value);
    $value = str_replace("\0", '', $value);
    $value = preg_replace("/\r\n?/", "\n", $value) ?? '';

    return mb_substr($value, 0, $maxLength);
}

function renderResponse(
    string $title,
    string $message,
    bool $success,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    $titleEscaped = h($title);
    $messageEscaped = h($message);
    $className = $success ? 'response-card--success' : 'response-card--error';
    $buttonLabel = $success ? 'Retour à l’accueil' : 'Retour au formulaire';
    $buttonHref = $success ? 'index.html' : 'contact.html';

    echo <<<HTML
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>{$titleEscaped} — HYDREAUPRO</title>
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 28px 0;
      background:
        radial-gradient(circle at top right, rgba(255, 106, 0, 0.16), transparent 34%),
        #f6f3ef;
    }

    .response-wrapper {
      width: min(100% - 32px, 680px);
    }

    .response-card {
      padding: 38px;
      background: #fff;
      border-radius: 28px;
      box-shadow: 0 22px 54px rgba(10, 10, 10, 0.13);
      text-align: center;
    }

    .response-card h1 {
      margin: 0 0 14px;
      color: #121212;
      font-family: var(--font-title, Georgia, serif);
      font-size: clamp(2rem, 6vw, 3rem);
      line-height: 1.08;
    }

    .response-card p {
      max-width: 560px;
      margin: 0 auto 26px;
      color: #5e564f;
      line-height: 1.7;
    }

    .response-card--success {
      border-top: 7px solid #16a34a;
    }

    .response-card--error {
      border-top: 7px solid #ff6a00;
    }

    .response-logo {
      width: 72px;
      height: 72px;
      margin: 0 auto 20px;
      object-fit: contain;
    }

    @media (max-width: 560px) {
      .response-card {
        padding: 28px 20px;
        border-radius: 22px;
      }
    }
  </style>
</head>
<body>
  <main class="response-wrapper">
    <section class="response-card {$className}">
      <img class="response-logo" src="images/logo-hydreaupro.webp" alt="Logo HYDREAUPRO">
      <h1>{$titleEscaped}</h1>
      <p>{$messageEscaped}</p>
      <a class="btn btn--primary" href="{$buttonHref}">{$buttonLabel}</a>
    </section>
  </main>
</body>
</html>
HTML;

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

$lastSubmission = (int) ($_SESSION['last_contact_submission'] ?? 0);

if ($lastSubmission > 0 && (time() - $lastSubmission) < CONTACT_RATE_LIMIT_SECONDS) {
    renderResponse(
        'Veuillez patienter',
        'Un message vient déjà d’être envoyé. Patientez une minute avant de recommencer.',
        false,
        429
    );
}

$honeypot = trim((string) ($_POST['website'] ?? ''));

if ($honeypot !== '') {
    renderResponse(
        'Message reçu',
        'Votre demande a bien été prise en compte.',
        true
    );
}

$prenom = cleanSingleLine((string) ($_POST['prenom'] ?? ''), 80);
$nom = cleanSingleLine((string) ($_POST['nom'] ?? ''), 100);
$email = trim((string) ($_POST['email'] ?? ''));
$telephone = cleanSingleLine((string) ($_POST['tel'] ?? ''), 40);
$service = cleanSingleLine((string) ($_POST['service'] ?? ''), 40);
$localisation = cleanSingleLine((string) ($_POST['localisation'] ?? ''), 160);
$sujet = cleanSingleLine((string) ($_POST['sujet'] ?? ''), 180);
$message = cleanMultiline((string) ($_POST['message'] ?? ''), 5000);
$rgpdAccepted = isset($_POST['rgpd']);

$allowedServices = [
    'assainissement' => 'Assainissement autonome',
    'surpression' => 'Surpression d’eau potable',
    'irrigation' => 'Irrigation',
    'autre' => 'Autre besoin',
];

$errors = [];

if ($prenom === '') {
    $errors[] = 'Le prénom est obligatoire.';
}

if ($nom === '') {
    $errors[] = 'Le nom est obligatoire.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'L’adresse email est invalide.';
}

if (!array_key_exists($service, $allowedServices)) {
    $errors[] = 'Le service sélectionné est invalide.';
}

if ($sujet === '') {
    $errors[] = 'Le sujet est obligatoire.';
}

if ($message === '' || mb_strlen($message) < 20) {
    $errors[] = 'Le message doit contenir au moins 20 caractères.';
}

if (!$rgpdAccepted) {
    $errors[] = 'Vous devez accepter l’utilisation de vos informations pour être recontacté.';
}

if ($telephone !== '' && !preg_match('/^[0-9+\s().-]{6,40}$/', $telephone)) {
    $errors[] = 'Le numéro de téléphone contient des caractères invalides.';
}

if (!empty($errors)) {
    renderResponse(
        'Formulaire incomplet',
        implode(' ', $errors),
        false,
        422
    );
}

$serviceLabel = $allowedServices[$service];

$mailSubjectText = '[HYDREAUPRO] ' . $sujet;
$mailSubject = '=?UTF-8?B?' . base64_encode($mailSubjectText) . '?=';

$mailBody = implode("\n", [
    'Nouvelle demande reçue depuis le site HYDREAUPRO',
    '',
    'Prénom : ' . $prenom,
    'Nom : ' . $nom,
    'Email : ' . $email,
    'Téléphone : ' . ($telephone !== '' ? $telephone : 'Non renseigné'),
    'Service : ' . $serviceLabel,
    'Localisation : ' . ($localisation !== '' ? $localisation : 'Non renseignée'),
    'Sujet : ' . $sujet,
    '',
    'Message :',
    $message,
    '',
    'Consentement : la personne a accepté d’être recontactée pour le traitement de sa demande.',
    'Date d’envoi : ' . date('d/m/Y H:i:s'),
]);

$headers = [
    'From: ' . CONTACT_SITE_NAME . ' <' . CONTACT_SENDER . '>',
    'Reply-To: ' . $prenom . ' ' . $nom . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$mailSent = mail(
    CONTACT_RECIPIENT,
    $mailSubject,
    $mailBody,
    implode("\r\n", $headers)
);

if (!$mailSent) {
    error_log('HYDREAUPRO : échec de mail() pour le formulaire de contact.');

    renderResponse(
        'Envoi impossible',
        'Votre message n’a pas pu être envoyé pour le moment. Vous pouvez nous contacter directement à contact@hydreaupro.sn ou par téléphone.',
        false,
        500
    );
}

$_SESSION['last_contact_submission'] = time();

renderResponse(
    'Message envoyé',
    'Merci pour votre demande. L’équipe HYDREAUPRO vous répondra dans les plus brefs délais.',
    true
);
