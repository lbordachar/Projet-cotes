# Analyseur de cotes sportives

Application pedagogique deployee avec Docker Compose, permettant de consulter des matchs, de filtrer les championnats et de simuler des mises et des tickets combines.

Le projet comprend quatre services : un frontend Nginx, un backend PHP-FPM, une base MySQL et une interface phpMyAdmin.

## Membres du groupe

- Lucas Bordachar
- Aloys Lartiguelongue
- Simon Salefranque

## Fonctionnalites

- Consultation des matchs et des cotes depuis MySQL.
- Filtrage par championnat.
- Simulation de mises avec la formule de demi-Kelly.
- Selection de matchs pour constituer un ticket combine.
- Calcul de la cote combinee et du retour brut potentiel.

Les matchs, cotes et probabilites sont des donnees de demonstration stockees dans la base. Aucune API externe ni aucun compte fournisseur ne sont necessaires.

## Architecture

| Service | Technologie | Role | Acces |
| --- | --- | --- | --- |
| frontend | Nginx Alpine | Sert le HTML et transmet les appels API | Port 8085 du serveur |
| backend | PHP 8.2 FPM | Execute api.php et interroge MySQL avec PDO | Port 9000 interne |
| db | MySQL 8.0 | Stocke les matchs et leurs cotes | Port 3306 interne |
| phpmyadmin | phpMyAdmin | Permet d'administrer la base | Port 8084 local au serveur |

```text
Navigateur -> Nginx -> PHP-FPM -> MySQL
```

Les services communiquent sur le reseau Docker app_network. Les donnees MySQL sont conservees dans le volume db_data.

## Installation

### Prerequis

- Docker Engine et Docker Compose installes sur le serveur Debian.
- Git pour recuperer les sources.
- Le port 8085 disponible et accessible depuis le reseau de consultation.
- Un acces aux registres Docker pour recuperer les images.

### Recuperer le projet

```bash
git clone https://github.com/lbordachar/Projet-cotes.git projet2
cd projet2
```

### Configurer les variables

Pour une nouvelle installation :

```bash
cp .env.example .env
nano .env
```

Le fichier .env doit contenir :

```dotenv
MYSQL_ROOT_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_ROOT
MYSQL_DATABASE=sports_stats
MYSQL_USER=analyste_user
MYSQL_PASSWORD=REMPLACER_PAR_UN_MOT_DE_PASSE_APPLICATION
```

Remplacer les deux mots de passe par des valeurs distinctes avant le lancement. Le fichier .env ne doit pas etre ajoute au depot Git.

### Demarrer la stack

```bash
docker compose up -d --build
docker compose ps
```

Au premier lancement, MySQL cree la base et le compte applicatif, puis execute database/init/01-init.sql pour charger les 46 matchs de demonstration.

L'initialisation peut prendre plusieurs minutes. Le backend et phpMyAdmin attendent que db soit healthy avant de demarrer.

Ouvrir ensuite le site :

```text
http://IP_DU_SERVEUR:8085/
```

## Choix techniques et securite

### Backend sans privileges root

PHP-FPM fonctionne avec appuser, de UID 1000. Le backend ne recoit pas le mot de passe root de MySQL.

L'option no-new-privileges empeche les processus du backend d'obtenir des privileges supplementaires par les mecanismes du systeme, par exemple un executable setuid.

### Ports exposes

Seul le frontend est accessible directement sur le reseau du serveur.

Les ports MySQL et PHP-FPM ne sont pas publies. phpMyAdmin est accessible a distance par un tunnel SSH.

phpMyAdmin est accessible a distance par un tunnel SSH (`ssh -N -L 8084:127.0.0.1:8084 utilisateur@IP_DU_SERVEUR` puis `http://localhost:8084`).

### Fichiers en lecture seule

Les fichiers du frontend, la configuration Nginx et les scripts SQL sont montes avec l'option ro. Les conteneurs peuvent les lire sans pouvoir les modifier par ces montages.

Le volume MySQL reste accessible en ecriture pour enregistrer les donnees.

### Configuration Nginx et PHP

Nginx transmet uniquement /api.php au backend. Les autres chemins PHP et les fichiers caches sont bloques.

PHP utilise sa configuration de production pour eviter d'afficher les details des erreurs aux visiteurs. Les versions PHP et Nginx sont masquees dans les en-tetes HTTP.

### Organisation du Dockerfile

L'image utilise la base officielle php:8.2-fpm. PDO etant deja disponible, seul le pilote pdo_mysql est installe.

L'installation des dependances precede la copie de api.php. Cet ordre permet de reutiliser le cache de construction lorsque seul le code change.

Le code est integre dans l'image avec COPY, puis PHP est execute avec appuser.

EXPOSE 9000 documente le port FastCGI sans le publier sur le serveur. La commande de lancement PHP-FPM est heritee de l'image officielle.

### Simplicite du deploiement

La stack conserve quatre services. Aucun service externe n'est necessaire pour la demonstration.

Des versions detaillees et commentees des fichiers de configuration (`docker-compose-commentaire.yml` et `backend/Dockerfile-commentaire`) sont egalement presentes dans le depot pour documenter chaque directive.

Les variables obligatoires sont verifiees avant le lancement. Les services utilisent restart: unless-stopped pour redemarrer apres un arret inattendu ou un redemarrage de Docker, sauf arret volontaire du conteneur.

## Limites de la simulation

Les donnees sont des exemples et ne sont pas actualisees automatiquement.

Le champ indice_rentabilite correspond a la cote multipliee par la probabilite, et non au ROI net.

La probabilite combinee est calculee en multipliant les probabilites des selections, ce qui suppose l'independance des evenements.

Le retour brut potentiel inclut la mise initiale.
