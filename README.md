# README - Telelec-Transfert

## 📋 Sommaire
1. [Introduction](#introduction)
2. [Fonctionnalités](#fonctionnalités)
3. [Prérequis](#prérequis)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Utilisation](#utilisation)
7. [Sécurité](#sécurité)
8. [Maintenance](#maintenance)
9. [Dépannage](#dépannage)

## 📝 Introduction

**Telelec-Transfert** est une solution interne développée pour Telelec permettant de transférer des fichiers volumineux de manière sécurisée. Cette application offre une alternative aux services tiers comme WeTransfer, en garantissant la confidentialité des données et un contrôle total sur les fichiers partagés.

Ce système permet l'envoi de fichiers jusqu'à 50 Go avec authentification à deux facteurs et suivi complet des téléchargements.

## ✨ Fonctionnalités

- **Upload de fichiers volumineux** (jusqu'à 50 Go)
- **Authentification à deux facteurs (A2F)** pour les téléchargements
- **Traçabilité complète** des téléchargements (adresse IP, localisation, date, appareil)
- **Interface d'administration** pour la gestion des fichiers
- **Tableau de bord** avec statistiques en temps réel
- **Système de logs** pour surveiller l'activité et les tentatives non autorisées
- **Génération de codes uniques** pour le partage de fichiers
- **Interface utilisateur intuitive** et responsive
- **Sécurisation des données** et cryptage des communications

## 🔧 Prérequis

- Docker et Docker Compose
- Un serveur web avec accès SSH
- Au moins 2 Go de RAM disponibles
- Espace disque adapté aux fichiers à stocker

## 🚀 Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/votre-organisation/telelec-transfert.git
cd telelec-transfert
```

### 2. Configurer les variables d'environnement

Créez un fichier `.env` à la racine du projet basé sur le modèle `.env.example` :

```bash
cp .env.example .env
nano .env
```

Modifiez les variables suivantes :
```
DB_HOST=db
DB_NAME=telelec
DB_USER=telelecuser
DB_PASSWORD=votre_mot_de_passe_securise
ADMIN_USERNAME=votre_nom_utilisateur_admin
ADMIN_PASSWORD=votre_mot_de_passe_admin
UPLOAD_MAX_SIZE=51200M  # Taille maximale d'upload en Mo (50 Go)
```

### 3. Construire et démarrer les conteneurs Docker

```bash
docker-compose up -d
```

### 4. Initialiser la base de données

```bash
docker-compose exec db mysql -u root -p

# Dans l'invite MySQL, exécutez :
source /docker-entrypoint-initdb.d/init.sql
exit
```

### 5. Configurer le compte administrateur

```bash
docker-compose exec app php ./scripts/create-admin.php
```

## ⚙️ Configuration

### Base de données

Les paramètres de connexion à la base de données sont configurés dans plusieurs fichiers :

- `/secure/config.php` - Configuration principale (créé automatiquement)
- `/admin/login.php` - Connexion à la base pour l'administration

Assurez-vous que ces fichiers contiennent les identifiants corrects :

```php
$host = 'db';  // Nom du service dans docker-compose
$db = 'telelec';
$user = 'telelecuser';
$pass = 'votre_mot_de_passe_securise';
```

### Limites d'upload

La taille maximale d'upload est configurée dans :
- `php.ini` - `upload_max_filesize` et `post_max_size`
- `.htaccess` pour Apache

### Paramètres d'expiration

Dans `/Transfert/finalize-upload.php`, vous pouvez modifier la durée d'expiration des liens :

```php
$expirationDate = date('Y-m-d H:i:s', strtotime('+5 days'));  // Par défaut 5 jours
```

## 🖱️ Utilisation

### Envoi de fichiers

1. Accédez à l'application via `https://votre-domaine.fr`
2. Cliquez sur "Envoyer" dans le menu
3. Déposez votre fichier ou utilisez le sélecteur de fichiers
4. Attendez la fin de l'upload
5. Récupérez le lien de téléchargement et le code A2F
6. Partagez le lien et le code A2F au destinataire (via deux canaux différents)

### Administration

1. Accédez à `https://votre-domaine.fr/admin/login.php`
2. Connectez-vous avec les identifiants administrateur
3. Gérez les fichiers (suppression, modification, historique)
4. Consultez les statistiques et logs d'activité

## 🔐 Sécurité

- L'authentification à deux facteurs est activée par défaut
- Les mots de passe sont hachés avec l'algorithme bcrypt
- Les sessions sont protégées contre la fixation et le vol
- L'accès à l'administration est limité
- La géolocalisation IP permet de détecter les accès suspects
- Les fichiers sont stockés dans un répertoire sécurisé hors de la racine web

**Important :** Changez régulièrement le mot de passe administrateur et surveillez les logs pour détecter toute activité suspecte.

## 🛠️ Maintenance

### Nettoyage automatique

Un système de nettoyage automatique supprime les fichiers expirés. Vous pouvez le configurer en tant que tâche cron :

```bash
# Exemple de configuration cron (exécution quotidienne à 2h du matin)
0 2 * * * docker-compose exec app php /var/www/html/clean_database.php > /dev/null 2>&1
```

### Sauvegarde

Pour sauvegarder la base de données et les fichiers :

```bash
# Base de données
docker-compose exec db mysqldump -u telelecuser -p telelec > backup/telelec_$(date +%Y%m%d).sql

# Fichiers uploadés
tar -czf backup/uploads_$(date +%Y%m%d).tar.gz uploads/
```

## 🔧 Dépannage

### Problèmes d'upload
- Vérifiez les limites dans `php.ini` et `.htaccess`
- Contrôlez les permissions du dossier `uploads/`
- Consultez les logs PHP et Apache

### Erreurs de base de données
- Vérifiez la connexion à la base avec `docker-compose exec db mysql -u telelecuser -p telelec`
- Assurez-vous que toutes les tables existent
- Contrôlez les logs MySQL

### Problèmes de téléchargement
- Vérifiez si les codes A2F sont correctement générés
- Contrôlez les paramètres d'expiration
- Examinez les logs d'accès pour détecter d'éventuels problèmes

---

Développé par Telelec © 2025
