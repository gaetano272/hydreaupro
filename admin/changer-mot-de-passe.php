<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();

$configFile = __DIR__ . '/config-auth.php';

$errors = [];
$passwordChanged = false;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function passwordIsStrong(string $password): bool
{
    if (mb_strlen($password) < 12 || mb_strlen($password) > 128) {
        return false;
    }

    $criteria = 0;

    if (preg_match('/[a-z]/', $password)) {
        $criteria++;
    }

    if (preg_match('/[A-Z]/', $password)) {
        $criteria++;
    }

    if (preg_match('/[0-9]/', $password)) {
        $criteria++;
    }

    if (preg_match('/[^a-zA-Z0-9]/', $password)) {
        $criteria++;
    }

    return $criteria >= 3;
}

function destroyAdminSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $cookieParameters['path'],
                'domain' => $cookieParameters['domain'],
                'secure' => $cookieParameters['secure'],
                'httponly' => $cookieParameters['httponly'],
                'samesite' => 'Strict',
            ]
        );
    }

    session_destroy();
}

$csrfToken = getAdminCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    $attempts = (int) ($_SESSION['password_change_attempts'] ?? 0);
    $lockedUntil = (int) ($_SESSION['password_change_locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $remainingMinutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
        $errors[] = "Trop de tentatives. Réessayez dans {$remainingMinutes} minute(s).";
    } elseif (
        !verifyAdminCsrfToken(
            is_string($submittedToken) ? $submittedToken : null
        )
    ) {
        $errors[] = 'La session de sécurité a expiré. Rechargez la page.';
    } elseif (
        !password_verify(
            $currentPassword,
            (string) $adminConfig['password_hash']
        )
    ) {
        $attempts++;
        $_SESSION['password_change_attempts'] = $attempts;

        if ($attempts >= 5) {
            $_SESSION['password_change_locked_until'] = time() + 900;
            $_SESSION['password_change_attempts'] = 0;

            $errors[] = 'Trop de tentatives incorrectes. La modification est bloquée pendant 15 minutes.';
        } else {
            $remainingAttempts = 5 - $attempts;
            $errors[] = "Le mot de passe actuel est incorrect. {$remainingAttempts} tentative(s) restante(s).";
        }
    } else {
        $_SESSION['password_change_attempts'] = 0;
        unset($_SESSION['password_change_locked_until']);

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Les deux nouveaux mots de passe ne correspondent pas.';
        }

        if (!passwordIsStrong($newPassword)) {
            $errors[] = 'Le nouveau mot de passe doit contenir entre 12 et 128 caractères et au moins trois catégories parmi : minuscules, majuscules, chiffres et caractères spéciaux.';
        }

        if (
            $newPassword !== ''
            && password_verify(
                $newPassword,
                (string) $adminConfig['password_hash']
            )
        ) {
            $errors[] = 'Le nouveau mot de passe doit être différent du mot de passe actuel.';
        }

        if (empty($errors)) {
            if (!is_file($configFile)) {
                $errors[] = 'Le fichier config-auth.php est introuvable.';
            } elseif (!is_writable($configFile)) {
                $errors[] = 'Le fichier config-auth.php n’est pas accessible en écriture.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                if (!is_string($newHash) || $newHash === '') {
                    $errors[] = 'Le nouveau mot de passe n’a pas pu être sécurisé.';
                } else {
                    $username = (string) $adminConfig['username'];

                    $newConfigContent = "<?php\n\n"
                        . "declare(strict_types=1);\n\n"
                        . "/*\n"
                        . " * Identifiants administrateur.\n"
                        . " * Le mot de passe n'est jamais stocké en clair : seul son hash est enregistré.\n"
                        . " */\n"
                        . "return [\n"
                        . "    'username' => " . var_export($username, true) . ",\n"
                        . "    'password_hash' => " . var_export($newHash, true) . ",\n"
                        . "];\n";

                    $backupFile = $configFile . '.bak';
                    $backupCreated = copy($configFile, $backupFile);

                    if (!$backupCreated) {
                        $errors[] = 'La sauvegarde de sécurité de config-auth.php a échoué.';
                    } else {
                        $bytesWritten = file_put_contents(
                            $configFile,
                            $newConfigContent,
                            LOCK_EX
                        );

                        if ($bytesWritten === false) {
                            @copy($backupFile, $configFile);
                            $errors[] = 'Le nouveau mot de passe n’a pas pu être enregistré.';
                        } else {
                            @unlink($backupFile);
                            $passwordChanged = true;
                            destroyAdminSession();
                        }
                    }
                }
            }
        }
    }
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <meta name="robots" content="noindex, nofollow">

  <title>Changer le mot de passe — HYDREAUPRO</title>

  <link rel="stylesheet" href="../style.css">

  <style>
    body {
      min-height: 100vh;
      padding: 40px 0;
      background:
        radial-gradient(circle at top right, rgba(255, 106, 0, 0.16), transparent 34%),
        #f6f3ef;
    }

    .password-page {
      width: min(100% - 32px, 720px);
      margin: 0 auto;
    }

    .password-card {
      padding: 36px;
      background: #fff;
      border: 1px solid rgba(18, 18, 18, 0.07);
      border-radius: 28px;
      box-shadow: 0 22px 54px rgba(10, 10, 10, 0.13);
    }

    .password-brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 24px;
    }

    .password-brand img {
      width: 58px;
      height: 58px;
      object-fit: contain;
    }

    .password-brand strong {
      color: #0b3b82;
      font-size: 1.45rem;
      letter-spacing: 0.04em;
    }

    .password-brand strong span {
      color: #ff6a00;
    }

    .password-card h1 {
      margin: 0 0 10px;
      color: #121212;
      font-family: var(--font-title, Georgia, serif);
      font-size: clamp(2rem, 6vw, 3rem);
      line-height: 1.08;
    }

    .password-intro {
      margin: 0 0 26px;
      color: #5e564f;
      line-height: 1.7;
    }

    .password-alert {
      margin-bottom: 22px;
      padding: 16px 18px;
      border-radius: 16px;
      font-weight: 700;
    }

    .password-alert--error {
      color: #9d2d00;
      background: #fff2ec;
      border-left: 5px solid #ff6a00;
    }

    .password-alert--success {
      color: #166534;
      background: #ecfdf3;
      border-left: 5px solid #16a34a;
    }

    .password-alert ul {
      margin: 0;
      padding-left: 20px;
    }

    .password-field {
      display: grid;
      gap: 8px;
      margin-bottom: 18px;
    }

    .password-field label {
      color: #121212;
      font-weight: 800;
    }

    .password-field input {
      width: 100%;
      min-height: 54px;
      padding: 0 16px;
      border: 1px solid rgba(18, 18, 18, 0.15);
      border-radius: 15px;
      outline: none;
      font: inherit;
    }

    .password-field input:focus {
      border-color: rgba(255, 106, 0, 0.72);
      box-shadow: 0 0 0 4px rgba(255, 106, 0, 0.12);
    }

    .password-help {
      margin: -4px 0 20px;
      color: #6b625b;
      font-size: 0.92rem;
      line-height: 1.6;
    }

    .password-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 24px;
    }

    @media (max-width: 560px) {
      .password-card {
        padding: 28px 20px;
        border-radius: 22px;
      }

      .password-actions {
        flex-direction: column;
      }

      .password-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>

  <main class="password-page">
    <section class="password-card">

      <div class="password-brand">
        <img src="../images/logo-hydreaupro.webp" alt="Logo HYDREAUPRO">

        <strong>
          HYDREAU<span>PRO</span>
        </strong>
      </div>

      <?php if ($passwordChanged): ?>

        <div class="password-alert password-alert--success" role="status">
          Le mot de passe a bien été modifié. Pour des raisons de sécurité,
          vous avez été déconnecté.
        </div>

        <h1>Mot de passe modifié</h1>

        <p class="password-intro">
          Vous pouvez maintenant vous reconnecter avec votre nouveau mot de passe.
        </p>

        <a class="btn btn--primary" href="connexion.php">
          Se reconnecter
        </a>

      <?php else: ?>

        <h1>Changer le mot de passe</h1>

        <p class="password-intro">
          Saisissez le mot de passe actuel, puis choisissez un nouveau mot de passe
          robuste et unique.
        </p>

        <?php if (!empty($errors)): ?>
          <div class="password-alert password-alert--error" role="alert">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">

          <input
            type="hidden"
            name="csrf_token"
            value="<?= h($csrfToken) ?>"
          >

          <div class="password-field">
            <label for="current_password">Mot de passe actuel</label>

            <input
              type="password"
              id="current_password"
              name="current_password"
              autocomplete="current-password"
              required
              autofocus
            >
          </div>

          <div class="password-field">
            <label for="new_password">Nouveau mot de passe</label>

            <input
              type="password"
              id="new_password"
              name="new_password"
              autocomplete="new-password"
              minlength="12"
              maxlength="128"
              required
            >
          </div>

          <div class="password-field">
            <label for="confirm_password">Confirmer le nouveau mot de passe</label>

            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              autocomplete="new-password"
              minlength="12"
              maxlength="128"
              required
            >
          </div>

          <p class="password-help">
            Utilisez au moins 12 caractères et mélangez idéalement majuscules,
            minuscules, chiffres et caractères spéciaux. N’utilisez pas un mot
            de passe déjà employé sur un autre service.
          </p>

          <div class="password-actions">
            <button class="btn btn--primary" type="submit">
              Enregistrer le nouveau mot de passe
            </button>

            <a class="btn btn--secondary" href="index.php">
              Annuler
            </a>
          </div>

        </form>

      <?php endif; ?>

    </section>
  </main>

</body>
</html>
