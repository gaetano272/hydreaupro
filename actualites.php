<?php
/**
 * HYDREAUPRO — Page Actualités dynamique
 *
 * Cette page lit les actualités depuis la table MySQL `actualites`.
 * Elle attend un fichier : config/database.php
 * Ce fichier doit créer une variable $pdo, instance de PDO.
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
        throw new RuntimeException('Le fichier config/database.php est introuvable.');
    }

    require_once $databaseFile;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('La variable $pdo n’est pas disponible dans config/database.php.');
    }

    /*
     * Compatibilité progressive :
     * - si la colonne image_publication existe, elle est utilisée ;
     * - sinon, la page continue de fonctionner avec un visuel PDF par défaut.
     */
    $imageColumnExists = false;

    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM actualites LIKE 'image_publication'");
        $imageColumnExists = (bool) $columnCheck->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $imageColumnExists = false;
    }

    $imageSelect = $imageColumnExists
        ? 'image_publication'
        : 'NULL AS image_publication';

    $stmt = $pdo->prepare("
        SELECT
            id,
            titre,
            description,
            fichier_pdf,
            {$imageSelect},
            date_publication,
            statut,
            created_at
        FROM actualites
        WHERE statut = 'publie'
        ORDER BY date_publication DESC, created_at DESC
    ");

    $stmt->execute();
    $actualites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Actualités et documents — HYDREAUPRO</title>

  <meta
    name="description"
    content="Actualités, conseils et documents HYDREAUPRO sur l’assainissement autonome, la surpression d’eau potable et l’irrigation au Sénégal."
  >

  <link rel="icon" href="images/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="images/favicon-hydreaupro.png">
<link rel="apple-touch-icon" href="images/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="style.css">
  <script defer src="main.js"></script>
</head>
<body class="prest-page actualites-page">

  <nav class="site-header" aria-label="Navigation principale">
    <div class="container nav-inner">

      <a href="index.html" class="brand">
        <img src="images/logo-hydreaupro.webp" alt="Logo HYDREAUPRO">

        <span class="brand-text">
          <span class="brand-text__blue">HYDREAU</span><span class="brand-text__orange">PRO</span>
        </span>
      </a>

      <button
        class="nav-toggle"
        type="button"
        aria-label="Ouvrir le menu"
        aria-expanded="false"
      >
        ☰
      </button>

      <ul class="nav-menu">
        <li><a href="index.html">Accueil</a></li>
        <li><a href="assainissement.html">Assainissement</a></li>
        <li><a href="surpression.html">Surpression</a></li>
        <li><a href="irrigation.html">Irrigation</a></li>
        <li><a href="actualites.php" class="active" aria-current="page">Actualités</a></li>
        <li><a href="contact.html">Contact</a></li>
      </ul>

      <a href="contact.html" class="btn-devis">Demander un devis</a>
    </div>
  </nav>

  <main>

    <section class="page-header actualites-page__header">
      <div class="container">
        <div class="page-title">

          <h1>Actualités et documents</h1>

          <div class="page-intro">
            <p>
              Découvrez nos publications, conseils pratiques et ressources techniques autour
              de l’assainissement autonome, de la surpression d’eau potable et de l’irrigation
              au Sénégal.
            </p>
          </div>

        </div>
      </div>
    </section>

    <section class="container photo-gallery actualites-listing">

      <div class="photo-gallery__head actualites-listing__head">
        <div>
          <h2>Documents publiés</h2>
          <p>
            Parcourez nos dernières publications et ouvrez le document qui vous intéresse.
          </p>
        </div>
      </div>

      <?php if ($dbError): ?>

        <div class="actualites-empty" role="status">
          <h3>Connexion à la base de données à configurer</h3>

          <p>
            La page est prête, mais elle ne peut pas encore afficher les actualités.
            Vérifiez le fichier <strong>config/database.php</strong> et la table
            <strong>actualites</strong>.
          </p>

        </div>

      <?php elseif (empty($actualites)): ?>

        <div class="actualites-empty" role="status">
          <h3>Aucune actualité publiée pour le moment</h3>

          <p>
            Une première publication sera bientôt disponible dans cet espace.
          </p>
        </div>

      <?php else: ?>

        <div class="actualites-grid">

          <?php foreach ($actualites as $actualite): ?>
            <?php
              $titre = $actualite['titre'] ?? 'Actualité HYDREAUPRO';
              $pdf = $actualite['fichier_pdf'] ?? '#';
              $image = trim((string) ($actualite['image_publication'] ?? ''));
            ?>

            <article class="actualite-card">

              <a
                class="actualite-card__media"
                href="<?= h($pdf) ?>"
                target="_blank"
                rel="noopener"
                aria-label="Ouvrir le PDF : <?= h($titre) ?>"
              >
                <?php if ($image !== ''): ?>
                  <img
                    src="<?= h($image) ?>"
                    alt="Aperçu de la publication : <?= h($titre) ?>"
                    loading="lazy"
                  >
                <?php else: ?>
                  <span class="actualite-card__placeholder" aria-hidden="true">
                    <span class="actualite-card__placeholder-icon">PDF</span>
                    <span>Publication HYDREAUPRO</span>
                  </span>
                <?php endif; ?>

                <span class="actualite-card__format">Document PDF</span>
              </a>

              <div class="actualite-card__body">

                <time
                  class="actualite-card__date"
                  datetime="<?= h($actualite['date_publication'] ?? '') ?>"
                >
                  <?= h(formatDateFr($actualite['date_publication'] ?? '')) ?>
                </time>

                <h3><?= h($titre) ?></h3>

                <?php if (!empty($actualite['description'])): ?>
                  <p><?= nl2br(h($actualite['description'])) ?></p>
                <?php endif; ?>

                <a
                  class="btn-cta actualite-card__link"
                  href="<?= h($pdf) ?>"
                  target="_blank"
                  rel="noopener"
                >
                  Découvrir la publication
                </a>

              </div>
            </article>
          <?php endforeach; ?>

        </div>

      <?php endif; ?>

    </section>

    <section class="cta-band cta-band--split">
      <div class="container cta-band__inner">
        <div class="cta-band__text">
          <h2>Une question ? Un projet ?</h2>
          <p>Contactez-nous et nos équipes vous répondront dans les plus brefs délais.</p>
        </div>
        <div class="btn-row">
          <a href="contact.html" class="btn-cta">Nous contacter</a>
        </div>
      </div>
    </section>

  </main>

  <footer class="site-footer">
    <div class="container site-footer__inner">

      <small>
        © 2026 HYDREAUPRO — Assainissement autonome, surpression d’eau potable
        et irrigation au Sénégal.
      </small>

      <small>
        <a href="mentions-legales.html">Mentions légales</a>
        ·
        <a href="mailto:contact@hydreaupro.sn">contact@hydreaupro.sn</a>
      </small>

    </div>
  </footer>

</body>
</html>
