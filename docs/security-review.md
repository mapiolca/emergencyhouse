# Revue de sécurité

## Mesures intégrées

- comptes publics séparés des utilisateurs Dolibarr ;
- mots de passe hachés avec les primitives PHP natives ;
- sessions publiques dédiées, jetons à durée limitée et invalidation serveur ;
- CSRF Dolibarr côté back-office et jeton public côté portail ;
- contrôles d’appartenance sur les objets accessibles publiquement ;
- limitation de débit pour l’authentification et les actions sensibles ;
- chiffrement authentifié XChaCha20-Poly1305 via Sodium ;
- clés de chiffrement et HMAC distinctes, créées automatiquement avec le
  générateur natif Dolibarr et enregistrées comme constantes sensibles globales ;
- chiffrement automatique de ces constantes par `dolibarr_set_const()` avec la
  clé unique de l’instance conservée hors base dans `conf.php` ;
- compatibilité de lecture conservée pour les installations historiques
  utilisant les deux noms fixes comme variables d’environnement ;
- refus des clés décodées de moins de 32 octets et refus d’une valeur commune
  aux deux usages ;
- adresses, coordonnées, positions, messages et notes sensibles chiffrés ;
- révélation des coordonnées soumise aux droits, au consentement ou à une
  justification contrôlée, avec trace d’audit ;
- requêtes métier filtrées par entité et partages Multicompany explicites ;
- détails techniques des services non renvoyés aux utilisateurs ;
- erreurs techniques masquées dans le portail public, le back-office et l’API ;
- file de messagerie publique séparée des Notifications natives, limitée aux
  comptes publics qui ne sont ni des utilisateurs ni des contacts Dolibarr ;
- fournisseurs externes désactivés par défaut et secrets non stockés en base.
- géocodage limité au domaine HTTPS de Géoplateforme, sans redirection et sans
  passage de l’adresse exacte dans le journal du helper HTTP Dolibarr v20 ;
- aperçu du portail protégé par l’authentification et le droit de configuration
  Dolibarr, sans lecture de donnée métier.

## Points à vérifier avant production

1. HTTPS forcé sur toutes les routes publiques.
2. Diagnostic vert pour les deux secrets distincts d’au moins 32 octets.
3. Sauvegarde conjointe de la base et de `conf.php`, avec test de restauration.
4. Politique CSP adaptée aux seuls domaines effectivement utilisés.
5. Configuration SMTP, antispam et délivrabilité des messages.
6. Tests IDOR avec deux comptes publics et deux entités.
7. Tests CSRF, fixation/vol de session, brute force et réinitialisation.
8. Vérification des droits administrateur, opérateur et utilisateur externe.
9. Contrôle des journaux Dolibarr sans secret ni donnée personnelle excessive.
10. Test de concurrence sur les réservations de capacité.
11. Vérification de la purge et des gels de conservation.
12. Revue indépendante du code et test d’intrusion avant ouverture publique.

## Risques résiduels

Le dépôt seul ne permet pas de valider la configuration du serveur, les
extensions PHP, le proxy HTTPS, la base, les tâches planifiées, la messagerie ni
les modules tiers. Une mauvaise gestion des clés rendrait les données
indéchiffrables ; leur exposition compromettrait les données chiffrées. La
recette opérationnelle et la supervision restent donc obligatoires.
