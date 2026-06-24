-- À exécuter dans phpMyAdmin sur la base `hydreaupro`.
-- Cette requête associe la couverture à la publication existante.

UPDATE actualites
SET image_publication = 'uploads/actualites/images/assainissement-autonome-nouvel-or-senegal.webp'
WHERE titre LIKE '%assainissement autonome%'
LIMIT 1;

-- Vérification : la ligne doit afficher le chemin ci-dessus.
SELECT id, titre, image_publication
FROM actualites
ORDER BY id DESC;
