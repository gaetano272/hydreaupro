<?php
require_once '../config/database.php';

$id = $_GET['id'] ?? null;

if (!$id || !ctype_digit($id)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM actualites WHERE id = ?");
$stmt->execute([$id]);
$actualite = $stmt->fetch();

if (!$actualite) {
    header('Location: index.php');
    exit;
}

if (!empty($actualite['fichier_pdf'])) {
    $filePath = '../' . $actualite['fichier_pdf'];

    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

$delete = $pdo->prepare("DELETE FROM actualites WHERE id = ?");
$delete->execute([$id]);

header('Location: index.php?deleted=1');
exit;