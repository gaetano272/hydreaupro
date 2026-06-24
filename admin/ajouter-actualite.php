<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';

$errors = [];

$titre = trim((string) ($_POST['titre'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$datePublication = (string) ($_POST['date_publication'] ?? date('Y-m-d'));
$statut = (string) ($_POST['statut'] ?? 'publie');

$csrfToken = getAdminCsrfToken();

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function createSlug(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return 'publication';
    }

    if (function_exists('transliterator_transliterate')) {
        $value = (string) transliterator_transliterate(
            'Any-Latin; Latin-ASCII; Lower()',
            $value
        );
    } else {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($converted !== false) {
            $value = $converted;
        }

        $value = strtolower($value);
    }

    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'publication';
}

function uploadErrorMessage(int $error, string $label): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => "Le fichier {$label} dépasse la taille autorisée par le serveur.",
        UPLOAD_ERR_PARTIAL => "Le fichier {$label} n’a été envoyé que partiellement.",
        UPLOAD_ERR_NO_FILE => "Le fichier {$label} est obligatoire.",
        UPLOAD_ERR_NO_TMP_DIR => "Le dossier temporaire du serveur est introuvable.",
        UPLOAD_ERR_CANT_WRITE => "Le serveur ne peut pas enregistrer le fichier {$label}.",
        UPLOAD_ERR_EXTENSION => "L’envoi du fichier {$label} a été interrompu par le serveur.",
        default => "Une erreur est survenue pendant l’envoi du fichier {$label}.",
    };
}

function ensureWritableDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("Impossible de créer le dossier : {$directory}");
    }

    if (!is_writable($directory)) {
        throw new RuntimeException("Le dossier n’est pas accessible en écriture : {$directory}");
    }
}

function removeUploadedFile(?string $path): void
{
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (
        !verifyAdminCsrfToken(
            is_string($submittedToken) ? $submittedToken : null
        )
    ) {
        $errors[] = 'La session de sécurité a expiré. Rechargez la page et recommencez.';
    }

    if ($titre === '') {
        $errors[] = 'Le titre est obligatoire.';
    } elseif (mb_strlen($titre) > 255) {
        $errors[] = 'Le titre ne doit pas dépasser 255 caractères.';
    }

    if (!in_array($statut, ['brouillon', 'publie'], true)) {
        $statut = 'publie';
    }

    $dateObject = DateTime::createFromFormat('Y-m-d', $datePublication);
    $dateIsValid = $dateObject !== false
        && $dateObject->format('Y-m-d') === $datePublication;

    if (!$dateIsValid) {
        $errors[] = 'La date de publication est invalide.';
    }

    $pdfFile = $_FILES['pdf'] ?? null;
    $imageFile = $_FILES['image_publication'] ?? null;

    if (!is_array($pdfFile)) {
        $errors[] = 'Le fichier PDF est obligatoire.';
    } elseif (($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = uploadErrorMessage(
            (int) ($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE),
            'PDF'
        );
    }

    $imageProvided = is_array($imageFile)
        && ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($imageProvided && ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = uploadErrorMessage(
            (int) ($imageFile['error'] ?? UPLOAD_ERR_NO_FILE),
            'image'
        );
    }

    $imageMime = '';
    $allowedImageTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (empty($errors) && is_array($pdfFile)) {
        if (!is_uploaded_file((string) $pdfFile['tmp_name'])) {
            $errors[] = 'Le fichier PDF envoyé est invalide.';
        } else {
            $pdfSize = (int) ($pdfFile['size'] ?? 0);

            if ($pdfSize <= 0) {
                $errors[] = 'Le fichier PDF est vide.';
            } elseif ($pdfSize > 10 * 1024 * 1024) {
                $errors[] = 'Le PDF ne doit pas dépasser 10 Mo.';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $pdfMime = (string) $finfo->file((string) $pdfFile['tmp_name']);

            if ($pdfMime !== 'application/pdf') {
                $errors[] = 'Le fichier sélectionné n’est pas un véritable document PDF.';
            }
        }
    }

    if (empty($errors) && $imageProvided && is_array($imageFile)) {
        if (!is_uploaded_file((string) $imageFile['tmp_name'])) {
            $errors[] = 'L’image envoyée est invalide.';
        } else {
            $imageSize = (int) ($imageFile['size'] ?? 0);

            if ($imageSize <= 0) {
                $errors[] = 'L’image envoyée est vide.';
            } elseif ($imageSize > 5 * 1024 * 1024) {
                $errors[] = 'L’image ne doit pas dépasser 5 Mo.';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $imageMime = (string) $finfo->file((string) $imageFile['tmp_name']);

            if (!array_key_exists($imageMime, $allowedImageTypes)) {
                $errors[] = 'L’image doit être au format JPG, PNG ou WebP.';
            }
        }
    }

    if (empty($errors)) {
        $pdfDirectory = __DIR__ . '/../uploads/actualites/';
        $imageDirectory = __DIR__ . '/../uploads/actualites/images/';

        $savedPdfPath = null;
        $savedImagePath = null;

        try {
            ensureWritableDirectory($pdfDirectory);
            ensureWritableDirectory($imageDirectory);

            $slug = createSlug($titre);
            $uniqueId = bin2hex(random_bytes(5));
            $timestamp = date('Ymd-His');

            $pdfFileName = "{$timestamp}-{$uniqueId}-{$slug}.pdf";
            $savedPdfPath = $pdfDirectory . $pdfFileName;

            if (!move_uploaded_file((string) $pdfFile['tmp_name'], $savedPdfPath)) {
                throw new RuntimeException('Impossible d’enregistrer le document PDF.');
            }

            $publicPdfPath = 'uploads/actualites/' . $pdfFileName;
            $publicImagePath = null;

            if ($imageProvided && is_array($imageFile)) {
                $imageExtension = $allowedImageTypes[$imageMime];
                $imageFileName = "{$timestamp}-{$uniqueId}-{$slug}.{$imageExtension}";
                $savedImagePath = $imageDirectory . $imageFileName;

                if (!move_uploaded_file((string) $imageFile['tmp_name'], $savedImagePath)) {
                    throw new RuntimeException('Impossible d’enregistrer l’image de publication.');
                }

                $publicImagePath = 'uploads/actualites/images/' . $imageFileName;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO actualites (
                    titre,
                    description,
                    fichier_pdf,
                    image_publication,
                    date_publication,
                    statut
                ) VALUES (
                    :titre,
                    :description,
                    :fichier_pdf,
                    :image_publication,
                    :date_publication,
                    :statut
                )'
            );

            $stmt->execute([
                ':titre' => $titre,
                ':description' => $description !== '' ? $description : null,
                ':fichier_pdf' => $publicPdfPath,
                ':image_publication' => $publicImagePath,
                ':date_publication' => $datePublication,
                ':statut' => $statut,
            ]);

            $pdo->commit();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php?success=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            removeUploadedFile($savedPdfPath);
            removeUploadedFile($savedImagePath);

            $errors[] = 'L’actualité n’a pas pu être enregistrée. Vérifiez la base de données et les droits des dossiers uploads.';
        }
    }
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Ajouter une actualité — HYDREAUPRO</title>

  <link rel="stylesheet" href="../style.css">

  <style>
    body {
      background: #f6f3ef;
    }

    .admin-page {
      padding: 50px 0 80px;
    }

    .admin-header {
      margin-bottom: 30px;
    }

    .admin-header h1 {
      margin: 0 0 8px;
      font-family: var(--font-title, Georgia, serif);
      font-size: clamp(2rem, 4vw, 3rem);
      color: #121212;
    }

    .admin-header p {
      max-width: 760px;
      margin: 0;
      color: #5e564f;
      line-height: 1.65;
    }

    .admin-form-card {
      max-width: 860px;
      padding: 30px;
      background: #fff;
      border-radius: 28px;
      box-shadow: 0 12px 30px rgba(10, 10, 10, 0.08);
    }

    .admin-errors {
      margin-bottom: 20px;
      padding: 16px 18px;
      color: #9d2d00;
      background: #fff2ec;
      border-left: 5px solid #ff6a00;
      border-radius: 18px;
    }

    .admin-errors strong {
      display: block;
      margin-bottom: 8px;
    }

    .admin-errors ul {
      margin: 0;
      padding-left: 20px;
    }

    .admin-field {
      display: grid;
      gap: 8px;
      margin-bottom: 18px;
    }

    .admin-field label {
      color: #121212;
      font-weight: 800;
    }

    .admin-field small {
      color: #6b625b;
      line-height: 1.5;
    }

    .admin-field input,
    .admin-field textarea,
    .admin-field select {
      width: 100%;
      padding: 15px 16px;
      border: 1px solid rgba(18, 18, 18, 0.14);
      border-radius: 16px;
      outline: none;
      font: inherit;
      background: #fff;
    }

    .admin-field input[type="file"] {
      padding: 12px;
      background: #fbfaf8;
    }

    .admin-field textarea {
      min-height: 150px;
      resize: vertical;
    }

    .admin-field input:focus,
    .admin-field textarea:focus,
    .admin-field select:focus {
      border-color: rgba(255, 106, 0, 0.72);
      box-shadow: 0 0 0 4px rgba(255, 106, 0, 0.12);
    }

    .admin-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      margin-top: 24px;
    }

    .admin-back {
      display: inline-flex;
      margin-bottom: 20px;
      color: #ff6a00;
      font-weight: 800;
    }

    .admin-required {
      color: #b91c1c;
    }

    @media (max-width: 640px) {
      .admin-page {
        padding: 34px 0 60px;
      }

      .admin-form-card {
        padding: 22px 18px;
        border-radius: 22px;
      }

      .admin-actions {
        flex-direction: column;
      }

      .admin-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <main class="admin-page">
    <div class="container">

      <a class="admin-back" href="index.php">
        ← Retour à l’administration
      </a>

      <div class="admin-header">
        <h1>Ajouter une actualité</h1>

        <p>
          Ajoutez un document PDF et, si vous le souhaitez, une image de couverture.
          La publication apparaîtra automatiquement sur la page Actualités lorsqu’elle
          possède le statut « Publié ».
        </p>
      </div>

      <div class="admin-form-card">

        <?php if (!empty($errors)): ?>
          <div class="admin-errors" role="alert">
            <strong>L’actualité n’a pas été enregistrée :</strong>

            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">

          <input
            type="hidden"
            name="csrf_token"
            value="<?= h($csrfToken) ?>"
          >

          <div class="admin-field">
            <label for="titre">
              Titre de l’actualité
              <span class="admin-required" aria-hidden="true">*</span>
            </label>

            <input
              type="text"
              id="titre"
              name="titre"
              maxlength="255"
              required
              value="<?= h($titre) ?>"
              placeholder="Exemple : L’assainissement autonome, le nouvel or du Sénégal"
            >
          </div>

          <div class="admin-field">
            <label for="description">Description</label>

            <textarea
              id="description"
              name="description"
              placeholder="Présentez brièvement le contenu du document."
            ><?= h($description) ?></textarea>
          </div>

          <div class="admin-field">
            <label for="date_publication">
              Date de publication
              <span class="admin-required" aria-hidden="true">*</span>
            </label>

            <input
              type="date"
              id="date_publication"
              name="date_publication"
              required
              value="<?= h($datePublication) ?>"
            >
          </div>

          <div class="admin-field">
            <label for="statut">Statut</label>

            <select id="statut" name="statut">
              <option value="publie" <?= $statut === 'publie' ? 'selected' : '' ?>>
                Publié
              </option>

              <option value="brouillon" <?= $statut === 'brouillon' ? 'selected' : '' ?>>
                Brouillon
              </option>
            </select>

            <small>
              Une publication en brouillon reste enregistrée, mais elle n’apparaît pas
              sur la page publique.
            </small>
          </div>

          <div class="admin-field">
            <label for="pdf">
              Fichier PDF
              <span class="admin-required" aria-hidden="true">*</span>
            </label>

            <input
              type="file"
              id="pdf"
              name="pdf"
              accept="application/pdf,.pdf"
              required
            >

            <small>
              Format accepté : PDF. Taille maximale : 10 Mo.
            </small>
          </div>

          <div class="admin-field">
            <label for="image_publication">Image de couverture</label>

            <input
              type="file"
              id="image_publication"
              name="image_publication"
              accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
            >

            <small>
              Champ facultatif. Formats acceptés : JPG, PNG ou WebP.
              Taille maximale : 5 Mo. Pour un site plus léger, privilégiez une image WebP.
            </small>
          </div>

          <div class="admin-actions">
            <button class="btn btn--primary" type="submit">
              Enregistrer l’actualité
            </button>

            <a class="btn btn--secondary" href="index.php">
              Annuler
            </a>
          </div>

        </form>
      </div>
    </div>
  </main>
</body>
</html>
