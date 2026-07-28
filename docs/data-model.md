# Modèle de données

Le schéma est réparti entre objets métier, identités publiques, tables de
liaison et tables techniques.

## Invariants

- Chaque ligne opérationnelle possède une entité explicite.
- Les références vers Dolibarr ou le module utilisent des colonnes `fk_*`.
- Les relations multiples utilisent des tables de liaison.
- Les données personnelles critiques sont chiffrées ; leurs empreintes de
  recherche sont stockées séparément.
- Aucune suppression en cascade ne pilote une règle métier.
- Les opérations rejouables utilisent des clés d'idempotence uniques.
- Les instantanés JSON sont versionnés et ne remplacent pas les relations.

## Relations principales

`campaign` possède des `offer` et `request`. Un `match` relie une offre et une
demande pour une version d'algorithme. Une `solicitation` relie le même couple
et porte une conversation. Une `allocation` réserve une quantité sur une offre
pour une demande. Les équipements et critères sont normalisés dans des tables
de liaison. Les comptes publics portent sessions, jetons et consentements.

Leur colonne nullable `fk_member` référence au plus un adhérent natif dans la
même entité ; l’unicité `(entity, fk_member)` empêche qu’une fiche d’adhérent
soit liée à plusieurs comptes publics. Cette liaison ne crée pas de seconde
source de vérité : l’identité nécessaire au portail reste chiffrée dans le
compte public, tandis que la fiche d’adhérent suit son cycle administratif
natif.

`offer_photo` référence une offre par `fk_offer` et conserve le nom technique,
l’empreinte SHA-256, l’ordre d’affichage et l’état de validation de chaque
photo. Le contenu du fichier reste dans le répertoire documentaire de l’entité
propriétaire de l’offre. Les fichiers sources ne sont jamais conservés :
l’image est réencodée afin de supprimer ses métadonnées EXIF et GPS avant
stockage.
