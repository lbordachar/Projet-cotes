# Analyseur de cotes sportives

Application pedagogique deployee avec Docker Compose, permettant de consulter des matchs en temps reel via une API externe, de sauvegarder l'historique des cotes en base de donnees, de filtrer les championnats et de simuler des mises ainsi que des tickets combines.

Le projet comprend quatre services : un frontend Nginx, un backend PHP-FPM, une base MySQL et une interface phpMyAdmin.

## Membres du groupe
- Lucas Bordachar
- Aloys Lartiguelongue
- Simon Salefranque

## Fonctionnalites
- Recuperation dynamique des matchs et des cotes en temps reel via **The Odds API**.
- Synchronisation et persistance automatique des donnees recuperees dans MySQL (`INSERT ... ON DUPLICATE KEY UPDATE`) avec horodatage (`updated_at`) pour suivre l'evolution dans le temps.
- Mode de secours automatique (fallback) sur les donnees MySQL locales si l'API externe est indisponible ou si le quota de requetes est atteint.
- Filtrage par championnat (Ligue des Champions, La Liga, Premier League, Ligue 1, Serie A).
- Simulation de mises optimales avec la formule de demi-Kelly.
- Selection de matchs pour constituer un ticket combine, avec calcul de la cote globale et du gain potentiel.

## Architecture

| Service | Technologie | Role | Acces |
| :--- | :--- | :--- | :--- |
| **frontend** | Nginx Alpine | Sert le HTML et transmet les appels API | Ports 80 et 8086 du serveur |
| **backend** | PHP 8.2 FPM | Interroge The Odds API (cURL) et synchronise MySQL (PDO) | Port 9000 interne |
| **db** | MySQL 8.0 | Stocke les matchs de secours et l'historique des cotes API | Port 3306 interne |
| **phpmyadmin** | phpMyAdmin | Permet d'administrer la base et de verifier les mises a jour | Port 8087 local au serveur |

`Navigateur -> Nginx -> PHP-FPM -> The Odds API & MySQL`

Les services communiquent sur le reseau Docker `app_network`. Les donnees MySQL sont conservees dans le volume `db_data`.

## Installation

### Prerequis
- Docker Engine et Docker Compose installes sur le serveur Debian.
- Git pour recuperer les sources.
- Les ports 80 et 8086 disponibles et accessibles depuis le reseau de consultation.
- Un acces aux registres Docker et a Internet (direct ou via proxy) pour interroger l'API externe.
- Une cle d'API valide pour le service **The Odds API**.

### Recuperer le projet
```bash
git clone <URL_DU_DEPOT_GIT> projet_api
cd projet_api
```

### Configurer les variables
Pour une nouvelle installation :
```bash
cp .env.example .env
nano .env
```

Le fichier `.env` doit contenir :
```env
MYSQL_ROOT_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_ROOT
MYSQL_DATABASE=sports_stats
MYSQL_USER=analyste_user
MYSQL_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_APPLICATION
ODDS_API_KEY=REMPLACER_PAR_VOTRE_CLE_THE_ODDS_API
HTTP_PROXY_URL=[http://cache.univ-pau.fr:3128](http://cache.univ-pau.fr:3128)
```
Remplacer les deux mots de passe par des valeurs distinctes et renseigner la cle `ODDS_API_KEY` avant le lancement. La variable `HTTP_PROXY_URL` est optionnelle et peut etre laissee vide si le serveur n'utilise pas le proxy de l'universite. Le fichier `.env` ne doit pas etre ajoute au depot Git.

### Demarrer la stack
```bash
docker compose up -d --build
docker compose ps
```
Au premier lancement, MySQL cree la base et le compte applicatif, puis execute `database/init/01-init.sql` pour charger les 46 matchs de base. Lors des consultations sur l'interface, le backend interroge l'API externe et met a jour la base MySQL en temps reel.

L'initialisation peut prendre quelques instants. Le backend et phpMyAdmin attendent que `db` soit `healthy` avant de demarrer.

Ouvrir ensuite le site via le nom de domaine declare ou via l'IP :
- `http://NOM_DE_DOMAINE/`
- `http://IP_DU_SERVEUR:8086/`

## Choix techniques et securite

### Backend sans privileges root
PHP-FPM fonctionne avec `appuser`, de UID 1000. Le backend ne recoit pas le mot de passe root de MySQL.
L'option `no-new-privileges` empeche les processus du backend d'obtenir des privileges supplementaires par les mecanismes du systeme, par exemple un executable setuid.

### Gestion des secrets et API externe
La cle d'API externe (`ODDS_API_KEY`) et les identifiants MySQL sont stockes exclusivement dans le fichier `.env` sur le serveur et injectes en variables d'environnement dans le conteneur `backend`. La cle n'est jamais exposee cote client dans le code JavaScript.

### Ports exposes
Seul le frontend est accessible directement sur le reseau du serveur (ports 80 et 8086).
Les ports MySQL et PHP-FPM ne sont pas publies.
phpMyAdmin est bride sur l'interface de bouclage locale (`127.0.0.1:8087`) et est accessible a distance uniquement par un tunnel SSH :
```bash
ssh -N -L 8087:127.0.0.1:8087 utilisateur@IP_DU_SERVEUR
```
Puis ouvrir `http://localhost:8087`.

### Fichiers en lecture seule
Les fichiers du frontend, la configuration Nginx et les scripts SQL d'initialisation sont montes avec l'option `ro` (read-only). Les conteneurs peuvent les lire sans pouvoir les modifier par ces montages.
Le volume MySQL `db_data` reste accessible en ecriture pour enregistrer les nouvelles cotes issues de l'API.

### Configuration Nginx et PHP
Nginx transmet uniquement `/api.php` au backend. Les autres chemins PHP et les fichiers caches sont bloques.
PHP utilise sa configuration de production pour eviter d'afficher les details des erreurs aux visiteurs. Les versions PHP et Nginx sont masquees dans les en-tetes HTTP.

### Organisation du Dockerfile
L'image utilise la base officielle `php:8.2-fpm`. Les paquets `curl` et `ca-certificates` sont installes pour garantir les appels HTTPS vers l'API externe, ainsi que le pilote `pdo_mysql` pour la communication avec la base de donnees. Le cache APT est nettoye dans la meme couche `RUN` pour reduire le poids de l'image.
L'installation des dependances precede la copie de `api.php`. Cet ordre permet de reutiliser le cache de construction lorsque seul le code change.
Le code est integre dans l'image avec `COPY --chown=appuser:appgroup`, puis PHP est execute avec `appuser`.
`EXPOSE 9000` documente le port FastCGI sans le publier sur le serveur.

### Simplicite du deploiement
La stack conserve quatre services orchestres automatiquement.
Des versions detaillees et commentees des fichiers de configuration (`docker-compose-commentaire.yml` et `backend/Dockerfile-commentaire`) sont egalement presentes dans le depot pour documenter chaque directive sans alourdir les fichiers de production.
Les variables obligatoires sont verifiees avant le lancement avec la syntaxe `${VARIABLE:?message}`. Les services utilisent `restart: unless-stopped` pour redemarrer apres un arret inattendu ou un redemarrage du demon Docker.

## Limites de la simulation
- Le calcul de probabilite ajoute un bonus deterministe (`crc32`) a la probabilite implicite de la cote issue du bookmaker afin de simuler un avantage statistique (Value Bet) pour la formule de demi-Kelly.
- Le champ `indice_rentabilite` correspond a la cote multipliee par la probabilite estimee, et non au ROI net.
- La probabilite combinee est calculee en multipliant les probabilites des selections, ce qui suppose l'independance des evenements.
- Le retour brut potentiel inclut la mise initiale.
