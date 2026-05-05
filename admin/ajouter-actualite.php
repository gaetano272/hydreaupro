<?php
require_once '../config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_publication = $_POST['date_publication'] ?? date('Y-m-d');
    $statut = $_POST['statut'] ?? 'publie';

    if ($titre === '') {
        $errors[] = "Le titre est obligatoire.";
    }

    if (!in_array($statut, ['brouillon', 'publie'], true)) {
        $statut = 'publie';
    }

    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Le fichier PDF est obligatoire.";
    } else {
        $file = $_FILES['pdf'];

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'pdf') {
            $errors[] = "Seuls les fichiers PDF sont autorisés.";
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = "Le PDF ne doit pas dépasser 10 Mo.";
        }
    }

    if (empty($errors)) {
        $uploadDir = '../uploads/actualites/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeTitle = preg_replace('/[^a-zA-Z0-9-_]/', '-', strtolower($titre));
        $safeTitle = trim($safeTitle, '-');

        if ($safeTitle === '') {
            $safeTitle = 'document';
        }

        $fileName = date('Ymd-His') . '-' . $safeTitle . '.pdf';
        $targetPath = $uploadDir . $fileName;

        if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $targetPath)) {
            $errors[] = "Impossible d’enregistrer le PDF.";
        } else {
            $publicPath = 'uploads/actualites/' . $fileName;

            $stmt = $pdo->prepare("
                INSERT INTO actualites (
                    titre,
                    description,
                    fichier_pdf,
                    date_publication,
                    statut
                ) VALUES (
                    :titre,
                    :description,
                    :fichier_pdf,
                    :date_publication,
                    :statut
                )
            ");

            $stmt->execute([
                ':titre' => $titre,
                ':description' => $description,
                ':fichier_pdf' => $publicPath,
                ':date_publication' => $date_publication,
                ':statut' => $statut
            ]);

            header('Location: index.php?success=1');
            exit;
        }
    }
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ajouter une actualité — HYDRAUPRO</title>
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

    .admin-form-card {
      max-width: 860px;
      background: #fff;
      border-radius: 28px;
      box-shadow: 0 12px 30px rgba(10,10,10,0.08);
      padding: 30px;
    }

    .admin-errors {
      margin-bottom: 20px;
      padding: 16px 18px;
      border-radius: 18px;
      background: #fff2ec;
      color: #9d2d00;
      border-left: 5px solid #ff6a00;
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
      font-weight: 800;
      color: #121212;
    }

    .admin-field input,
    .admin-field textarea,
    .admin-field select {
      width: 100%;
      padding: 15px 16px;
      border-radius: 16px;
      border: 1px solid rgba(18,18,18,0.14);
      outline: none;
      font: inherit;
    }

    .admin-field textarea {
      min-height: 150px;
      resize: vertical;
    }

    .admin-field input:focus,
    .admin-field textarea:focus,
    .admin-field select:focus {
      border-color: rgba(255,106,0,0.72);
      box-shadow: 0 0 0 4px rgba(255,106,0,0.12);
    }

    .admin-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      margin-top: 24px;
    }

    .admin-back {
      color: #ff6a00;
      font-weight: 800;
      display: inline-flex;
      margin-bottom: 20px;
    }
  </style>
</head>

<body>
  <main class="admin-page">
    <div class="container">
      <a class="admin-back" href="index.php">← Retour à l’administration</a>

      <div class="admin-header">
        <h1>Ajouter une actualité</h1>
        <p>Ajoutez un document PDF qui apparaîtra automatiquement sur la page Actualités.</p>
      </div>

      <div class="admin-form-card">
        <?php if (!empty($errors)): ?>
          <div class="admin-errors">
            <strong>Erreur :</strong>
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <div class="admin-field">
            <label for="titre">Titre de l’actualité</label>
            <input
              type="text"
              id="titre"
              name="titre"
              required
              value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>"
              placeholder="Exemple : Nouveau document HYDRAUPRO"
            >
          </div>

          <div class="admin-field">
            <label for="description">Description</label>
            <textarea
              id="description"
              name="description"
              placeholder="Courte description du document"
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>

          <div class="admin-field">
            <label for="date_publication">Date de publication</label>
            <input
              type="date"
              id="date_publication"
              name="date_publication"
              required
              value="<?= htmlspecialchars($_POST['date_publication'] ?? date('Y-m-d')) ?>"
            >
          </div>

          <div class="admin-field">
            <label for="statut">Statut</label>
            <select id="statut" name="statut">
              <option value="publie" <?= (($_POST['statut'] ?? 'publie') === 'publie') ? 'selected' : '' ?>>
                Publié
              </option>
              <option value="brouillon" <?= (($_POST['statut'] ?? '') === 'brouillon') ? 'selected' : '' ?>>
                Brouillon
              </option>
            </select>
          </div>

          <div class="admin-field">
            <label for="pdf">Fichier PDF</label>
            <input
              type="file"
              id="pdf"
              name="pdf"
              accept="application/pdf"
              required
            >
          </div>

          <div class="admin-actions">
            <button class="btn btn--primary" type="submit">
              Publier l’actualité
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