<?php
require_once '../config/database.php';

$stmt = $pdo->query("
    SELECT *
    FROM actualites
    ORDER BY date_publication DESC, created_at DESC
");

$actualites = $stmt->fetchAll();
?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Actualités — HYDRAUPRO</title>
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
      gap: 20px;
      margin-bottom: 30px;
    }

    .admin-header h1 {
      margin: 0;
      font-family: var(--font-title, Georgia, serif);
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1.1;
      color: #121212;
    }

    .admin-header p {
      margin: 10px 0 0;
      color: #5e564f;
    }

    .admin-alert {
      margin-bottom: 20px;
      padding: 16px 18px;
      border-radius: 18px;
      background: #fff7ed;
      border-left: 5px solid #ff6a00;
      color: #121212;
      font-weight: 700;
    }

    .admin-card {
      background: #fff;
      border-radius: 28px;
      box-shadow: 0 12px 30px rgba(10,10,10,0.08);
      padding: 26px;
      margin-bottom: 18px;
      overflow-x: auto;
    }

    .admin-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 820px;
    }

    .admin-table th,
    .admin-table td {
      padding: 16px 12px;
      border-bottom: 1px solid rgba(18,18,18,0.08);
      text-align: left;
      vertical-align: top;
    }

    .admin-table th {
      color: #121212;
      font-weight: 800;
      background: #fbfaf8;
    }

    .admin-table tr:last-child td {
      border-bottom: 0;
    }

    .admin-title {
      font-weight: 800;
      color: #121212;
    }

    .admin-description {
      display: block;
      margin-top: 6px;
      color: #5e564f;
      font-size: 0.92rem;
      line-height: 1.5;
    }

    .admin-status {
      display: inline-flex;
      min-height: 32px;
      align-items: center;
      padding: 0 12px;
      border-radius: 999px;
      font-size: 0.86rem;
      font-weight: 800;
      text-transform: uppercase;
    }

    .admin-status--publie {
      background: rgba(22, 163, 74, 0.10);
      color: #15803d;
    }

    .admin-status--brouillon {
      background: rgba(107, 114, 128, 0.12);
      color: #4b5563;
    }

    .admin-link {
      color: #ff6a00;
      font-weight: 800;
    }

    .admin-link:hover {
      text-decoration: underline;
    }

    .admin-delete {
      color: #b91c1c;
      font-weight: 800;
    }

    .admin-delete:hover {
      text-decoration: underline;
    }

    .admin-empty {
      margin: 0;
      color: #5e564f;
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
        align-items: flex-start;
      }

      .admin-card {
        overflow-x: visible;
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
        padding: 18px 0;
        border-bottom: 1px solid rgba(18,18,18,0.08);
      }

      .admin-table tr:last-child {
        border-bottom: 0;
      }

      .admin-table td {
        border: 0;
        padding: 8px 0;
      }

      .admin-table td::before {
        content: attr(data-label) " : ";
        display: block;
        margin-bottom: 2px;
        font-weight: 800;
        color: #121212;
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
          <p>Gérez ici les documents PDF qui apparaissent sur la page Actualités.</p>
        </div>

        <a class="btn btn--primary" href="ajouter-actualite.php">
          Ajouter une actualité
        </a>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert">
          L’actualité a bien été ajoutée.
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['deleted'])): ?>
        <div class="admin-alert">
          L’actualité a bien été supprimée.
        </div>
      <?php endif; ?>

      <div class="admin-card">
        <?php if (empty($actualites)): ?>
          <p class="admin-empty">Aucune actualité enregistrée pour le moment.</p>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Titre</th>
                <th>Statut</th>
                <th>PDF</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <?php foreach ($actualites as $actualite): ?>
                <tr>
                  <td data-label="Date">
                    <?= htmlspecialchars(date('d/m/Y', strtotime($actualite['date_publication']))) ?>
                  </td>

                  <td data-label="Titre">
                    <span class="admin-title">
                      <?= htmlspecialchars($actualite['titre']) ?>
                    </span>

                    <?php if (!empty($actualite['description'])): ?>
                      <span class="admin-description">
                        <?= htmlspecialchars($actualite['description']) ?>
                      </span>
                    <?php endif; ?>
                  </td>

                  <td data-label="Statut">
                    <?php if ($actualite['statut'] === 'publie'): ?>
                      <span class="admin-status admin-status--publie">Publié</span>
                    <?php else: ?>
                      <span class="admin-status admin-status--brouillon">Brouillon</span>
                    <?php endif; ?>
                  </td>

                  <td data-label="PDF">
                    <a
                      class="admin-link"
                      href="../<?= htmlspecialchars($actualite['fichier_pdf']) ?>"
                      target="_blank"
                      rel="noopener"
                    >
                      Ouvrir le PDF
                    </a>
                  </td>

                  <td data-label="Actions">
                    <a
                      class="admin-delete"
                      href="supprimer-actualite.php?id=<?= (int) $actualite['id'] ?>"
                      onclick="return confirm('Supprimer cette actualité et son PDF ?');"
                    >
                      Supprimer
                    </a>
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