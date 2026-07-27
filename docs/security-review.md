# Revue de sécurité

## Mesures intégrées

- comptes publics séparés des utilisateurs Dolibarr ;
- mots de passe hachés avec les primitives PHP natives ;
- sessions publiques dédiées, jetons à durée limitée et invalidation serveur ;
- CSRF Dolibarr côté back-office et jeton public côté portail ;
- contrôles d’appartenance sur les objets accessibles publiquement ;
- limitation de débit pour l’authentification et les actions sensibles ;
- chiffrement authentifié XChaCha20-Poly1305 via Sodium ;
- clés de chiffrement et HMAC distinctes, chargées depuis l’environnement ;
- adresses, coordonnées, positions, messages et notes sensibles chiffrés ;
- révélation des coordonnées soumise aux droits, au consentement ou à une
  justification contrôlée, avec trace d’audit ;
- requêtes métier filtrées par entité et partages Multicompany explicites ;
- détails techniques des services non renvoyés aux utilisateurs ;
- erreurs techniques masquées dans le portail public, le back-office et l’API ;
- file de messagerie publique séparée des Notifications natives, limitée aux
  comptes publics qui ne sont ni des utilisateurs ni des contacts Dolibarr ;
- fournisseurs externes désactivés par défaut et secrets non stockés en base.

## Points à vérifier avant production

1. HTTPS forcé sur toutes les routes publiques.
2. Deux secrets aléatoires distincts d’au moins 32 octets.
3. Sauvegarde et procédure de rotation des clés avec test de restauration.
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
