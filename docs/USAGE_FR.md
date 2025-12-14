# Référence d'utilisation

Référence de commande complète et documentation de l'API pour MPM - Gestionnaire de paquets Mehr.

<!--html-ignore-->
## Table des matières

- [Formats de requête](#formats-de-requête)
- [Commandes intégrées](#commandes-intégrées)
- [Gestionnaire de paquets](#gestionnaire-de-paquets)
- [Protocole de réponse](#protocole-de-réponse)
- [Fichiers de configuration](#fichiers-de-configuration)
- [Commandes personnalisées](#commandes-personnalisées)
- [Architecture](#architecture)
- [Performance](#performance)
- [Dépannage](#dépannage)
<!--/html-ignore-->

---

## Formats de requête

### Mode HTTP

**Format:**
```
GET /mpm.php/API_KEY/COMMAND/ARG0/ARG1/ARG2/...
```

**Exemples:**
```bash
# Obtenir la clé API
KEY=$(cat .config/key)

# Lister les fichiers
curl "http://localhost/mpm.php/$KEY/ls"

# Lire un fichier
curl "http://localhost/mpm.php/$KEY/cat/README.md"

# Afficher du texte avec plusieurs arguments
curl "http://localhost/mpm.php/$KEY/echo/hello/world"

# Gestion des paquets
curl "http://localhost/mpm.php/$KEY/pkg/search/database"
curl "http://localhost/mpm.php/$KEY/pkg/add/users"
```

**Réponse:**
- Succès: HTTP 200 avec réponse en texte brut
- Erreur: HTTP 4xx/5xx avec message d'erreur

### Mode CLI

**Format:**
```bash
php mpm.php COMMAND ARG0 ARG1 ARG2 ...
```

**Exemples:**
```bash
# Lister les fichiers
php mpm.php ls

# Lire un fichier
php mpm.php cat README.md

# Afficher du texte avec plusieurs arguments
php mpm.php echo hello world

# Gestion des paquets
php mpm.php pkg search database
php mpm.php pkg add users
```

**Réponse:**
- Succès: Sortie vers STDOUT, code de sortie 0
- Erreur: Sortie vers STDERR avec préfixe "Error: ", code de sortie 1

---

## Commandes intégrées

### ls [path]

Lister le contenu d'un répertoire.

**Arguments:**
- `path` (optionnel): Répertoire à lister (par défaut: répertoire courant)

**Exemples:**
```bash
# CLI
php mpm.php ls
php mpm.php ls app
php mpm.php ls app/packages

# HTTP
curl "http://localhost/mpm.php/$KEY/ls"
curl "http://localhost/mpm.php/$KEY/ls/app"
```

**Sortie:**
Noms de fichiers séparés par des espaces (exclut `.` et `..`)

**Erreurs:**
- 404: Répertoire non trouvé
- 400: Impossible de lire le répertoire
- 403: Validation du chemin échouée (chemin absolu ou `..`)

---

### cat <file>

Lire le contenu d'un fichier.

**Arguments:**
- `file` (obligatoire): Chemin du fichier

**Exemples:**
```bash
# CLI
php mpm.php cat README.md
php mpm.php cat .config/key

# HTTP
curl "http://localhost/mpm.php/$KEY/cat/README.md"
```

**Sortie:**
Contenu du fichier (préserve la mise en forme et les sauts de ligne)

**Erreurs:**
- 400: Argument fichier manquant ou ce n'est pas un fichier
- 404: Fichier non trouvé
- 403: Validation du chemin échouée

---

### rm <file>

Supprimer un fichier.

**Arguments:**
- `file` (obligatoire): Chemin du fichier

**Exemples:**
```bash
# CLI
php mpm.php rm temp.txt
php mpm.php rm logs/old.log

# HTTP
curl "http://localhost/mpm.php/$KEY/rm/temp.txt"
```

**Sortie:**
Chaîne vide en cas de succès (comportement POSIX)

**Erreurs:**
- 400: Argument fichier manquant, ce n'est pas un fichier ou impossible de supprimer
- 404: Fichier non trouvé
- 403: Validation du chemin échouée

---

### mkdir <path>

Créer un répertoire (récursivement).

**Arguments:**
- `path` (obligatoire): Chemin du répertoire à créer

**Exemples:**
```bash
# CLI
php mpm.php mkdir uploads
php mpm.php mkdir app/data/cache

# HTTP
curl "http://localhost/mpm.php/$KEY/mkdir/uploads"
```

**Sortie:**
Chaîne vide en cas de succès

**Erreurs:**
- 400: Argument chemin manquant, existe déjà ou impossible de créer
- 403: Validation du chemin échouée

---

### cp <src> <dst>

Copier un fichier.

**Arguments:**
- `src` (obligatoire): Chemin du fichier source
- `dst` (obligatoire): Chemin du fichier destination

**Exemples:**
```bash
# CLI
php mpm.php cp config.json config.backup.json
php mpm.php cp README.md docs/README.md

# HTTP
curl "http://localhost/mpm.php/$KEY/cp/config.json/config.backup.json"
```

**Sortie:**
Chaîne vide en cas de succès

**Erreurs:**
- 400: Arguments manquants, source ce n'est pas un fichier ou impossible de copier
- 404: Fichier source non trouvé
- 403: Validation du chemin échouée

---

### echo <text>...

Afficher des arguments de texte.

**Arguments:**
- `text...` (obligatoire): Un ou plusieurs arguments de texte

**Exemples:**
```bash
# CLI
php mpm.php echo hello
php mpm.php echo hello world "from shell"

# HTTP
curl "http://localhost/mpm.php/$KEY/echo/hello"
curl "http://localhost/mpm.php/$KEY/echo/hello/world"
```

**Sortie:**
Arguments séparés par des espaces

---

### env [action] [name]

Gérer les variables d'environnement.

**Actions:**
- `list` (par défaut): Lister toutes les variables d'environnement
- `get <name>`: Obtenir la valeur d'une variable spécifique

**Exemples:**
```bash
# CLI
php mpm.php env list
php mpm.php env get PATH
php mpm.php env get HOME

# HTTP
curl "http://localhost/mpm.php/$KEY/env/list"
curl "http://localhost/mpm.php/$KEY/env/get/PATH"
```

**Sortie:**
- `list`: Une variable par ligne (`KEY=value`)
- `get`: Valeur de la variable uniquement

**Erreurs:**
- 404: Variable non trouvée ou action inconnue
- 400: Nom de variable manquant pour `get`

---

## Gestionnaire de paquets

### pkg add [PACKAGE...]

Installer des paquets avec résolution automatique des dépendances.

**Arguments:**
- `PACKAGE...` (obligatoire): Un ou plusieurs noms de paquets

**Exemples:**
```bash
# CLI
php mpm.php pkg add users
php mpm.php pkg add users auth database

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/add/users"
curl "http://localhost/mpm.php/$KEY/pkg/add/users/auth"
```

**Processus:**
1. Récupérer la base de données du référentiel
2. Résoudre les dépendances (tri topologique DFS)
3. Télécharger tous les paquets avec vérification de checksum
4. Extraire tous les paquets vers la racine du projet
5. Enregistrer les paquets de manière atomique

**Sortie:**
```
Paquets à installer: dependency1, dependency2, users

Téléchargement des paquets...
Tous les paquets ont été téléchargés et vérifiés

Extraction des paquets...
Extrait dependency1 (1.0.0)
Extrait dependency2 (2.0.0)
Extrait users (3.0.0)

Enregistrement des paquets...

Installation réussie de 3 paquet(s): dependency1, dependency2, users
```

**Erreurs:**
- 404: Paquet non trouvé
- 400: Dépendance circulaire détectée
- 503: Tous les miroirs ont échoué ou verrou maintenu

---

### pkg del <PACKAGE>

Supprimer un paquet (échoue si d'autres paquets en dépendent).

**Arguments:**
- `PACKAGE` (obligatoire): Nom du paquet

**Exemples:**
```bash
# CLI
php mpm.php pkg del users

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/del/users"
```

**Sortie:**
```
Paquet supprimé: users (version 1.0.0) - 15 fichiers supprimés, 3 répertoires vides supprimés
```

**Erreurs:**
- 404: Paquet non installé
- 400: Paquet requis par d'autres paquets
- 503: Verrou maintenu

---

### pkg upgrade [PACKAGE]

Mettre à jour tous les paquets ou un paquet spécifique vers la dernière version.

**Arguments:**
- `PACKAGE` (optionnel): Nom du paquet (si omis, met à jour tous)

**Exemples:**
```bash
# CLI
php mpm.php pkg upgrade           # Mettre à jour tous
php mpm.php pkg upgrade users     # Mettre à jour spécifique

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/upgrade"
curl "http://localhost/mpm.php/$KEY/pkg/upgrade/users"
```

**Sortie:**
```
Mise à jour de users de 1.0.0 vers 1.1.0

Téléchargement des mises à jour de paquets...
Toutes les mises à jour ont été téléchargées et vérifiées

Extraction des mises à jour de paquets...
Extrait users mise à jour vers 1.1.0

Enregistrement des mises à jour...

Mise à jour réussie de 1 paquet(s): users
```

---

### pkg update

Actualiser le cache du référentiel (récupérer la dernière base de données des paquets).

**Exemples:**
```bash
# CLI
php mpm.php pkg update

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/update"
```

**Sortie:**
```
Cache du référentiel actualisé - 42 paquets disponibles
```

---

### pkg list [FILTER]

Lister les paquets installés.

**Arguments:**
- `FILTER` (optionnel): Filtrer par nom de paquet (insensible à la casse)

**Exemples:**
```bash
# CLI
php mpm.php pkg list
php mpm.php pkg list auth

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/list"
curl "http://localhost/mpm.php/$KEY/pkg/list/auth"
```

**Sortie:**
```
Paquets installés:

  users                 v1.0.0      installé: 2025-01-15
  auth                  v2.0.0      installé: 2025-01-15 (dépend: users)
  database              v1.5.0      installé: 2025-01-15 (dépend: users)
```

---

### pkg search <KEYWORD>

Rechercher des paquets disponibles.

**Arguments:**
- `KEYWORD` (obligatoire): Terme de recherche

**Exemples:**
```bash
# CLI
php mpm.php pkg search database

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/search/database"
```

**Sortie:**
```
3 paquets trouvés:

  mysql-driver          v1.0.0      Pilote base de données MySQL [installé]
    Fournit la connectivité MySQL pour les applications

  postgres-driver       v1.0.0      Pilote PostgreSQL
    Connecteur de base de données PostgreSQL

  database-tools        v2.0.0      Outils de gestion de base de données
    Utilitaires et assistants courants de base de données
```

---

### pkg info <PACKAGE>

Afficher les informations détaillées du paquet.

**Arguments:**
- `PACKAGE` (obligatoire): Nom du paquet

**Exemples:**
```bash
# CLI
php mpm.php pkg info users

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/info/users"
```

**Sortie:**
```
Paquet: Système de gestion des utilisateurs
ID: users
Dernière: 1.0.0
Description: Gestion complète des utilisateurs avec authentification
Auteur: Équipe Mehrnet
Licence: MIT

Installé: v1.0.0 (le 2025-01-15T10:30:00Z)
Dépendances: aucune

Versions disponibles:
  v1.0.0 - publié: 2025-01-15
  v0.9.0 - publié: 2025-01-01 (requiert: auth-lib)
```

**Erreurs:**
- 404: Paquet non trouvé

---

### pkg help

Afficher l'aide du gestionnaire de paquets.

**Exemples:**
```bash
# CLI
php mpm.php pkg help

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/help"
```

---

### pkg version

Afficher la version du gestionnaire de paquets.

**Exemples:**
```bash
# CLI
php mpm.php pkg version

# HTTP
curl "http://localhost/mpm.php/$KEY/pkg/version"
```

---

## Protocole de réponse

### Codes de statut HTTP

| Code | Signification |
|------|---------------|
| 200 | Succès |
| 400 | Mauvaise requête (arguments invalides, erreurs opérationnelles) |
| 403 | Interdit (clé API invalide, validation du chemin échouée) |
| 404 | Non trouvé (commande, paquet ou fichier non trouvé) |
| 503 | Service indisponible (tous les miroirs ont échoué, verrou maintenu) |

### Codes de sortie CLI

| Code | Signification |
|------|---------------|
| 0 | Succès |
| 1 | Erreur (tout type) |

### Sémantique POSIX

- Les commandes avec sortie retournent les données
- Les commandes sans sortie retournent une chaîne vide (mais sortie toujours 0/200)
- Les erreurs lancent des exceptions avec les codes appropriés

---

## Fichiers de configuration

### .config/key

Fichier texte brut contenant la clé API hexadécimale de 64 caractères.

**Format:**
```
abc123def456789...  (64 caractères hexadécimaux)
```

**Génération:**
```php
$key = bin2hex(random_bytes(32));
```

**Sécurité:**
- Générée automatiquement au premier lancement
- Utilisée pour l'authentification HTTP
- Chargée automatiquement en mode CLI
- Comparaison sûre aux attaques temporelles via `hash_equals()`

---

### .config/repos.json

Configuration des miroirs du référentiel.

**Format:**
```json
{
  "main": [
    "https://primary-mirror.com/packages",
    "https://secondary-mirror.com/packages"
  ],
  "extra": [
    "https://extra-repo.com/packages"
  ]
}
```

**Sélection du miroir:**
- Les miroirs sont essayés séquentiellement en cas d'échec
- Aucun délai de recul
- Le premier miroir réussi gagne

---

### .config/packages.json

Registre des paquets installés.

**Format:**
```json
{
  "createdAt": "2025-01-15T10:00:00Z",
  "updatedAt": "2025-01-15T10:30:00Z",
  "packages": {
    "users": {
      "version": "1.0.0",
      "installed_at": "2025-01-15T10:30:00Z",
      "dependencies": [],
      "files": [
        "./app/packages/users/handler.php",
        "./app/packages/users/module.php"
      ],
      "download_url": "https://mirror.com/users-1.0.0.zip",
      "download_time": "2025-01-15T10:29:00Z",
      "checksum": "sha256:abc123...",
      "repository": "main"
    }
  }
}
```

**Métadonnées:**
- `createdAt`: Heure de création du registre
- `updatedAt`: Heure de dernière modification
- `files`: Tableau des chemins de fichiers installés (pour la suppression)
- `download_url`: Miroir source utilisé
- `checksum`: Checksum vérifié

---

### .config/path.json

Modèles de découverte de gestionnaire de commandes.

**Format:**
```json
[
  "app/packages/[name]/handler.php",
  "bin/[name].php"
]
```

**Correspondance de modèle:**
- `[name]` remplacé par le nom de la commande
- Les modèles sont recherchés dans l'ordre du tableau
- Le premier correspondance gagne

**Exemple:**
La commande `users` recherche:
1. `app/packages/users/handler.php`
2. `bin/users.php`

---

## Commandes personnalisées

### Format du gestionnaire

Les gestionnaires sont des fichiers PHP retournant un callable:

```php
<?php
return function(array $args): string {
    // Traiter les arguments
    // Retourner la réponse ou lancer une exception
    return "output";
};
```

### Traitement des arguments

**Mode HTTP:**
```
GET /mpm.php/KEY/mycommand/action/arg1/arg2
                       ↓        ↓      ↓    ↓
                   command   args[0] [1]  [2]
```

**Mode CLI:**
```bash
php mpm.php mycommand action arg1 arg2
                ↓        ↓      ↓    ↓
            command   args[0] [1]  [2]
```

**Le gestionnaire reçoit:**
```php
function(array $args): string {
    $action = $args[0];  // 'action'
    $arg1 = $args[1];    // 'arg1'
    $arg2 = $args[2];    // 'arg2'
}
```

### Gestion des erreurs

Lancer des exceptions avec codes de statut HTTP:

```php
// Mauvaise requête
throw new \RuntimeException('Entrée invalide', 400);

// Non trouvé
throw new \RuntimeException('Élément non trouvé', 404);

// Interdit
throw new \RuntimeException('Accès refusé', 403);
```

**Note:** Le mode CLI convertit tous les codes d'erreur au code de sortie 1.

### Exemple de gestionnaire

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'help';

    switch ($action) {
        case 'list':
            // Lire les données
            $data = json_decode(
                file_get_contents('app/data/items.json'),
                true
            );
            return json_encode($data);

        case 'get':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('ID requis', 400);
            }
            // Récupérer l'élément
            return "Item: $id";

        case 'create':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Nom requis', 400);
            }
            // Créer l'élément
            return "Created: $name";

        case 'help':
            return "Actions: list, get <id>, create <name>";

        default:
            throw new \RuntimeException("Action inconnue: $action", 404);
    }
};
```

---

## Architecture

### Structure des fichiers

```
.
├── mpm.php              # Exécutable principal (2300 lignes)
├── .config/               # Configuration (création automatique)
│   ├── key                # Clé API
│   ├── repos.json         # Miroirs du référentiel
│   ├── packages.json      # Paquets installés
│   └── path.json          # Modèles de gestionnaire
├── .cache/                # Cache (création automatique)
│   ├── mpm.lock           # Fichier de verrou
│   └── *.zip              # Paquets téléchargés
└── app/packages/          # Paquets installés
    └── [package]/
        └── handler.php
```

### Flux d'exécution

```
1. Détection de runtime (HTTP/CLI)
    ↓
2. Initialiser les fichiers de configuration
    ↓
3. Analyser la requête (PATH_INFO ou argv)
    ↓
4. Valider la clé API
    ↓
5. Exécuter la commande
    ├─ Commande intégrée
    ├─ Gestionnaire de paquets
    └─ Gestionnaire personnalisé
    ↓
6. Envoyer la réponse (en-têtes HTTP ou STDOUT/STDERR)
```

### Flux du gestionnaire de paquets

**Installation:**
```
1. Résoudre les dépendances (tri topologique DFS)
    ↓
2. Télécharger tous les paquets (avec vérification de checksum)
    ↓
3. Extraire tous les paquets (avec résolution de conflit)
    ↓
4. Enregistrer dans .config/packages.json (atomiquement)
```

**Caractéristiques clés:**
- **Résolution de dépendances:** Complexité O(V + E)
- **Détection de cycle:** Recherches d'ensemble O(1)
- **Vérification de checksum:** SHA256 avec comparaison sûre aux attaques temporelles
- **Basculement de miroir:** Nouvel essai séquentiel, aucun recul
- **Opérations atomiques:** Installation tout ou rien
- **Résolution de conflit:** Fichiers existants renommés avec `-2`, `-3`, etc.

### Modèle de sécurité

1. **Authentification:**
    - Clé API aléatoire de 64 caractères
    - Comparaison sûre aux attaques temporelles via `hash_equals()`
    - Chargement automatique en mode CLI

2. **Validation du chemin:**
    - Bloque les chemins absolus (`/etc/passwd`)
    - Bloque la traversée de répertoires (`../../../`)
    - Appliquée à toutes les opérations de fichiers

3. **Sécurité des paquets:**
    - Vérification de checksum SHA256
    - Détection de traversée Zip
    - Application HTTPS du miroir

---

## Performance

### Points de référence

- **Exécution de l'application:** <1ms (mesurée via profilage)
- **Surcharge du serveur intégré:** 1000ms+ (utilisez un serveur web approprié en production)
- **Téléchargement de paquets:** Dépend de la vitesse du réseau et du miroir
- **Résolution de dépendances:** O(V + E) où V = paquets, E = dépendances

### Conseils d'optimisation

1. **Utiliser un serveur web de production** (Nginx, Apache)
2. **Activer le cache de code PHP** (OPcache)
3. **Utiliser un CDN pour les miroirs de paquets**
4. **Minimiser la complexité du gestionnaire personnalisé**
5. **Mettre en cache la base de données des paquets localement**

---

## Dépannage

### "Clé API invalide"

**HTTP 403**

**Cause:** Clé API manquante ou incorrecte

**Solution:**
```bash
# Vérifier la clé
cat .config/key

# Régénérer la clé (supprimer et réexécuter)
rm .config/key
php mpm.php ls
```

---

### "Clé API non trouvée" (CLI)

**Code de sortie 1**

**Cause:** Le fichier `.config/key` n'existe pas

**Solution:**
```bash
# Initialiser le shell
php mpm.php ls
```

---

### "Commande non trouvée"

**HTTP 404 / Code de sortie 1**

**Cause:** Fichier gestionnaire non trouvé

**Solution:**
```bash
# Vérifier les modèles de gestionnaire
cat .config/path.json

# Vérifier que le gestionnaire existe
ls app/packages/[command]/handler.php
ls bin/[command].php
```

---

### "Une autre opération pkg est en cours"

**HTTP 503 / Code de sortie 1**

**Cause:** Le fichier de verrou existe

**Solution:**
```bash
# Attendre que l'opération se termine, ou forcer le déverrouillage
php mpm.php pkg unlock

# Ou supprimer manuellement
rm .cache/mpm.lock
```

---

### "Paquet non trouvé"

**HTTP 404 / Code de sortie 1**

**Cause:** Paquet non dans le référentiel

**Solution:**
```bash
# Actualiser le cache du référentiel
php mpm.php pkg update

# Rechercher le paquet
php mpm.php pkg search keyword
```

---

### "Dépendance circulaire"

**HTTP 400 / Code de sortie 1**

**Cause:** Cycle de dépendance de paquet (A → B → C → A)

**Solution:**
Examinez les dépendances des paquets et supprimez le cycle. C'est un problème du référentiel de paquets.

---

### "Impossible de supprimer - requis par"

**HTTP 400 / Code de sortie 1**

**Cause:** D'autres paquets dépendent du paquet cible

**Solution:**
```bash
# Supprimer d'abord les paquets dépendants
php mpm.php pkg del dependent-package
php mpm.php pkg del target-package
```

---

### Erreurs de permission

**Cause:** Permissions du système de fichiers

**Solution:**
```bash
# Corriger les permissions
chmod 755 .
chmod 644 mpm.php
chmod 755 .config
chmod 644 .config/*
chmod 755 .cache
```

---

<!--html-ignore-->
## Voir aussi

- **[Guide d'installation](INSTALL_FR.md)** - Configuration et installation
- **[Développement de paquets](PACKAGES_FR.md)** - Création de paquets
- **[Dépôt GitHub](https://github.com/mehrnet/mpm)** - Code source

---

Pour les questions ou les problèmes, veuillez visiter: https://github.com/mehrnet/mpm/issues
<!--/html-ignore-->
