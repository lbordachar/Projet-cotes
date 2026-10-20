# Analyseur de cotes sportives

Application pedagogique deployee avec Docker Compose, permettant de consulter des matchs en temps reel via une API externe, de sauvegarder l'historique des cotes en base de donnees, de filtrer les championnats et de simuler des mises ainsi que des tickets combines.

Le projet comprend quatre services : un frontend Nginx, un backend PHP-FPM, une base MySQL et une interface phpMyAdmin.

## Membres du groupe
- Lucas Bordachar
- Aloys Lartiguelongue
- Simon Salefranque

## Fonctionnalites
- Recuperation dynamique des matchs et des cotes en temps reel via **The Odds API** (marches `h2h` et `totals`).
- Diversification des types de paris populaires pris en charge :
  - **1N2** : Victoires domicile/exterieur et Matchs nuls (evalues sans distorsion).
  - **Buts** : Plus de 2.5 buts (+2.5) et Moins de 2.5 buts (-2.5).
  - **BTTS** : Les deux equipes marquent (Oui), extrapole selon le profil offensif de la rencontre.
- Synchronisation et persistance automatique dans MySQL (`INSERT ... ON DUPLICATE KEY UPDATE` sur la cle composite ligue/equipes/pari) avec horodatage (`updated_at`) pour constater l'evolution temporelle.
- Mode de secours automatique (*fallback*) sur les donnees MySQL locales si l'API externe est indisponible ou si le quota gratuit est atteint.
- Double strategie de mise simulee :
  - **Budget fixe a repartir** : ventilation ponderee du capital sur un nombre parametrable de paris les plus rentables ($N \ge 1$).
  - **Demi-Kelly** : gestion proportionnelle du capital pour optimiser la croissance du capital sur les opportunites a valeur positive (*Value Bets*).
- Selection automatique en un clic du Top $N$ des paris les plus rentables pour constituer le ticket.
- Selection manuelle de paris pour constituer un ticket combine, avec calcul de la cote globale, de la probabilite estimee et du gain potentiel.
- Indicateur visuel de chargement interactif (spinner) pour accompagner les requetes de synchronisation multi-championnats.

## Architecture

| Service | Technologie | Role | Acces |
| :--- | :--- | :--- | :--- |
| **frontend** | Nginx Alpine | Sert le HTML, les styles, le script client et transmet les appels API | Ports 80 et 8086 du serveur |
| **backend** | PHP 8.2 FPM | Interroge The Odds API (cURL multi-marches) et synchronise MySQL (PDO) | Port 9000 interne |
| **db** | MySQL 8.0 | Stocke les matchs de secours et l'historique complet des cotes API | Port 3306 interne |
| **phpmyadmin** | phpMyAdmin | Permet d'administrer la base et de verifier les mises a jour | Port 8087 local au serveur |

`Navigateur -> Nginx -> PHP-FPM -> The Odds API & MySQL`

Les services communiquent sur le reseau Docker `app_network`. Les donnees MySQL sont conservees dans le volume `db_data`.

## Installation

### Prerequis
- Docker Engine et Docker Compose installes sur le serveur Debian.
- Git pour recuperer les sources.
- Les ports 80 et 8086 disponibles et accessibles depuis le reseau de consultation.
- Un acces aux registres Docker et a Internet (direct ou via le proxy universitaire `cache.univ-pau.fr:3128`) pour interroger l'API externe.
- Une cle d'API pour le service **The Odds API**.

### Recuperer le projet
```bash
git clone [https://github.com/lbordachar/Projet-cotes.git](https://github.com/lbordachar/Projet-cotes.git) projet_api
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
Remplacer les deux mots de passe par des valeurs distinctes et renseigner la cle `ODDS_API_KEY` avant le lancement. La variable `HTTP_PROXY_URL` est optionnelle (a vider si le serveur d'evaluation dispose d'un acces direct au Web sans passer par le proxy academique). Le fichier `.env` est ignore par Git (`.gitignore`) et ne doit pas etre ajoute au depot.

### Demarrer la stack
```bash
docker compose up -d --build
docker compose ps
```
Au premier lancement, MySQL cree la base et le compte applicatif, puis execute `database/init/01-init.sql` pour charger le catalogue initial de matchs. Lors des consultations via l'interface web, le backend interroge l'API externe, calcule la rentabilite et met a jour la base MySQL en temps reel.

L'initialisation de MySQL peut prendre quelques secondes. Le backend et phpMyAdmin attendent que le conteneur `db` soit signale `healthy` avant de demarrer.

Acceder ensuite a l'application :
- Via le nom de domaine : `http://cotes-sportives.eva.univ-pau.fr/`
- Via l'adresse IP directe : `http://10.3.16.141:8086/`

## Choix techniques et securite

### Backend sans privileges root
PHP-FPM fonctionne avec l'utilisateur dedie `appuser` (UID 1000). Le backend ne dispose d'aucun acces au compte `root` du conteneur ni a celui de MySQL.
L'option de securite `no-new-privileges` empeche les processus enfants du backend d'elever leurs privileges par les mecanismes systeme (ex. binaires `setuid`).

### Gestion des secrets et API externe
La cle privee `ODDS_API_KEY` et les identifiants MySQL sont centralises dans le fichier `.env` sur le serveur hote et injectes sous forme de variables d'environnement au conteneur `backend`. Aucun secret n'est expose cote client dans le navigateur.

### Ports exposes
Seul le frontend Nginx est expose publiquement sur le reseau du serveur (port 80 pour le nom de domaine, port 8086 en ecoute alternative).
Les ports de communication internes MySQL (3306) et PHP-FPM (9000) restent strictement confines au reseau bridge Docker.
L'interface d'administration phpMyAdmin est bridee sur l'interface locale `127.0.0.1:8087` et requiert un tunnel SSH securise pour y acceder a distance :
```bash
ssh -N -L 8087:127.0.0.1:8087 root@10.3.16.141
```
Puis se connecter depuis son navigateur sur `http://localhost:8087`.

### Fichiers en lecture seule
Le code source du frontend, la configuration de Nginx ainsi que
