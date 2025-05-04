# RapidsModels

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rapids/rapids.svg?style=flat-square)](https://packagist.org/packages/rapids/rapids)
[![Total Downloads](https://img.shields.io/packagist/dt/rapids/rapids.svg?style=flat-square)](https://packagist.org/packages/rapids/rapids)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/Tresor-Kasenda?style=social)](https://x.com/TresorKasenda)
[![GitHub Issues](https://img.shields.io/github/issues/Tresor-Kasenda/rapids-models.svg?style=flat-square)](https://packagist.org/packages/rapids/rapids)

> **Supercharge your Laravel development workflow by generating complete model ecosystems with a single command**

## 📚 Table of Contents

- [Introduction](#introduction)
- [Installation](#installation)
- [Core Features](#core-features)
- [Basic Usage](#basic-usage)
- [Field Types](#field-types)
- [Relationship Management](#relationship-management)
    - [Belongs To Relationship](#1-belongs-to-relationship)
    - [Has One Relationship](#2-has-one-relationship)
    - [Has Many Relationship](#3-has-many-relationship)
    - [Belongs To Many Relationship](#4-belongs-to-many-relationship)
    - [Has One Through Relationship](#5-has-one-through-relationship)
    - [Has Many Through Relationship](#6-has-many-through-relationship)
    - [Polymorphic Relationships](#7-polymorphic-relationships)
- [Working with Existing Models](#working-with-existing-models)
- [PHP Compatibility](#php-compatibility)
- [Contributing](#contributing)
- [Support](#support)
- [License](#license)

## Introduction

RapidsModels is a Laravel package designed to streamline your development workflow by automating the creation of the
entire model ecosystem. Instead of manually creating models, migrations, factories, and seeders separately, RapidsModels
handles everything with a single command and an intuitive interactive process.

**Why Use RapidsModels?**

- **Time Efficiency**: Create complete model ecosystems in seconds
- **Consistency**: Maintain standardized code across your project
- **Interactive Process**: Guided setup with clear prompts
- **Complete Solution**: Generates models, migrations, factories, seeders, and relationships
- **Full Laravel Relations Support**: Supports ALL Laravel relationship types (hasOne, belongsTo, hasMany, belongsToMany, hasOneThrough, hasManyThrough, morphOne, morphMany, morphTo, morphToMany, morphedByMany)
- **Modern PHP Support**: Compatible with PHP 8.2, 8.3, and 8.4
- **Flexible**: Works with new projects or existing codebases

## Installation

Installing RapidsModels is straightforward with Composer:

```bash
composer require rapids/rapids
```

Laravel will automatically discover the package - no additional configuration required.

## Core Features

- **One-Command Generation**: Create models, migrations, factories, and seeders with a single command
- **Interactive Setup**: Guided creation process for fields and relationships
- **Comprehensive Field Support**: Supports all Laravel field types with appropriate options
- **Automated Relationships**: Configures both sides of model relationships
- **Complete Relations Support**: All Laravel relationships including through and polymorphic relations
- **Pivot Table Support**: Handles many-to-many relationships with customizable pivot tables
- **Existing Model Integration**: Works with existing models to add fields or relationships
- **Migration Generation**: Creates migrations for new models or updates to existing ones
- **Modern PHP Support**: Takes advantage of PHP 8.2+ features like readonly classes

## Basic Usage

Generate a complete model ecosystem with a single command:

```bash
php artisan rapids:model Product
```

The interactive process will guide you through:

1. Adding fields with their types and options
2. Setting up foreign keys and relationships
3. Configuring timestamps, soft deletes, and other options
4. Creating factories and seeders

### Using Fields JSON Flag

You can also create a model with a single command by providing field definitions as a JSON string:

```bash
php artisan rapids:model User --fields='{"name":{"type":"string"},"email":{"type":"string"},"password":{"type":"string"},"_config":{"softDeletes":true}}'
```

The JSON structure supports:
- Field definitions with type, nullable, default, length properties
- Relationship definitions with type, model, and inverse properties
- Configuration options like softDeletes

Example with relationships:

```bash
php artisan rapids:model Post --fields='{"title":{"type":"string"},"content":{"type":"text"},"category":{"relation":{"type":"belongsTo","model":"Category","inverse":"hasMany"}},"_config":{"softDeletes":true}}'
```

### Traditional Approach vs RapidsModels

**Traditional Approach:**

```
- Create model:        php artisan make:model Product
- Create migration:    php artisan make:migration create_products_table
- Create factory:      php artisan make:factory ProductFactory
- Create seeder:       php artisan make:seeder ProductSeeder
- Define fields:       Manually edit migration file
- Configure relations: Manually edit model files
```

**RapidsModels Approach:**

```
- Everything at once:  php artisan rapids:model Product
                       (follow the interactive prompts)
```

## Field Types

RapidsModels supports all standard Laravel field types:

| Type     | Description           | Example Use Cases               |
|----------|-----------------------|---------------------------------|
| string   | Text data             | name, title, slug               |
| text     | Longer text           | content, description, biography |
| integer  | Whole numbers         | count, position, age            |
| decimal  | Numbers with decimals | price, weight, rating           |
| boolean  | True/false values     | is_active, has_discount         |
| date     | Date without time     | birth_date, release_date        |
| datetime | Date with time        | starts_at, expires_at           |
| enum     | Predefined options    | status, role, type              |
| json     | JSON data             | settings, preferences, metadata |
| uuid     | UUID identifiers      | uuid field with HasUuids trait  |

## Relationship Management

RapidsModels simplifies creating and managing relationships between models.

### 1. Belongs To Relationship

**Example: Product belongs to Category**

```bash
> Enter field name: category_id
> Enter field type: integer
> Is this a foreign key? Yes
> Enter related model name: Category
> Select relationship type: belongsTo
> Select inverse relationship type: hasMany
```

**Generated Code:**

```php
// In Product.php
public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Category::class);
}

// In Category.php
public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Product::class);
}
```

### 2. Has One Relationship

**Example: User has one Profile**

```bash
> Enter field name: user_id
> Enter field type: integer
> Is this a foreign key? Yes
> Enter related model name: User
> Select relationship type: belongsTo
> Select inverse relationship type: hasOne
```

**Generated Code:**

```php
// In Profile.php
public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(User::class);
}

// In User.php
public function profile(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(Profile::class);
}
```

### 3. Has Many Relationship

**Example: Author has many Books**

```bash
> Enter field name: author_id
> Enter field type: integer
> Is this a foreign key? Yes
> Enter related model name: Author
> Select relationship type: belongsTo
> Select inverse relationship type: hasMany
```

**Generated Code:**

```php
// In Book.php
public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(Author::class);
}

// In Author.php
public function books(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Book::class);
}
```

### 4. Belongs To Many Relationship

**Example: Post has many Tags (and vice versa)**

```bash
> Add relationship? Yes
> Enter related model name: Tag
> Select relationship type: belongsToMany
> Customize pivot table name? No
> Add timestamps to pivot? Yes
```

**Generated Code:**

```php
// In Post.php
public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Tag::class)
        ->withTimestamps();
}

// In Tag.php
public function posts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Post::class)
        ->withTimestamps();
}
```

### 5. Has One Through Relationship

**Example: Supplier has one Account through User**

```bash
> Add relationship? Yes
> Select relationship type: hasOneThrough
> Enter related model name: Account
> Enter intermediate model name: User
> Enter foreign key on intermediate model: supplier_id
> Enter foreign key on target model: user_id
```

**Generated Code:**

```php
// In Supplier.php
public function account(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
{
    return $this->hasOneThrough(
        Account::class,
        User::class,
        'supplier_id', // Foreign key on User table...
        'user_id',     // Foreign key on Account table...
        'id',          // Local key on Supplier table...
        'id'           // Local key on User table...
    );
}
```

### 6. Has Many Through Relationship

**Example: Country has many Patients through Hospitals**

```bash
> Add relationship? Yes
> Select relationship type: hasManyThrough
> Enter related model name: Patient
> Enter intermediate model name: Hospital
> Enter foreign key on intermediate model: country_id
> Enter foreign key on target model: hospital_id
```

**Generated Code:**

```php
// In Country.php
public function patients(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
{
    return $this->hasManyThrough(
        Patient::class,
        Hospital::class,
        'country_id',  // Foreign key on Hospital table...
        'hospital_id', // Foreign key on Patient table...
        'id',          // Local key on Country table...
        'id'           // Local key on Hospital table...
    );
}
```

### 7. Polymorphic Relationships

**Example: Image morphTo multiple models (Post, User)**

```bash
> Enter field name: imageable_id
> Enter field name: imageable_type
> Select relationship type: morphTo
> Enter polymorphic name: imageable
```

**Generated Code:**

```php
// In Image.php
public function imageable(): \Illuminate\Database\Eloquent\Relations\MorphTo
{
    return $this->morphTo();
}

// In Post.php (when creating the Post model)
public function image(): \Illuminate\Database\Eloquent\Relations\MorphOne
{
    return $this->morphOne(Image::class, 'imageable');
}

// In User.php (when creating the User model)
public function image(): \Illuminate\Database\Eloquent\Relations\MorphOne
{
    return $this->morphOne(Image::class, 'imageable');
}
```

## Working with Existing Models

RapidsModels integrates seamlessly with existing Laravel projects:

### Adding Fields to Existing Models

When running `rapids:model` on an existing model name:

```bash
php artisan rapids:model Product
```

The system will detect the existing model and offer options:

1. **Add a new migration for the existing model**: Create a migration to add fields to an existing table
2. **Update the existing model file**: Add relationships or methods to the model class
3. **Generate additional components**: Create missing factory or seeder files

### Example: Adding a Relationship to an Existing Model

```bash
php artisan rapids:model Product
> Model Product already exists.
> What would you like to do? Update existing model file
> Add relationship? Yes
> Enter related model name: Supplier
> Select relationship type: belongsTo
> Create migration for foreign key? Yes
```

This will:

1. Create a migration to add the supplier_id field
2. Add the relationship method to your Product model
3. Add the inverse relationship method to your Supplier model

## PHP Compatibility

RapidsModels is compatible with:

- PHP 8.2
- PHP 8.3
- PHP 8.4

The package takes advantage of modern PHP features including:

- Readonly classes and properties
- Constructor property promotion
- Match expressions
- Return type declarations
- Named arguments

## Contributing

Contributions are welcome! Here's how you can help:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add some amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/Tresor-Kasenda/rapids-models.git

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit
```

## Support

If you find RapidsModels useful in your projects, consider supporting development:

- **Star the repository** on GitHub
- **Share your experience** on social media using #RapidsModels
- **Donate** via [GitHub Sponsors](https://github.com/sponsors/Tresor-Kasenda)
- **Hire me** for your Laravel projects

## License

RapidsModels is open-source software licensed under the [MIT license](LICENSE.md).

---

Made with ❤️ by [Tresor Kasenda](https://github.com/Tresor-Kasenda)
