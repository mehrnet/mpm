# Guide de développement des paquets

Guide complet pour créer, tester et distribuer les paquets MPM (Gestionnaire de paquets Mehr).

<!--html-ignore-->
Pour les instructions d'installation et de configuration, voir [INSTALL_FR.md](INSTALL_FR.md) ou [USAGE_FR.md](USAGE_FR.md).

---

## Table des matières

- [Démarrage rapide](#démarrage-rapide)
- [Structure du paquet](#structure-du-paquet)
- [Implémentation du gestionnaire](#implémentation-du-gestionnaire)
- [Métadonnées du paquet](#métadonnées-du-paquet)
- [Tests des paquets](#tests-des-paquets)
- [Distribution](#distribution)
- [Bonnes pratiques](#bonnes-pratiques)
- [Exemples](#exemples)

---
<!--/html-ignore-->

## Démarrage rapide

Créez un paquet minimal en 3 étapes:

```bash
# 1. Créer la structure du paquet
mkdir -p mypackage
cd mypackage

# 2. Créer le gestionnaire
cat > handler.php << 'EOF'
<?php
return function(array $args): string {
    return "Bonjour depuis mypackage!";
};
EOF

# 3. Créer les métadonnées
cat > package.json << 'EOF'
{
    "id": "mypackage",
    "name": "My Package",
    "version": "1.0.0",
    "description": "Un paquet simple",
    "author": "Votre nom",
    "license": "MIT",
    "dependencies": []
}
EOF

# Le tester
cd ..
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/
php mpm.php mypackage
```

---

## Structure du paquet

### Fichiers obligatoires

```
mypackage/
├── handler.php       # OBLIGATOIRE: point d'entrée
└── package.json      # OBLIGATOIRE: métadonnées
```

### Structure optionnelle

```
mypackage/
├── handler.php       # Point d'entrée (obligatoire)
├── package.json      # Métadonnées (obligatoire)
├── lib/             # Code de bibliothèque
│   ├── MyClass.php
│   └── helpers.php
├── data/            # Fichiers de données
│   └── config.json
├── views/           # Modèles
│   └── template.html
└── README.md        # Documentation du paquet
```

### Extraction de fichiers

Les paquets s'extraient à la racine du projet en préservant les chemins:

**Le paquet contient:**
```
handler.php
module.php
lib/Database.php
data/schema.json
```

**S'extrait vers:**
```
./handler.php
./module.php
./lib/Database.php
./data/schema.json
```

Pour les modules du framework Mehr, utilisez:
```
app/packages/[name]/handler.php
app/packages/[name]/module.php
```

---

## Implémentation du gestionnaire

### Gestionnaire basique

```php
<?php
return function(array $args): string {
    // Action unique
    return "Bonjour, le monde!";
};
```

### Gestionnaire multi-actions

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'help';

    switch ($action) {
        case 'list':
            return handleList();

        case 'get':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('ID requis', 400);
            }
            return handleGet($id);

        case 'create':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Nom requis', 400);
            }
            return handleCreate($name);

        case 'delete':
            $id = $args[1] ?? null;
            if (!$id) {
                throw new \RuntimeException('ID requis', 400);
            }
            return handleDelete($id);

        case 'help':
            return <<<'EOF'
Actions disponibles:
  list              - Lister tous les éléments
  get <id>          - Obtenir l'élément par ID
  create <name>     - Créer un nouvel élément
  delete <id>       - Supprimer l'élément
EOF;

        default:
            throw new \RuntimeException("Action inconnue: $action", 404);
    }
};

function handleList(): string {
    return "item1\nitem2\nitem3";
}

function handleGet(string $id): string {
    // Charger à partir du fichier, de la base de données, etc.
    return "Item data for: $id";
}

function handleCreate(string $name): string {
    // Créer et persister
    return "Created: $name";
}

function handleDelete(string $id): string {
    // Supprimer l'élément
    return "Deleted: $id";
}
```

### Traitement des arguments

Les arguments sont identiques dans les modes HTTP et CLI:

**HTTP:**
```
GET /mpm.php/KEY/users/create/alice/alice@example.com
                    ↓      ↓      ↓         ↓
                command args[0] args[1]  args[2]
```

**CLI:**
```bash
php mpm.php users create alice alice@example.com
               ↓      ↓      ↓         ↓
           command args[0] args[1]  args[2]
```

**Gestionnaire:**
```php
function(array $args): string {
    $action = $args[0];    // 'create'
    $name = $args[1];      // 'alice'
    $email = $args[2];     // 'alice@example.com'
}
```

### Opérations sur les fichiers

Les gestionnaires s'exécutent dans le répertoire mpm.php. Utilisez les chemins relatifs:

```php
<?php
return function(array $args): string {
    // Lire un fichier de données
    $data = json_decode(
        file_get_contents('app/data/items.json'),
        true
    );

    // Modifier les données
    $data['new_item'] = $args[0] ?? 'default';

    // Réécrire
    file_put_contents(
        'app/data/items.json',
        json_encode($data, JSON_PRETTY_PRINT)
    );

    return "Mis à jour";
};
```

### Gestion des erreurs

```php
<?php
return function(array $args): string {
    $id = $args[0] ?? null;

    // Validation
    if (!$id) {
        throw new \RuntimeException('ID requis', 400);
    }

    if (!is_numeric($id)) {
        throw new \RuntimeException('L\'ID doit être numérique', 400);
    }

    // Opérations sur les fichiers
    $file = "app/data/$id.json";
    if (!file_exists($file)) {
        throw new \RuntimeException('Élément non trouvé', 404);
    }

    try {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!$data) {
            throw new \RuntimeException('JSON invalide', 400);
        }

        return json_encode($data);
    } catch (\Throwable $e) {
        throw new \RuntimeException(
            "Impossible de lire l'élément: {$e->getMessage()}",
            400
        );
    }
};
```

### Formats de réponse

**Texte brut:**
```php
return "item1\nitem2\nitem3";
```

**JSON:**
```php
return json_encode([
    'status' => 'success',
    'items' => ['item1', 'item2', 'item3']
]);
```

**Vide (POSIX):**
```php
return '';  // Succès sans sortie
```

---

## Métadonnées du paquet

### Format package.json

```json
{
    "id": "mypackage",
    "name": "My Package",
    "version": "1.0.0",
    "description": "Description du paquet",
    "author": "Votre nom",
    "license": "MIT",
    "dependencies": ["dependency1", "dependency2"]
}
```

### Spécifications des champs

| Champ | Type | Obligatoire | Description |
|-------|------|-------------|-------------|
| `id` | chaîne | Oui | Minuscules, pas d'espaces, utilisé dans les URL |
| `name` | chaîne | Oui | Nom lisible |
| `version` | chaîne | Oui | Versioning sémantique (X.Y.Z) |
| `description` | chaîne | Oui | Description brève |
| `author` | chaîne | Non | Auteur du paquet |
| `license` | chaîne | Non | Identifiant de licence (MIT, Apache-2.0, etc.) |
| `dependencies` | tableau | Non | Tableau d'ID de paquets |

### Déclaration des dépendances

```json
{
    "id": "auth",
    "dependencies": ["users", "session"]
}
```

Le gestionnaire de paquets:
- Résout les dépendances transitives automatiquement
- Détecte les dépendances circulaires
- Installe dans le bon ordre (dépendances avant dépendants)
- Ignore les paquets déjà installés

---

## Tests des paquets

### Test manuel (CLI)

```bash
# Installer le paquet manuellement
mkdir -p app/packages/mypackage
cp -r mypackage/* app/packages/mypackage/

# Tester les commandes
php mpm.php mypackage
php mpm.php mypackage list
php mpm.php mypackage get 123
php mpm.php mypackage create "Test Item"
```

### Test manuel (HTTP)

```bash
# Démarrer le serveur
php -S localhost:8000

# Obtenir la clé API
KEY=$(cat .config/key)

# Tester les commandes
curl "http://localhost:8000/mpm.php/$KEY/mypackage"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/list"
curl "http://localhost:8000/mpm.php/$KEY/mypackage/get/123"
```

### Checklist de test

- [ ] Toutes les actions fonctionnent correctement
- [ ] La gestion des erreurs fonctionne (entrée invalide)
- [ ] Les arguments obligatoires sont validés
- [ ] Les opérations sur les fichiers réussissent
- [ ] Les modes CLI et HTTP fonctionnent de manière identique
- [ ] L'action d'aide documente toutes les commandes
- [ ] Les dépendances sont déclarées dans package.json

---

## Distribution

### Création du ZIP du paquet

```bash
cd mypackage
zip -r ../mypackage-1.0.0.zip .

# Vérifier le contenu
unzip -l ../mypackage-1.0.0.zip
```

### Calcul du checksum

```bash
sha256sum mypackage-1.0.0.zip
# Output: abc123def456...  mypackage-1.0.0.zip
```

### Scripts de construction automatisés

Pour un empaquetage réutilisable, créez des scripts shell qui automatisent le processus de construction. Ceci est particulièrement utile pour empaqueter des ressources tierces comme des icônes, des polices ou des bibliothèques JavaScript.

**Structure de base:**

```bash
#!/bin/sh
set -e

PKG="mypackage"
DES="Description du paquet"
VER="1.0.0"

URL="https://example.com/source.zip"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
# Télécharger et extraire la source
wget -q "$URL" -O src.zip
unzip -q src.zip

# Restructurer pour votre format
mkdir -p "assets/category/${PKG}"
mv source-files/* "assets/category/${PKG}/"

# Créer le zip du paquet
zip -rq "$OUT" assets

# Calculer les métadonnées
CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

# Déplacer vers le référentiel
mv "$OUT" "${SCRIPT_DIR}/../main/"

# Mettre à jour database.json
DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" \
   --arg name "$PKG" \
   --arg desc "$DES" \
   --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" \
   --argjson size "$SIZE" \
   --arg url "$OUT" \
   --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name,
       description: $desc,
       author: "mpm-bot",
       license: "MIT",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**Exemple: Paquet d'icônes (heroicons)**

```bash
#!/bin/sh
set -e

PKG="heroicons"
DES="Un ensemble d'icônes SVG de haute qualité sous licence MIT."
VER="2.2.0"

URL="https://github.com/tailwindlabs/heroicons/archive/refs/tags/v${VER}.zip"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
wget -q "$URL" -O src.zip
unzip -q src.zip

mkdir -p "assets/icons/${PKG}"
mv "heroicons-${VER}/src/"* "assets/icons/${PKG}/"

zip -rq "$OUT" assets

CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

mv "$OUT" "${SCRIPT_DIR}/../main/"

DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" --arg name "$PKG" --arg desc "$DES" --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" --argjson size "$SIZE" \
   --arg url "$OUT" --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name, description: $desc, author: "mpm-bot", license: "MIT",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**Exemple: Bibliothèque JavaScript (htmx)**

```bash
#!/bin/sh
set -e

PKG="htmx"
DES="Outils puissants pour HTML."
VER="2.0.4"

URL="https://unpkg.com/htmx.org@${VER}/dist/htmx.min.js"
OUT="${PKG}-${VER}.zip"
TMP="$(mktemp -d)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

cd "$TMP"
mkdir -p "assets/js"
wget -q "$URL" -O "assets/js/htmx.min.js"

zip -rq "$OUT" assets

CHECKSUM=$(sha256sum "$OUT" | cut -d' ' -f1)
SIZE=$(wc -c < "$OUT" | tr -d ' ')
RELEASED=$(date +%Y-%m-%d)

mv "$OUT" "${SCRIPT_DIR}/../main/"

DB="${SCRIPT_DIR}/../main/database.json"
jq --arg id "$PKG" --arg name "$PKG" --arg desc "$DES" --arg ver "$VER" \
   --arg checksum "sha256:$CHECKSUM" --argjson size "$SIZE" \
   --arg url "$OUT" --arg released "$RELEASED" \
   '.packages[$id] = {
       name: $name, description: $desc, author: "mpm-bot", license: "BSD-2-Clause",
       versions: { ($ver): { version: $ver, dependencies: [], checksum: $checksum, size: $size, download_url: $url, released_at: $released } },
       latest: $ver
   }' "$DB" > "${DB}.tmp" && mv "${DB}.tmp" "$DB"

rm -rf "$TMP"
echo "[+] Built ${PKG} v${VER}"
```

**Structure du référentiel:**

```
mpm-repo/
├── scripts/
│   ├── heroicons.sh
│   ├── htmx.sh
│   ├── alpinejs.sh
│   └── ...
└── main/
    ├── database.json
    ├── heroicons-2.2.0.zip
    ├── htmx-2.0.4.zip
    └── ...
```

**Construire tous les paquets:**

```bash
for script in scripts/*.sh; do ./$script; done
```

### database.json du référentiel

```json
{
  "version": 1,
  "packages": {
    "mypackage": {
      "name": "My Package",
      "description": "Description du paquet",
      "author": "Votre nom",
      "license": "MIT",
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
      "latest": "1.0.0"
    }
  }
}
```

Les fichiers sont suivis localement lors de l'extraction pour une désinstallation propre.

### Hébergement GitHub

```bash
# Créer la structure du référentiel
mkdir -p my-packages/main
cd my-packages/main

# Ajouter le paquet et la base de données
cp /path/to/mypackage-1.0.0.zip .
cat > database.json << 'EOF'
{
  "version": 1,
  "packages": { ... }
}
EOF

# Pousser vers GitHub
cd ..
git init
git add .
git commit -m "Add mypackage"
git remote add origin https://github.com/user/my-packages.git
git push -u origin main

# Les utilisateurs configurent:
# "main": ["https://github.com/user/my-packages/raw/main/main"]
```

---

## Bonnes pratiques

### Validation des entrées

```php
// MAUVAIS: Pas de validation
$id = $args[0];
return processId($id);

// BON: Valider et nettoyer
$id = $args[0] ?? null;
if (!$id || !is_numeric($id) || $id < 1) {
    throw new \RuntimeException('ID invalide', 400);
}
return processId((int)$id);
```

### Sécurité

```php
// MAUVAIS: Accès arbitraire aux fichiers
$file = $args[0];
return file_get_contents($file);  // Vulnérable à ../../../etc/passwd

// BON: Restreindre à un répertoire sûr
$file = basename($args[0] ?? '');  // Supprimer les composants de chemin
$path = "app/data/$file";

if (!file_exists($path)) {
    throw new \RuntimeException('Fichier non trouvé', 404);
}

return file_get_contents($path);
```

### Messages d'erreur

```php
// MAUVAIS: Erreur générique
throw new \RuntimeException('Erreur', 400);

// BON: Erreur descriptive
throw new \RuntimeException('L\'ID utilisateur doit être un entier positif', 400);
```

### Organisation du code

```php
// MAUVAIS: Tout dans le gestionnaire
return function(array $args): string {
    // 500 lignes de code...
};

// BON: Utiliser le répertoire lib/
return function(array $args): string {
    require_once __DIR__ . '/lib/Manager.php';
    $manager = new Manager();
    return $manager->handle($args);
};
```

### Dépendances

```php
// BON: Vérifier si les bibliothèques Composer sont disponibles
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    // Utiliser les bibliothèques
} else {
    throw new \RuntimeException('Dépendances Composer manquantes', 500);
}
```

---

## Exemples

### Compteur simple

```php
<?php
return function(array $args): string {
    $file = 'app/data/counter.txt';

    if (!file_exists($file)) {
        file_put_contents($file, '0');
    }

    $count = (int)file_get_contents($file);
    $count++;
    file_put_contents($file, (string)$count);

    return "Compte: $count";
};
```

### CRUD JSON

```php
<?php
return function(array $args): string {
    $action = $args[0] ?? 'list';
    $file = 'app/data/items.json';

    // Charger les données
    $items = [];
    if (file_exists($file)) {
        $items = json_decode(file_get_contents($file), true) ?? [];
    }

    switch ($action) {
        case 'list':
            return json_encode($items);

        case 'add':
            $name = $args[1] ?? null;
            if (!$name) {
                throw new \RuntimeException('Nom requis', 400);
            }
            $items[] = [
                'id' => count($items) + 1,
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents($file, json_encode($items, JSON_PRETTY_PRINT));
            return "Ajouté: $name";

        case 'get':
            $id = (int)($args[1] ?? 0);
            foreach ($items as $item) {
                if ($item['id'] === $id) {
                    return json_encode($item);
                }
            }
            throw new \RuntimeException('Élément non trouvé', 404);

        default:
            throw new \RuntimeException('Action inconnue', 404);
    }
};
```

### Module du framework Mehr

```
mymodule/
├── app/
│   └── packages/
│       └── mymodule/
│           ├── handler.php       # Gestionnaire de commande shell
│           ├── module.php        # Implémentation du module
│           ├── manifest.json     # Manifeste Mehr
│           └── migrations/       # Migrations de base de données
│               └── 001_create_table.php
└── package.json                  # Métadonnées de distribution
```

**handler.php:**
```php
<?php
return function(array $args): string {
    require_once __DIR__ . '/module.php';
    $module = new MyModule();
    return $module->handleCommand($args);
};
```

**package.json:**
```json
{
    "id": "mymodule",
    "name": "My Module",
    "version": "1.0.0",
    "description": "Module du framework Mehr",
    "dependencies": ["core"]
}
```

---

<!--html-ignore-->
## Voir aussi

- **[Guide d'installation](INSTALL_FR.md)** - Configuration et création de référentiel
- **[Référence d'utilisation](USAGE_FR.md)** - Référence des commandes
- **[Paquets d'exemple](https://github.com/mehrnet/mpm-packages)** - Exemples fonctionnels

---

Pour les questions ou les problèmes, veuillez visiter: https://github.com/mehrnet/mpm/issues
<!--/html-ignore-->
