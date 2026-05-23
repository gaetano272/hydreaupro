<?php
/**
 * HYDREAUPRO — Page Actualités dynamique
 *
 * Cette page lit les actualités depuis la table MySQL `actualites`.
 * Elle attend un fichier : config/database.php
 * Ce fichier doit créer une variable $pdo, instance de PDO.
 *
 * Exemple de table :
 * CREATE TABLE actualites (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   titre VARCHAR(255) NOT NULL,
 *   description TEXT,
 *   fichier_pdf VARCHAR(255) NOT NULL,
 *   date_publication DATE NOT NULL,
 *   statut ENUM('brouillon', 'publie') DEFAULT 'publie',
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * );
 */

declare(strict_types=1);

$actualites = [];
$dbError = null;

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
        $dt = new DateTime($date);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return h($date);
    }
}

try {
    $databaseFile = __DIR__ . '/config/database.php';

    if (!file_exists($databaseFile)) {
        throw new RuntimeException("Le fichier config/database.php est introuvable.");
    }

    require_once $databaseFile;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException("La variable \$pdo n'est pas disponible dans config/database.php.");
    }

    $stmt = $pdo->prepare("\n        SELECT id, titre, description, fichier_pdf, date_publication, statut, created_at\n        FROM actualites\n        WHERE statut = 'publie'\n        ORDER BY date_publication DESC, created_at DESC\n    ");
    $stmt->execute();
    $actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // En production, il vaut mieux journaliser l'erreur côté serveur au lieu de l'afficher.
    $dbError = $e->getMessage();
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Actualités — HYDREAUPRO</title>
  <meta name="description" content="Actualités, conseils et documents HYDREAUPRO sur l’assainissement, l’eau potable et l’irrigation au Sénégal.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="style.css">
  <script defer src="main.js"></script>
</head>
<body class="prest-page actualites-page">

  <header class="hero-banner">
    <img src="images/bannieres.webp" alt="Bannière HYDREAUPRO">
  </header>

  <nav class="site-header" aria-label="Navigation principale">
    <div class="container nav-inner">
      <a href="index.html" class="brand">
        <img src="images/logo-hydreaupro.webp" alt="Logo HYDREAUPRO">
        <span class="brand-text">
          <span class="brand-text__blue">HYDREAU</span><span class="brand-text__orange">PRO</span>
        </span>
      </a>

      <button class="nav-toggle" type="button" aria-label="Ouvrir le menu" aria-expanded="false">☰</button>

      <ul class="nav-menu">
        <li><a href="index.html">Accueil</a></li>
        <li><a href="assainissement.html">Assainissement</a></li>
        <li><a href="distribution.html">Surpression</a></li>
        <li><a href="irrigation.html">Irrigation</a></li>
        <li><a href="actualites.php" class="active" aria-current="page">Actualités</a></li>
        <li><a href="contact.html">Contact</a></li>
      </ul>

      <a href="contact.html" class="btn-devis">Demander un devis</a>
    </div>
  </nav>

  <main>
    <section class="page-header">
      <div class="container">
        <div class="page-title">
          <h1>Actualités et documents</h1>
          <span class="page-title__brand">HYDREAUPRO</span>
          <div class="page-intro">
            <strong>HYDREAUPRO</strong>
            <p>
              Retrouvez ici nos documents PDF, conseils pratiques, informations techniques
              et actualités autour de l’assainissement autonome, de la surpression d’eau potable
              et de l’irrigation.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="container photo-gallery actualites-listing">
      <div class="photo-gallery__head">
        <div>
          <h2>Documents publiés</h2>
          <p>Les PDF ajoutés depuis l’espace d’administration apparaissent automatiquement ici.</p>
        </div>
      </div>

      <?php if ($dbError): ?>
        <div class="actualites-empty" role="status">
          <h3>Connexion à la base de données à configurer</h3>
          <p>
            La page est prête, mais elle ne peut pas encore afficher les actualités.
            Vérifiez le fichier <strong>config/database.php</strong> et la table <strong>actualites</strong>.
          </p>
          <!-- Erreur technique utile en phase de développement : <?= h($dbError) ?> -->
        </div>
      <?php elseif (empty($actualites)): ?>
        <div class="actualites-empty" role="status">
          <h3>Aucune actualité publiée pour le moment</h3>
          <p>
            Ajoutez un premier PDF depuis l’espace d’administration pour le faire apparaître automatiquement sur cette page.
          </p>
        </div>
      <?php else: ?>
        <div class="photo-gallery__grid actualites-grid">
          <?php foreach ($actualites as $actualite): ?>
            <article class="photo-card actualite-card">
              <div class="photo-card__body actualite-card__body">
                <time class="actualite-card__date" datetime="<?= h($actualite['date_publication'] ?? '') ?>">
                  <?= h(formatDateFr($actualite['date_publication'] ?? '')) ?>
                </time>

                <strong><?= h($actualite['titre'] ?? 'Actualité HYDREAUPRO') ?></strong>

                <?php if (!empty($actualite['description'])): ?>
                  <p><?= nl2br(h($actualite['description'])) ?></p>
                <?php endif; ?>

                <a class="btn-cta actualite-card__link" href="<?= h($actualite['fichier_pdf'] ?? '#') ?>" target="_blank" rel="noopener">
                  Voir le PDF
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="cta-band">
      <div class="container">
        <h2>Besoin d’un conseil technique ?</h2>
        <p>
          Contactez HYDREAUPRO pour échanger sur votre projet d’assainissement,
          de surpression d’eau potable ou d’irrigation.
        </p>
        <div class="btn-row">
          <a class="btn-cta" href="contact.html">Nous contacter</a>
          <a class="btn-outline" href="index.html">Retour à l’accueil</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container site-footer__inner">
      <small>© 2026 HYDREAUPRO — Assainissement autonome, surpression d’eau potable et irrigation au Sénégal.</small>
      <small><a href="mentions-legales.html">Mentions légales</a> · <a href="mailto:contact@hydreaupro.sn">contact@hydraupro.sn</a></small>
    </div>
  </footer>
</body>
</html>
