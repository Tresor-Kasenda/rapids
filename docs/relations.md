# Relations Laravel Avancées avec Rapids

Ce guide détaille l'utilisation des relations Laravel avancées avec le package Rapids, y compris toutes les relations polymorphiques et les relations "through".

## Relations disponibles

Rapids supporte maintenant **toutes** les relations Laravel :

1. Relations simples
   - `hasOne`
   - `belongsTo`
   - `hasMany`
   - `belongsToMany`

2. Relations "through"
   - `hasOneThrough`
   - `hasManyThrough`

3. Relations polymorphiques
   - `morphOne`
   - `morphMany`
   - `morphTo`
   - `morphToMany`
   - `morphedByMany`

## Relations "Through"

### 1. hasOneThrough

Cette relation établit une connexion "one-to-one" entre deux modèles en passant par un modèle intermédiaire.

**Exemple :** Un `Supplier` a une relation indirecte vers `Account` via le modèle intermédiaire `User`.

```
Supplier → User → Account
```

**Utilisation avec Rapids :**

```bash
php artisan rapids:model Supplier

# Quand on vous le demande:
> Ajouter une relation ? Yes
> Sélectionner le type de relation : hasOneThrough
> Entrer le nom du modèle lié : Account
> Entrer le nom du modèle intermédiaire : User
> Entrer la clé étrangère sur le modèle intermédiaire : supplier_id
> Entrer la clé étrangère sur le modèle cible : user_id
```

**Code généré :**

```php
// Dans Supplier.php
public function account(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
{
    return $this->hasOneThrough(
        Account::class, 
        User::class,
        'supplier_id', // Clé étrangère sur la table User
        'user_id',     // Clé étrangère sur la table Account
        'id',          // Clé locale sur la table Supplier
        'id'           // Clé locale sur la table User
    );
}
```

### 2. hasManyThrough

Cette relation établit une connexion "one-to-many" entre deux modèles en passant par un modèle intermédiaire.

**Exemple :** Un `Country` a plusieurs `Post` via le modèle intermédiaire `User`.

```
Country → Users → Posts
```

**Utilisation avec Rapids :**

```bash
php artisan rapids:model Country

# Quand on vous le demande:
> Ajouter une relation ? Yes
> Sélectionner le type de relation : hasManyThrough
> Entrer le nom du modèle lié : Post
> Entrer le nom du modèle intermédiaire : User
> Entrer la clé étrangère sur le modèle intermédiaire : country_id
> Entrer la clé étrangère sur le modèle cible : user_id
```

**Code généré :**

```php
// Dans Country.php
public function posts(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
{
    return $this->hasManyThrough(
        Post::class, 
        User::class,
        'country_id', // Clé étrangère sur la table User
        'user_id',    // Clé étrangère sur la table Post
        'id',         // Clé locale sur la table Country
        'id'          // Clé locale sur la table User
    );
}
```

## Relations Polymorphiques

### 1. morphTo et morphOne/morphMany

Ces relations permettent à un modèle d'appartenir à plus d'un autre modèle sur une seule association.

**Exemple :** Une `Image` peut appartenir à un `Post` ou à un `User`.

**Utilisation avec Rapids :**

```bash
php artisan rapids:model Image

# Quand on vous le demande:
> Ajouter un champ : imageable_id
> Type du champ : integer
> Ajouter un champ : imageable_type
> Type du champ : string
> Sélectionner le type de relation : morphTo
> Entrer le nom polymorphique : imageable
```

**Code généré :**

```php
// Dans Image.php
public function imageable(): \Illuminate\Database\Eloquent\Relations\MorphTo
{
    return $this->morphTo();
}

// Dans Post.php (lors de la création du modèle Post)
public function image(): \Illuminate\Database\Eloquent\Relations\MorphOne
{
    return $this->morphOne(Image::class, 'imageable');
}

// Dans User.php (lors de la création du modèle User)
public function image(): \Illuminate\Database\Eloquent\Relations\MorphOne
{
    return $this->morphOne(Image::class, 'imageable');
}
```

### 2. morphToMany et morphedByMany

Ces relations établissent des connexions "many-to-many" polymorphiques.

**Exemple :** Un `Post` ou une `Video` peuvent avoir plusieurs `Tag`, et un `Tag` peut être associé à de nombreux `Post` ou `Video`.

**Utilisation avec Rapids :**

```bash
php artisan rapids:model Post

# Quand on vous le demande:
> Ajouter une relation ? Yes
> Sélectionner le type de relation : morphToMany
> Entrer le nom du modèle lié : Tag
> Entrer le nom polymorphique : taggable
> Ajouter timestamps à la table pivot ? Yes
> Personnaliser le nom de la table pivot ? No
```

**Code généré :**

```php
// Dans Post.php
public function tags(): \Illuminate\Database\Eloquent\Relations\MorphToMany
{
    return $this->morphToMany(Tag::class, 'taggable')
        ->withTimestamps();
}

// Dans Tag.php (lors de la création ou mise à jour du modèle Tag)
public function posts(): \Illuminate\Database\Eloquent\Relations\MorphedByMany
{
    return $this->morphedByMany(Post::class, 'taggable')
        ->withTimestamps();
}
```

## Options avancées pour les tables pivot

### Personnalisation des tables pivot

Pour les relations `belongsToMany`, `morphToMany` et `morphedByMany`, vous pouvez personnaliser la table pivot :

```bash
> Personnaliser le nom de la table pivot ? Yes
> Entrer le nom personnalisé de la table pivot : custom_post_tags
```

### Ajout de champs supplémentaires

Pour les tables pivot, vous pouvez ajouter des champs supplémentaires :

```bash
> Ajouter des champs supplémentaires à la table pivot ? Yes
> Entrer le nom du champ : status
> Entrer le type du champ : enum
> Entrer les valeurs (séparées par des virgules) : pending,approved,rejected
```

### Ajout de timestamps

Pour les tables pivot, vous pouvez activer les timestamps :

```bash
> Ajouter timestamps à la table pivot ? Yes
```

Cela ajoute `$table->timestamps();` à la migration de la table pivot et appelle `->withTimestamps()` sur la relation.

## Stratégies d'implémentation recommandées

### 1. Relations hiérarchiques

Pour les modèles avec des hiérarchies ou des arbres :

```php
// Auto-relation
public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Category::class, 'parent_id');
}

public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Category::class, 'parent_id');
}
```

### 2. Combinaison de relations standard et polymorphiques

Pour des modèles comme les commentaires qui peuvent être à la fois liés à un utilisateur et à différents types de contenu :

```php
// Dans Comment.php
public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(User::class);
}

public function commentable(): \Illuminate\Database\Eloquent\Relations\MorphTo
{
    return $this->morphTo();
}
```

### 3. Relations many-to-many avec données pivots

Pour des relations many-to-many avec des données supplémentaires :

```php
// Dans User.php
public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->withTimestamps()
        ->withPivot('is_active', 'expires_at');
}
```

## Conclusion

Le package Rapids offre maintenant un support complet pour toutes les relations Laravel, y compris les relations les plus avancées comme les relations "through" et polymorphiques. Cette flexibilité vous permet de modéliser facilement des structures de données complexes tout en maintenant un code propre et maintenable.