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

