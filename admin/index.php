<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDateFr(?string $date): string
{
    if (!$date) {
        return '';
    }

    try {
        return (new DateTime($date))->format('d/m/Y');
    } catch (Throwable $e) {
        return (string) $date;
    }
}

$csrfToken = getAdminCsrfToken();

$stmt = $pdo->query(
    "SELECT
        id,
        titre,
        description,
        fichier_pdf,
        image_publication,
        date_publication,
        statut,
        created_at
     FROM actualites
     ORDER BY date_publication DESC, created_at DESC"
);

$actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);

$successMessage = null;

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $successMessage = 'L’actualité a bien été ajoutée.';
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $successMessage = 'L’actualité, son PDF et son image ont bien été supprimés.';
}

$errorMessage = null;

if (isset($_GET['error'])) {
    $errorMessage = match ((string) $_GET['error']) {
        'invalid-request' => 'La demande de suppression est invalide.',
        'invalid-token' => 'La session de sécurité a expiré. Rechargez la page et recommencez.',
        'not-found' => 'L’actualité demandée est introuvable.',
        'delete-failed' => 'La suppression n’a pas pu être terminée.',
        default => 'Une erreur est survenue.',
    };
}
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Administration des actualités — HYDREAUPRO</title>

  <link rel="stylesheet" href="../style.css">

  <style>
    body {
      background: #f6f3ef;
    }

    .admin-page {
      padding: 50px 0 80px;
    }

    .admin-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
      margin-bottom: 30px;
    }

    .admin-header h1 {
      margin: 0;
      font-family: var(--font-title, Georgia, serif);
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1.1;
      color: #121212;
    }

    .admin-header__actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 12px;
    }

    .admin-header__actions form {
      margin: 0;
    }

    .admin-header p {
      max-width: 760px;
      margin: 10px 0 0;
      color: #5e564f;
      line-height: 1.65;
    }

    .admin-alert {
      margin-bottom: 20px;
      padding: 16px 18px;
      border-radius: 18px;
      font-weight: 700;
    }

    .admin-alert--success {
      color: #166534;
      background: #ecfdf3;
      border-left: 5px solid #16a34a;
    }

    .admin-alert--error {
      color: #9d2d00;
      background: #fff2ec;
      border-left: 5px solid #ff6a00;
    }

    .admin-card {
      padding: 26px;
      margin-bottom: 18px;
      overflow-x: auto;
      background: #fff;
      border-radius: 28px;
      box-shadow: 0 12px 30px rgba(10, 10, 10, 0.08);
    }

    .admin-table {
      width: 100%;
      min-width: 1040px;
      border-collapse: collapse;
    }

    .admin-table th,
    .admin-table td {
      padding: 16px 12px;
      text-align: left;
      vertical-align: middle;
      border-bottom: 1px solid rgba(18, 18, 18, 0.08);
    }

    .admin-table th {
      color: #121212;
      font-weight: 800;
      background: #fbfaf8;
    }

    .admin-table tr:last-child td {
      border-bottom: 0;
    }

    .admin-cover {
      width: 118px;
      height: 78px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: #f2eee9;
      border: 1px solid rgba(18, 18, 18, 0.08);
      border-radius: 14px;
    }

    .admin-cover img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
      object-position: center;
    }

    .admin-cover--empty {
      padding: 10px;
      color: #776d65;
      font-size: 0.78rem;
      font-weight: 800;
      text-align: center;
    }

    .admin-title {
      display: block;
      color: #121212;
      font-weight: 800;
      line-height: 1.35;
    }

    .admin-description {
      display: -webkit-box;
      max-width: 390px;
      margin-top: 6px;
      overflow: hidden;
      color: #5e564f;
      font-size: 0.92rem;
      line-height: 1.5;
      -webkit-box-orient: vertical;
      line-clamp: 3;
      -webkit-line-clamp: 3;
    }

    .admin-status {
      display: inline-flex;
      min-height: 32px;
      align-items: center;
      padding: 0 12px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
    }

    .admin-status--publie {
      color: #15803d;
      background: rgba(22, 163, 74, 0.10);
    }

    .admin-status--brouillon {
      color: #4b5563;
      background: rgba(107, 114, 128, 0.12);
    }

    .admin-link {
      color: #ff6a00;
      font-weight: 800;
    }

    .admin-link:hover {
      text-decoration: underline;
    }

    .admin-delete-form {
      margin: 0;
    }

    .admin-delete {
      padding: 0;
      color: #b91c1c;
      background: transparent;
      border: 0;
      font: inherit;
      font-weight: 800;
      cursor: pointer;
    }

    .admin-delete:hover {
      text-decoration: underline;
    }

    .admin-empty {
      margin: 0;
      color: #5e564f;
      line-height: 1.6;
    }

    .admin-bottom-link {
      margin-top: 18px;
    }

    @media (max-width: 760px) {
      .admin-page {
        padding: 34px 0 60px;
      }

      .admin-header {
        flex-direction: column;
        align-items: stretch;
      }

      .admin-header__actions {
        width: 100%;
        flex-direction: column;
      }

      .admin-header__actions form,
      .admin-header__actions .btn {
        width: 100%;
      }

      .admin-card {
        padding: 20px;
        overflow-x: visible;
        border-radius: 22px;
      }

      .admin-table {
        min-width: 0;
      }

      .admin-table,
      .admin-table thead,
      .admin-table tbody,
      .admin-table th,
      .admin-table td,
      .admin-table tr {
        display: block;
      }

      .admin-table thead {
        display: none;
      }

      .admin-table tr {
        padding: 20px 0;
        border-bottom: 1px solid rgba(18, 18, 18, 0.10);
      }

      .admin-table tr:first-child {
        padding-top: 0;
      }

      .admin-table tr:last-child {
        padding-bottom: 0;
        border-bottom: 0;
      }

      .admin-table td {
        padding: 8px 0;
        border: 0;
      }

      .admin-table td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 5px;
        color: #121212;
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
      }

      .admin-cover {
        width: 100%;
        height: auto;
        aspect-ratio: 16 / 9;
      }

      .admin-description {
        max-width: none;
      }
    }
  </style>
</head>

<body>

  <main class="admin-page">
    <div class="container">

      <div class="admin-header">
        <div>
          <h1>Administration des actualités</h1>

          <p>
            Gérez les PDF, les images de couverture et le statut des publications
            visibles sur la page Actualités.
          </p>
        </div>

        <div class="admin-header__actions">
          <a class="btn btn--primary" href="ajouter-actualite.php">
            Ajouter une actualité
          </a>

          <a class="btn btn--secondary" href="changer-mot-de-passe.php">
            Changer le mot de passe
          </a>

          <form method="post" action="deconnexion.php">
            <input
              type="hidden"
              name="csrf_token"
              value="<?= h($csrfToken) ?>"
            >

            <button class="btn btn--secondary" type="submit">
              Se déconnecter
            </button>
          </form>
        </div>
      </div>

      <?php if ($successMessage !== null): ?>
        <div class="admin-alert admin-alert--success" role="status">
          <?= h($successMessage) ?>
        </div>
      <?php endif; ?>

      <?php if ($errorMessage !== null): ?>
        <div class="admin-alert admin-alert--error" role="alert">
          <?= h($errorMessage) ?>
        </div>
      <?php endif; ?>

      <div class="admin-card">

        <?php if (empty($actualites)): ?>

          <p class="admin-empty">
            Aucune actualité n’est enregistrée pour le moment.
            Utilisez le bouton « Ajouter une actualité » pour publier votre premier document.
          </p>

        <?php else: ?>

          <table class="admin-table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Date</th>
                <th>Publication</th>
                <th>Statut</th>
                <th>PDF</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>

              <?php foreach ($actualites as $actualite): ?>
                <?php
                  $imagePath = trim((string) ($actualite['image_publication'] ?? ''));
                  $pdfPath = trim((string) ($actualite['fichier_pdf'] ?? ''));
                  $title = (string) ($actualite['titre'] ?? 'Actualité HYDREAUPRO');
                  $status = (string) ($actualite['statut'] ?? 'brouillon');
                ?>

                <tr>

                  <td data-label="Image">
                    <?php if ($imagePath !== ''): ?>
                      <div class="admin-cover">
                        <img
                          src="../<?= h(ltrim($imagePath, '/')) ?>"
                          alt="Couverture de la publication : <?= h($title) ?>"
                          loading="lazy"
                        >
                      </div>
                    <?php else: ?>
                      <div class="admin-cover admin-cover--empty">
                        Sans image
                      </div>
                    <?php endif; ?>
                  </td>

                  <td data-label="Date">
                    <?= h(formatDateFr($actualite['date_publication'] ?? '')) ?>
                  </td>

                  <td data-label="Publication">
                    <span class="admin-title">
                      <?= h($title) ?>
                    </span>

                    <?php if (!empty($actualite['description'])): ?>
                      <span class="admin-description">
                        <?= h((string) $actualite['description']) ?>
                      </span>
                    <?php endif; ?>
                  </td>

                  <td data-label="Statut">
                    <?php if ($status === 'publie'): ?>
                      <span class="admin-status admin-status--publie">
                        Publié
                      </span>
                    <?php else: ?>
                      <span class="admin-status admin-status--brouillon">
                        Brouillon
                      </span>
                    <?php endif; ?>
                  </td>

                  <td data-label="PDF">
                    <?php if ($pdfPath !== ''): ?>
                      <a
                        class="admin-link"
                        href="../<?= h(ltrim($pdfPath, '/')) ?>"
                        target="_blank"
                        rel="noopener"
                      >
                        Ouvrir le PDF
                      </a>
                    <?php else: ?>
                      <span>PDF absent</span>
                    <?php endif; ?>
                  </td>

                  <td data-label="Actions">
                    <form
                      class="admin-delete-form"
                      method="post"
                      action="supprimer-actualite.php"
                      onsubmit="return confirm('Supprimer définitivement cette actualité, son PDF et son image ?');"
                    >
                      <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $actualite['id'] ?>"
                      >

                      <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h($csrfToken) ?>"
                      >

                      <button class="admin-delete" type="submit">
                        Supprimer
                      </button>
                    </form>
                  </td>

                </tr>
              <?php endforeach; ?>

            </tbody>
          </table>

        <?php endif; ?>

      </div>

      <p class="admin-bottom-link">
        <a class="admin-link" href="../actualites.php">
          Voir la page Actualités
        </a>
      </p>

    </div>
  </main>

</body>
</html>
