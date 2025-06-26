# Telelec-Transfert

Solution interne de transfert de fichiers sécurisée développée pour Telelec. Cette application permet un partage de fichiers sécurisé avec authentification à deux facteurs, interface d'administration complète, et système de logs avancé.

## 🚀 Fonctionnalités

### Transfert de Fichiers
- **Upload sécurisé** : Interface drag & drop intuitive
- **Validation avancée** : Vérification des types de fichiers et tailles
- **Scan antivirus** : Protection contre les fichiers malveillants
- **Chiffrement** : Stockage sécurisé des fichiers
- **Liens temporaires** : Génération de liens de téléchargement avec expiration

### Sécurité
- **Authentification 2FA** : Double authentification par email
- **Contrôle d'accès** : Système de permissions granulaire
- **Monitoring** : Surveillance en temps réel des activités
- **Logs complets** : Traçabilité de toutes les actions
- **Protection CSRF** : Sécurisation des formulaires

### Administration
- **Dashboard complet** : Vue d'ensemble des activités
- **Gestion des utilisateurs** : Création, modification, suppression
- **Analyse des logs** : Interface de consultation des journaux
- **Statistiques** : Métriques de performance et d'utilisation
- **Configuration** : Paramétrage centralisé

## 🏗️ Architecture Technique

### Stack Technologique
- **Backend** : PHP 8.1+ avec PDO
- **Base de données** : MySQL 8.0
- **Frontend** : HTML5, CSS3, JavaScript vanilla
- **Conteneurisation** : Docker & Docker Compose
- **Serveur web** : Apache 2.4

### Structure des Dossiers
```
telelec-transfert/
├── admin/              # Interface d'administration
├── css/               # Feuilles de style
├── js/                # Scripts JavaScript
├── uploads/           # Stockage temporaire des fichiers
├── secure/            # Zone sécurisée (fichiers traités)
├── docker-compose.yml # Configuration Docker
├── Dockerfile         # Image Docker personnalisée
└── *.php             # Pages principales de l'application
```

### Base de Données
- **users** : Comptes utilisateurs et administrateurs
- **files** : Métadonnées des fichiers uploadés
- **file_logs** : Journal des activités et erreurs
- **sessions** : Gestion des sessions utilisateurs
- **verification_codes** : Codes 2FA temporaires

## 📦 Installation et Déploiement

### Prérequis
- Docker 20.10+
- Docker Compose 2.0+
- Port 8080 disponible

### 1. Cloner le projet
```bash
git clone <repository-url>
cd telelec-transfert
```

### 2. Créer l'environnement Docker
```bash
# Construire et lancer les conteneurs
docker-compose up -d --build

# Vérifier le statut
docker-compose ps
```

### 3. Vérifier l'installation
- **Application** : http://localhost:8080
- **Administration** : http://localhost:8080/admin
- **Base de données** : Accessible via port 3306

### 4. Configuration initiale

#### Base de données
La base de données s'initialise automatiquement au premier démarrage via Docker. Les tables sont créées avec la structure suivante :

- Table `users` pour les comptes (admin créé par défaut)
- Table `files` pour les métadonnées des fichiers
- Table `file_logs` pour les journaux d'activité
- Table `sessions` pour la gestion des sessions
- Table `verification_codes` pour l'authentification 2FA

#### Compte administrateur
Un compte administrateur par défaut est créé dans la base de données :
- **Identifiant** : admin
- **Mot de passe** : Configuré directement dans la base

#### Configuration email (2FA)
Pour activer l'authentification à deux facteurs, configurez les paramètres SMTP dans les fichiers PHP concernés :
- Serveur SMTP
- Port (587 recommandé)
- Authentification
- Chiffrement TLS

### 5. Configuration avancée

#### Paramètres de sécurité
Les paramètres de connexion à la base de données sont configurés dans :
- Fichiers de l'interface admin (`/admin/*.php`)
- Configuration Docker Compose pour l'environnement

#### Limites de fichiers
Modifiez dans `php.ini` ou la configuration Docker :
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

## 🔧 Configuration

### Variables d'environnement
Configuration via Docker Compose :
```yaml
environment:
  MYSQL_ROOT_PASSWORD: rootpassword
  MYSQL_DATABASE: telelec_transfert
  MYSQL_USER: telelec_user
  MYSQL_PASSWORD: telelec_password
```

### Sécurité
- Changez les mots de passe par défaut
- Configurez HTTPS en production
- Activez les logs d'audit
- Configurez les sauvegardes automatiques

## 📊 Utilisation

### Interface Utilisateur
1. **Accès** : http://localhost:8080
2. **Upload** : Glissez-déposez vos fichiers
3. **Validation** : Vérification automatique des fichiers
4. **Partage** : Récupération du lien de téléchargement

### Interface Admin
1. **Connexion** : http://localhost:8080/admin
2. **Dashboard** : Vue d'ensemble des activités
3. **Logs** : Consultation des journaux
4. **Gestion** : Administration des utilisateurs et fichiers

## 🛡️ Sécurité

### Mesures Implémentées
- Validation stricte des types de fichiers
- Scan antivirus automatique
- Authentification à deux facteurs
- Chiffrement des données sensibles
- Protection contre les attaques XSS/CSRF
- Logs détaillés de toutes les activités
- Gestion des sessions sécurisée

### Bonnes Pratiques
- Changement régulier des mots de passe
- Surveillance des logs
- Mise à jour régulière des dépendances
- Sauvegarde des données critiques

## 📈 Monitoring et Logs

### Types de Logs
- **Activités utilisateur** : Uploads, téléchargements
- **Erreurs système** : Échecs de traitement
- **Sécurité** : Tentatives d'intrusion, connexions admin
- **Performance** : Temps de traitement, utilisation

### Dashboard Admin
- Statistiques en temps réel
- Alertes de sécurité
- Métriques de performance
- Gestion des utilisateurs

## 🔄 Maintenance

### Commandes Docker Utiles
```bash
# Arrêter les services
docker-compose down

# Reconstruire après modifications
docker-compose up -d --build

# Consulter les logs
docker-compose logs -f

# Accès au conteneur
docker-compose exec app bash
```

### Sauvegarde
```bash
# Sauvegarde de la base de données
docker-compose exec mysql mysqldump -u root -p telelec_transfert > backup.sql

# Sauvegarde des fichiers
tar -czf uploads_backup.tar.gz uploads/ secure/
```

## 🤝 Support et Développement

### Structure de Développement
- Code modulaire et commenté
- Séparation des responsabilités
- Gestion d'erreurs centralisée
- Architecture extensible

### Contribution
- Respect des standards de codage PHP
- Tests des nouvelles fonctionnalités
- Documentation des modifications
- Validation sécuritaire

---

**Telelec-Transfert** - Solution de transfert de fichiers sécurisée
Développé pour un usage interne chez Telelec. Ne pas redistribuer sans autorisation.
