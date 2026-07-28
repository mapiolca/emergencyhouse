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

## Vérification

Les comptes publics, offres et demandes portent tous `verification_status` :

- `0` : en attente ;
- `1` : vérifié ;
- `2` : refusé.

Pour les comptes publics, `manual_verification_level` conserve le niveau
obtenu lorsque l’état vaut `1`. Il est remis à zéro lors d’un refus.

`emergencyhouse_verification_queue` contient une seule ligne durable par
couple `(entity, object_type, fk_object)`. La ligne conserve l’attributaire,
les dates d’entrée, d’attribution et de traitement, l’état de file et la clé
`fk_verification` vers la décision finale. Une nouvelle soumission réactive
cette même ligne : aucun historique concurrent n’est créé dans la file.

`emergencyhouse_verification_rotation` conserve le dernier utilisateur servi
pour chaque entité. Sa ligne est verrouillée pendant l’attribution afin que
deux soumissions simultanées ne consomment pas le même tour.

`emergencyhouse_verification` reste le registre immuable des décisions. La
clôture d’une ligne de file et l’écriture du registre sont transactionnelles ;
la vue **Historique** continue donc d’exposer les décisions antérieures sans
dépendre de l’état actif de la file.
