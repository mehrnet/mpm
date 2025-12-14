# Guide d'installation et de configuration

Guide complet pour installer, configurer et commencer avec MPM - Gestionnaire de paquets Mehr.

<!--html-ignore-->
## Table des matières

- [Installation](#installation)
- [Première exécution](#première-exécution)
- [Configuration](#configuration)
- [Commencer](#commencer)
- [Développement de paquets](#développement-de-paquets)
- [Configuration du référentiel](#configuration-du-référentiel)
- [Déploiement](#déploiement)
<!--/html-ignore-->

---

## Installation

### Installation rapide

```bash
# Télécharger mpm.php
wget https://raw.githubusercontent.com/mehrnet/mpm/main/mpm.php

# Définir les permissions
chmod 644 mpm.php

# Tester l'installation
php mpm.php echo "Hello, World!"
```

### Installation manuelle

1. Téléchargez `mpm.php` depuis le référentiel
2. Placez-le à la racine de votre projet ou dans le répertoire du serveur web
3. Assurez-vous que PHP 7.0+ est installé
4. Vérifiez que l'extension `ZipArchive` est activée (pour la gestion des paquets)

### Exigences

- **PHP**: 7.0 ou supérieur
- **Extensions**: `ZipArchive` (pour la gestion des paquets)
- **Permissions**: Accès en lecture/écriture au répertoire du projet

## Première exécution

À la première exécution, MPM génère automatiquement les fichiers de configuration:

```bash
# Exécutez n'importe quelle commande pour initialiser
php mpm.php ls
```

Cela crée:

```
.config/
├── key              # Clé API (64 caractères hexadécimaux)
├── repos.json       # Configuration des miroirs du référentiel
├── packages.json    # Registre des paquets installés
└── path.json        # Modèles de gestionnaire de commandes
```

### Clé API

Votre clé API est affichée au premier accès HTTP ou enregistrée dans `.config/key` à la première exécution CLI:

```bash
# Afficher votre clé API
cat .config/key
```

**Important:** Gardez votre clé API sécurisée. Quiconque y ayant accès peut exécuter des commandes.

## Configuration

### Miroirs du référentiel

Modifiez `.config/repos.json` pour personnaliser les référentiels de paquets:

```json
{
  "main": [
    "https://raw.githubusercontent.com/mehrnet/mpm-repo/refs/heads/main/main"
  ]
}
```

Chaque entrée est une URL complète vers un répertoire de dépôt contenant `database.json` et les fichiers ZIP des paquets. Les miroirs sont essayés séquentiellement en cas d'échec.

### Modèles de gestionnaire de commandes

Modifiez `.config/path.json` pour personnaliser où le shell recherche les commandes personnalisées:

```json
[
  "app/packages/[name]/handler.php",
  "bin/[name].php",
  "custom/handlers/[name].php"
]
```

Le placeholder `[name]` est remplacé par le nom de la commande.

### Configuration spécifique à l'environnement

Pour différents environnements, vous pouvez:

1. **Utiliser des répertoires de configuration différents:**
    ```php
    // Modifier les constantes dans mpm.php (non recommandé)
    const DIR_CONFIG = '.config';
    ```

2. **Symlink de configuration:**
    ```bash
    ln -s .config.production .config
    ```

3. **Variables d'environnement:**
    Accédez via `php mpm.php env list`

## Commencer

### Mode CLI (Recommandé pour le développement)

```bash
# Lister les fichiers
php mpm.php ls

# Lire le contenu des fichiers
php mpm.php cat mpm.php | head -20

# Créer un répertoire
php mpm.php mkdir tmp

# Copier des fichiers
php mpm.php cp mpm.php shell.backup.php

# Gestion des paquets
php mpm.php pkg search database
php mpm.php pkg add users
php mpm.php pkg list
php mpm.php pkg upgrade
```

### Mode HTTP (Production)

1. **Démarrez le serveur de développement PHP (test):**
    ```bash
    php -S localhost:8000
    ```

2. **Obtenez votre clé API:**
    ```bash
    KEY=$(cat .config/key)
    ```

3. **Exécutez les commandes:**
    ```bash
    # Lister les fichiers
    curl "http://localhost:8000/mpm.php/$KEY/ls"

    # Lire un fichier
    curl "http://localhost:8000/mpm.php/$KEY/cat/README.md"

    # Gestion des paquets
    curl "http://localhost:8000/mpm.php/$KEY/pkg/list"
    curl "http://localhost:8000/mpm.php/$KEY/pkg/add/users"
    ```

### Serveur web de production

Pour la production, utilisez un serveur web approprié:

**Nginx:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/shell;
    index mpm.php;

    location / {
        try_files $uri $uri/ /mpm.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index mpm.php;
        include fastcgi_params;
    }
}
```

**Apache (.htaccess):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ mpm.php/$1 [L,QSA]
```

## Développement de paquets

### Créer un paquet

1. **Créer la structure du paquet:**
    ```
    mypackage/
    ├── handler.php       # Obligatoire: point d'entrée
    ├── package.json      # Obligatoire: métadonnées
    ├── lib/             # Optionnel: code de bibliothèque
    └── data/            # Optionnel: fichiers de données
    ```

2. **Écrire handler.php:**
    ```php
    <?php
    return function(array $args): string {
        $action = $args[0] ?? 'help';

        switch ($action) {
            case 'list':
                return "Item 1\nItem 2\nItem 3";

            case 'create':
                $name = $args[1] ?? null;
                if (!$name) {
                    throw new \RuntimeException('Nom requis', 400);
                }
                return "Created: $name";

            default:
                return "Actions: list, create";
        }
    };
    ```

3. **Créer package.json:**
    ```json
    {
        "id": "mypackage",
        "name": "My Package",
        "version": "1.0.0",
        "description": "Description du paquet",
        "author": "Votre nom",
        "license": "MIT",
        "dependencies": []
    }
    ```

### Champs de métadonnées du paquet

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `id` | chaîne | Oui | Minuscules, sans espaces, utilisé dans les URL |
| `name` | chaîne | Oui | Nom lisible |
| `version` | chaîne | Oui | Versioning sémantique (X.Y.Z) |
| `description` | chaîne | Oui | Description brève |
| `author` | chaîne | Non | Auteur du paquet |
| `license` | chaîne | Non | Identifiant de licence (MIT, Apache-2.0, etc.) |
| `dependencies` | tableau | Non | Tableau d'ID de paquets |

### Tester votre paquet

**Mode CLI:**
```bash
# Installer manuellement
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/

# Tester les commandes
php mpm.php mypackage list
php mpm.php mypackage create item-name
```

**Mode HTTP:**
```bash
KEY=$(cat .config/key)
curl "http://localhost:8000/mpm.php/$KEY/mypackage/list"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/create/item-name"
```

### Bonnes pratiques

1. **Validation des entrées:**
    ```php
    $id = $args[0] ?? null;
    if (!$id || !is_numeric($id)) {
        throw new \RuntimeException('ID invalide', 400);
    }
    ```

2. **Gestion des erreurs:**
    ```php
    try {
        $data = json_decode(file_get_contents('data.json'), true);
        if (!$data) {
            throw new \RuntimeException('JSON invalide', 400);
        }
    } catch (\Throwable $e) {
        throw new \RuntimeException("Error: {$e->getMessage()}", 400);
    }
    ```

3. **Sécurité:**
    ```php
    // MAUVAIS: Utilisation directe de l'entrée utilisateur
    $file = $args[0];
    return file_get_contents($file);  // Vulnérable à la traversée de chemin

    // BON: Valider et restreindre les chemins
    $file = basename($args[0] ?? '');  // Supprimer les composants de chemin
    $path = "app/data/$file";
    if (!file_exists($path)) {
        throw new \RuntimeException('Fichier non trouvé', 404);
    }
    return file_get_contents($path);
    ```

## Configuration du référentiel

### Créer un référentiel de paquets

1. **Créer la structure du référentiel:**
    ```
    my-repo/
    └── main/
        ├── database.json
        ├── mypackage-1.0.0.zip
        └── otherapkg-1.0.0.zip
    ```

2. **Construire le ZIP du paquet:**
    ```bash
    cd mypackage
    zip -r ../my-repo/main/mypackage-1.0.0.zip .
    ```

3. **Calculer le checksum:**
    ```bash
    sha256sum my-repo/main/mypackage-1.0.0.zip
    # Output: abc123def456...  mypackage-1.0.0.zip
    ```

4. **Créer database.json:**
    ```json
    {
      "version": 1,
      "packages": {
        "mypackage": {
          "name": "My Package",
          "description": "Description du paquet",
          "versions": {
            "1.0.0": {
              "version": "1.0.0",
              "dependencies": [],
              "checksum": "sha256:abc123def456...",
              "size": 2048,
              "download_url": "mypackage-1.0.0.zip",
              "released_at": "2025-01-15"
            }
          },
          "latest": "1.0.0",
          "author": "Votre nom",
          "license": "MIT"
        }
      }
    }
    ```

5. **Héberger le référentiel:**
    - Télécharger sur GitHub: `https://github.com/user/repo/raw/main/main/`
    - Utiliser un CDN ou un hébergement statique
    - S'assurer que HTTPS est activé

6. **Configurer le shell pour utiliser votre référentiel:**
    Modifiez `.config/repos.json`:
    ```json
    {
      "main": ["https://github.com/user/repo/raw/main/main"]
    }
    ```

### Exemple d'hébergement GitHub

```bash
# Créer un référentiel
mkdir -p my-packages/main
cd my-packages

# Ajouter des paquets
cp /path/to/mypackage-1.0.0.zip main/
cat > main/database.json << 'EOF'
{
  "version": 1,
  "packages": { ... }
}
EOF

# Pousser vers GitHub
git init
git add .
git commit -m "Add packages"
git remote add origin https://github.com/user/my-packages.git
git push -u origin main

# Les utilisateurs configurent: https://github.com/user/my-packages/raw/main/main
```

## Déploiement

### Développement

```bash
# Exécutez le serveur PHP intégré
php -S localhost:8000

# Testez les commandes
php mpm.php pkg list
curl "http://localhost:8000/mpm.php/$(cat .config/key)/pkg/list"
```

### Staging/Production

1. **Utilisez un serveur web approprié** (Nginx, Apache, Caddy)
2. **Activez HTTPS** pour la sécurité de la clé API
3. **Définissez des permissions restrictives:**
    ```bash
    chmod 644 mpm.php
    chmod 700 .config
    chmod 600 .config/key
    ```

4. **Configurez PHP-FPM** pour une meilleure performance
5. **Configurez la surveillance et la journalisation**
6. **Sauvegardez régulièrement** le répertoire `.config/`

### Déploiement Docker

```dockerfile
FROM php:8.1-fpm

# Installer les extensions
RUN docker-php-ext-install zip

# Copier le shell
COPY mpm.php /var/www/html/

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
```

### Renforcement de la sécurité

MPM applique automatiquement ces mesures de sécurité au premier lancement:

1. **Permissions de fichiers sécurisées** - `.config/` défini sur 0700, fichiers sur 0600
2. **.htaccess automatique** - Bloque l'accès web à tous les fichiers cachés
3. **Initialisation localhost** - Clé API affichée uniquement via CLI ou localhost HTTP
4. **En-têtes de sécurité HTTP** - X-Frame-Options, CSP, X-Content-Type-Options
5. **Génération de clé CSPRNG** - Utilise `random_int()` pour la sécurité cryptographique

Recommandations supplémentaires:

1. **Activer HTTPS uniquement** en production
2. **Limitation du taux** au niveau du serveur web
3. **Liste blanche d'IP** pour l'accès admin uniquement
4. **Surveiller les tentatives d'authentification échouées**
5. **Mises à jour de sécurité régulières**

Pour nginx, ajoutez ceci à votre bloc serveur:

```nginx
location ~ /\. {
    deny all;
}
```

## Dépannage

### Problèmes courants

**Erreurs "Autorisation refusée":**
```bash
# Corriger les permissions
chmod 755 .
chmod 644 mpm.php
chmod -R 755 .config
```

**"ZipArchive non trouvé":**
```bash
# Installer l'extension
sudo apt-get install php-zip  # Debian/Ubuntu
sudo yum install php-zip       # CentOS/RHEL
```

**"Clé API non trouvée" (mode CLI):**
```bash
# Générer la clé manuellement
php mpm.php ls  # Cela crée .config/key
```

**Problèmes de fichier de verrou:**
```bash
# Forcer le déverrouillage s'il est bloqué
php mpm.php pkg unlock
```

<!--html-ignore-->
## Étapes suivantes

- Lire la [Référence d'utilisation](USAGE_FR.md) pour la documentation complète des commandes
- Voir [Développement de paquets](PACKAGES_FR.md) pour la création avancée de paquets
- Consulter [les paquets d'exemple](https://github.com/mehrnet/mpm-packages)

---

Pour les questions ou les problèmes, veuillez visiter: https://github.com/mehrnet/mpm
<!--/html-ignore-->
