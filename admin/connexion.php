<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (isAdminAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = null;
$logoutMessage = isset($_GET['logout']) && $_GET['logout'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $submittedUsername = trim((string) ($_POST['username'] ?? ''));
    $submittedPassword = (string) ($_POST['password'] ?? '');

    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $remainingMinutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
        $error = "Trop de tentatives. Réessayez dans {$remainingMinutes} minute(s).";
    } elseif (!verifyAdminCsrfToken(is_string($submittedToken) ? $submittedToken : null)) {
        $error = 'La session de sécurité a expiré. Rechargez la page.';
    } else {
        $usernameIsValid = hash_equals(
            (string) $adminConfig['username'],
            $submittedUsername
        );

        $passwordIsValid = password_verify(
            $submittedPassword,
            (string) $adminConfig['password_hash']
        );

        if ($usernameIsValid && $passwordIsValid) {
            session_regenerate_id(true);

            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_username'] = (string) $adminConfig['username'];
            $_SESSION['admin_login_time'] = time();
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_locked_until']);

            $redirectPage = (string) (
                $_SESSION['admin_redirect_after_login'] ?? 'index.php'
            );

            unset($_SESSION['admin_redirect_after_login']);

            if (!preg_match('/^[a-zA-Z0-9._-]+(?:\?.*)?$/', $redirectPage)) {
                $redirectPage = 'index.php';
            }

            header('Location: ' . $redirectPage);
            exit;
        }

        $attempts++;
        $_SESSION['login_attempts'] = $attempts;

        if ($attempts >= 5) {
            $_SESSION['login_locked_until'] = time() + 900;
            $_SESSION['login_attempts'] = 0;
            $error = 'Trop de tentatives. L’accès est bloqué pendant 15 minutes.';
        } else {
            $remainingAttempts = 5 - $attempts;
            $error = "Identifiant ou mot de passe incorrect. {$remainingAttempts} tentative(s) restante(s).";
        }
    }
}

$csrfToken = getAdminCsrfToken();
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <meta name="robots" content="noindex, nofollow">

  <title>Connexion administration — HYDREAUPRO</title>

  <link rel="stylesheet" href="../style.css">

  <style>
    body {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 28px 0;
      background:
        radial-gradient(circle at top right, rgba(255, 106, 0, 0.18), transparent 34%),
        #f6f3ef;
    }

    .login-wrapper {
      width: min(100% - 32px, 500px);
    }

    .login-card {
      padding: 34px;
      background: #fff;
      border: 1px solid rgba(18, 18, 18, 0.07);
      border-radius: 28px;
      box-shadow: 0 22px 54px rgba(10, 10, 10, 0.13);
    }

    .login-brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 26px;
    }

    .login-brand img {
      width: 58px;
      height: 58px;
      object-fit: contain;
    }

    .login-brand strong {
      color: #0b3b82;
      font-size: 1.5rem;
      letter-spacing: 0.04em;
    }

    .login-brand strong span {
      color: #ff6a00;
    }

    .login-card h1 {
      margin: 0 0 10px;
      color: #121212;
      font-family: var(--font-title, Georgia, serif);
      font-size: clamp(2rem, 6vw, 2.7rem);
      line-height: 1.08;
    }

    .login-intro {
      margin: 0 0 24px;
      color: #5e564f;
    }

    .login-alert {
      margin-bottom: 20px;
      padding: 14px 16px;
      border-radius: 16px;
      font-weight: 700;
    }

    .login-alert--error {
      color: #9d2d00;
      background: #fff2ec;
      border-left: 5px solid #ff6a00;
    }

    .login-alert--success {
      color: #166534;
      background: #ecfdf3;
      border-left: 5px solid #16a34a;
    }

    .login-field {
      display: grid;
      gap: 8px;
      margin-bottom: 18px;
    }

    .login-field label {
      color: #121212;
      font-weight: 800;
    }

    .login-field input {
      width: 100%;
      min-height: 54px;
      padding: 0 16px;
      border: 1px solid rgba(18, 18, 18, 0.15);
      border-radius: 15px;
      outline: none;
      font: inherit;
    }

    .login-field input:focus {
      border-color: rgba(255, 106, 0, 0.72);
      box-shadow: 0 0 0 4px rgba(255, 106, 0, 0.12);
    }

    .login-submit {
      width: 100%;
      margin-top: 6px;
    }

    .login-return {
      display: block;
      margin-top: 20px;
      color: #ff6a00;
      font-weight: 800;
      text-align: center;
    }

    @media (max-width: 520px) {
      .login-card {
        padding: 26px 20px;
        border-radius: 22px;
      }
    }
  </style>
</head>

<body>

  <main class="login-wrapper">
    <section class="login-card">

      <div class="login-brand">
        <img src="../images/logo-hydreaupro.webp" alt="Logo HYDREAUPRO">

        <strong>
          HYDREAU<span>PRO</span>
        </strong>
      </div>

      <h1>Connexion administrateur</h1>

      <p class="login-intro">
        Connectez-vous pour gérer les publications et les documents PDF.
      </p>

      <?php if ($logoutMessage): ?>
        <div class="login-alert login-alert--success" role="status">
          Vous êtes maintenant déconnecté.
        </div>
      <?php endif; ?>

      <?php if ($error !== null): ?>
        <div class="login-alert login-alert--error" role="alert">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="on">

        <input
          type="hidden"
          name="csrf_token"
          value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >

        <div class="login-field">
          <label for="username">Identifiant</label>

          <input
            type="text"
            id="username"
            name="username"
            autocomplete="username"
            required
            autofocus
          >
        </div>

        <div class="login-field">
          <label for="password">Mot de passe</label>

          <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            required
          >
        </div>

        <button class="btn btn--primary login-submit" type="submit">
          Se connecter
        </button>

      </form>

      <a class="login-return" href="../index.html">
        Retour au site
      </a>

    </section>
  </main>

</body>
</html>
