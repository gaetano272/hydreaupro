<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/database.php';

/**
 * Redirige vers la liste des actualités puis arrête le script.
 */
function redirectToAdmin(string $query = ''): never
{
    $location = 'index.php';

    if ($query !== '') {
        $location .= '?' . $query;
    }

    header('Location: ' . $location);
    exit;
}

/**
 * Retourne un chemin réel uniquement si le fichier demandé se trouve bien
 * dans uploads/actualites/. Cela empêche toute suppression en dehors
 * du dossier autorisé.
 */
function resolveUploadPath(?string $publicPath): ?string
{
    if ($publicPath === null) {
        return null;
    }

    $publicPath = trim($publicPath);

    if ($publicPath === '') {
        return null;
    }

    $normalizedPath = str_replace('\\', '/', $publicPath);
    $normalizedPath = ltrim($normalizedPath, '/');

    $allowedPrefix = 'uploads/actualites/';

    if (!str_starts_with($normalizedPath, $allowedPrefix)) {
        return null;
    }

    if (
        str_contains($normalizedPath, '../')
        || str_contains($normalizedPath, '/..')
        || str_contains($normalizedPath, "\0")
    ) {
        return null;
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $uploadsRoot = realpath(__DIR__ . '/../uploads/actualites');

    if ($projectRoot === false || $uploadsRoot === false) {
        return null;
    }

    $candidate = $projectRoot
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

    $realCandidate = realpath($candidate);

    if ($realCandidate === false || !is_file($realCandidate)) {
        return null;
    }

    $allowedRoot = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!str_starts_with($realCandidate, $allowedRoot)) {
        return null;
    }

    return $realCandidate;
}

/**
 * Supprime un fichier autorisé s’il existe.
 */
function deleteUploadedFile(?string $publicPath): bool
{
    $resolvedPath = resolveUploadPath($publicPath);

    if ($resolvedPath === null) {
        return true;
    }

    return unlink($resolvedPath);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToAdmin('error=invalid-request');
}

$id = $_POST['id'] ?? null;
$submittedToken = $_POST['csrf_token'] ?? null;

if (
    !is_string($id)
    || $id === ''
    || !ctype_digit($id)
    || (int) $id < 1
) {
    redirectToAdmin('error=invalid-request');
}

if (
    !verifyAdminCsrfToken(
        is_string($submittedToken) ? $submittedToken : null
    )
) {
    redirectToAdmin('error=invalid-token');
}

$actualiteId = (int) $id;

try {
    $stmt = $pdo->prepare(
        'SELECT id, fichier_pdf, image_publication
         FROM actualites
         WHERE id = :id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $actualiteId,
    ]);

    $actualite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$actualite) {
        redirectToAdmin('error=not-found');
    }

    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare(
        'DELETE FROM actualites
         WHERE id = :id'
    );

    $deleteStmt->execute([
        ':id' => $actualiteId,
    ]);

    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException('La ligne MySQL n’a pas été supprimée.');
    }

    $pdo->commit();

    /*
     * La suppression en base est effectuée avant celle des fichiers.
     * Ainsi, une publication supprimée ne reste jamais visible sur le site.
     * Si un fichier ne peut pas être retiré, l’erreur est journalisée côté serveur.
     */
    $pdfDeleted = deleteUploadedFile(
        isset($actualite['fichier_pdf'])
            ? (string) $actualite['fichier_pdf']
            : null
    );

    $imageDeleted = deleteUploadedFile(
        isset($actualite['image_publication'])
            ? (string) $actualite['image_publication']
            : null
    );

    if (!$pdfDeleted || !$imageDeleted) {
        error_log(
            sprintf(
                'HYDREAUPRO : fichiers non supprimés pour l’actualité ID %d.',
                $actualiteId
            )
        );
    }

    /*
     * Renouvelle le jeton après une opération destructive pour éviter sa réutilisation.
     */
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    redirectToAdmin('deleted=1');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        sprintf(
            'HYDREAUPRO : échec de suppression de l’actualité ID %d — %s',
            $actualiteId,
            $e->getMessage()
        )
    );

    redirectToAdmin('error=delete-failed');
}
