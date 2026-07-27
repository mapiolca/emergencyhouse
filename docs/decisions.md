# Décisions d'architecture

## ADR-001 — Racine du module

Les fichiers du module sont placés directement dans ce dépôt. Une racine
imbriquée `emergencyhouse/emergencyhouse` est interdite.

## ADR-002 — Identifiant du module

Le numéro retenu est `450201`, dans la plage `450000–450999` réservée à
Pierre ARDOIN. Il a été contrôlé contre les dépôts publics `mapiolca`.
Un contrôle des modules privés/localement installés reste obligatoire avant
la première activation.

## ADR-003 — Comptes publics

Les particuliers utilisent des comptes et sessions Emergency House. Aucun
utilisateur Dolibarr n'est créé implicitement.

## ADR-004 — Chiffrement

Sodium est utilisé pour le chiffrement applicatif. La clé principale reste
hors dépôt et hors base. Des empreintes HMAC distinctes servent à la recherche.

## ADR-005 — Navigation

Emergency House dispose d'un menu haut dédié, explicitement retenu compte tenu
de son périmètre opérationnel autonome.

## ADR-006 — Fournisseurs externes

Les tuiles OpenStreetMap constituent le réglage cartographique initial.
Le géocodage exact et le SMS restent désactivés tant qu'un fournisseur
approprié n'est pas configuré. Aucun secret n'est livré.

## ADR-007 — Politique de publication

Les demandes sont privées par défaut. Une offre nécessite un e-mail vérifié et
une validation opérateur avant publication, avec surcharge possible par
campagne.

