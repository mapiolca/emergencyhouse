# Rapport de tests

## Environnement disponible

- macOS ;
- PHP CLI 8.5.7 ;
- aucune instance Dolibarr locale ;
- aucune base MySQL/MariaDB reliée au module ;
- aucun exécutable ou fichier de configuration PHPStan ;
- aucun serveur web du module disponible pour une recette navigateur.

## Contrôles autonomes

Les contrôles suivants sont exécutables depuis la racine du module :

```bash
php test/static-contracts.php
php test/public-url-contract.php
```

Le script vérifie notamment :

- le numéro, la famille, la version et l’unique page de configuration ;
- la formule de numérotation des permissions ;
- l’absence de superglobales brutes et de contournement CSRF ;
- l’absence de triggers custom non CRUD ;
- l’absence d’arrondis financiers codés en dur ;
- l’absence de préfixe SQL `llx_` dans le PHP métier ;
- la présence des fichiers racine obligatoires ;
- la protection de l’aperçu privé et l’absence de donnée métier ;
- les six modèles de numérotation dédiés ;
- les garde-fous des clés et du connecteur Géoplateforme ;
- le contrat d’URL racine du répertoire public et l’absence de liens de
  notification contournant le constructeur commun ;
- la présence des guides Sécurité et fournisseurs ;
- l’unicité et la parité des traductions `fr_FR` et `en_US`.

Une passe de lint PHP doit également être exécutée sur tous les fichiers
`*.php`.

Résultats de la passe finale du 27 juillet 2026 :

- lint PHP 8.5.7 : tous les fichiers PHP valides ;
- contrats statiques : `434 contrats validés` ;
- URL publique : racine configurée, liens de pages, lien de notification,
  redirection interne et fallback Dolibarr validés ;
- ressources publiques autonomes : sorties CSS, JavaScript et SVG identiques
  aux sources canoniques du module ;
- catalogues `fr_FR` et `en_US` : parité et unicité validées ;
- clés : deux valeurs de 32 octets acceptées, valeurs identiques et valeur
  courte refusées ;
- Géoplateforme : requête non personnelle `Paris`, réponse GeoJSON et
  coordonnées validées ;
- PHPStan : non exécuté, outil et bootstrap Dolibarr absents de
  l’environnement.

## Recette d’intégration encore requise

- activation, désactivation et réactivation sur Dolibarr v20 ;
- migration et idempotence SQL sur MySQL/MariaDB ;
- PHPStan avec le bootstrap Dolibarr du parc ;
- matrice complète des droits et comptes administrateurs ;
- deux entités Multicompany et documents dans l’entité propriétaire ;
- parcours public avec deux comptes et consentements distincts ;
- domaine public dont la racine documentaire pointe sur `public/` : accueil,
  ressources `/assets/`, navigation, formulaires, redirections et liens
  reçus par courriel ;
- correspondances et réservations concurrentes ;
- Agenda, Notifications et travaux planifiés natifs ;
- génération, aperçu et téléchargement du PDF multipage ;
- envoi SMTP réel et fournisseurs optionnels homologués ;
- clavier, lecteur d’écran, zoom 200 % et largeurs mobiles ;
- vérification des logs Dolibarr après chaque parcours.

Les contrôles non exécutés ne doivent pas être présentés comme validés.
