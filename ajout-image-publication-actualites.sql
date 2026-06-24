-- Ajoute un chemin d'image facultatif à chaque publication.
-- La page actualites.php continue de fonctionner même avant cette modification.

ALTER TABLE actualites
ADD COLUMN image_publication VARCHAR(255) NULL AFTER fichier_pdf;

-- Exemple pour associer une image à la publication ayant l'identifiant 1 :
-- UPDATE actualites
-- SET image_publication = 'images/actualites/assainissement-nouvel-or-senegal.webp'
-- WHERE id = 1;
