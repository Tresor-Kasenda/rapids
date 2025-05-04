# RapidsModels Documentation

## Overview

RapidsModels is a powerful Laravel package that streamlines model creation by generating a complete model ecosystem with
a single command. Instead of creating models, migrations, factories, and seeders separately, RapidsModels handles
everything through an interactive process.

## Installation

```bash
composer require rapids/rapids
```

## Basic Usage

```bash
php artisan rapids:model Product
```

## Traditional vs. RapidsModels Approach

```
Traditional Approach:
┌──────────────────────────┬─────────────────────────────────────────────────┐
│ Step                     │ Command                                         │
├──────────────────────────┼─────────────────────────────────────────────────┤
│ 1. Create model          │ php artisan make:model Product                  │
│ 2. Create migration      │ php artisan make:migration create_products_table│
│ 3. Create factory        │ php artisan make:factory ProductFactory         │
│ 4. Create seeder         │ php artisan make:seeder ProductSeeder           │
│ 5. Define fields manually│ Edit migration file                             │
│ 6. Set up relationships  │ Edit model files                                │
└──────────────────────────┴─────────────────────────────────────────────────┘

RapidsModels Approach:
┌──────────────────────────┬──────────────────────────────────────────────┐
│ Step                     │ Command                                      │
├──────────────────────────┼──────────────────────────────────────────────┤
│ Complete model ecosystem │ php artisan rapids:model Product             │
│ with interactive setup   │ (follow prompts for fields and relationships)│
└──────────────────────────┴──────────────────────────────────────────────┘
```

## PHP Compatibility

RapidsModels now fully supports:
- PHP 8.2
- PHP 8.3
- PHP 8.4

And takes advantage of modern PHP features like:
- Readonly classes and properties
- Constructor property promotion
- Match expressions
- Return type declarations
- Named arguments

## Field Types Reference

```
Field Type Options:
┌────────────┬──────────────────────────┬───────────────────────────┐
│ Type       │ Description              │ Example Usage             │
├────────────┼──────────────────────────┼───────────────────────────┤
│ string     │ Text data                │ name, title, description  │
│ text       │ Longer text data         │ content, bio, details     │
│ integer    │ Whole numbers            │ count, position, age      │
│ decimal    │ Numbers with decimals    │ price, weight, rating     │
│ boolean    │ True/false values        │ is_active, has_discount   │
│ date       │ Date without time        │ birth_date, release_date  │
│ datetime   │ Date with time           │ starts_at, expires_at     │
│ enum       │ Predefined options       │ status, role, type        │
│ json       │ JSON data                │ settings, preferences     │
│ uuid       │ UUID identifiers         │ adds HasUuids trait       │
└────────────┴──────────────────────────┴───────────────────────────┘
```

## Supported Laravel Relations

RapidsModels now supports ALL Laravel relationship types:

```
Relationship Types:
┌──────────────┬────────────────────────────────────────────┐
│ Type         │ Description                                │
├──────────────┼────────────────────────────────────────────┤
│ hasOne       │ One-to-one relation                        │
│ belongsTo    │ Inverse of hasOne or hasMany              │
│ hasMany      │ One-to-many relation                       │
│ belongsToMany│ Many-to-many relation                      │
│ hasOneThrough│ One-to-one relation through another model  │
│ hasManyThrough│ One-to-many relation through another model│
│ morphOne     │ One-to-one polymorphic relation           │
│ morphMany    │ One-to-many polymorphic relation          │
│ morphTo      │ Inverse of morphOne or morphMany          │
│ morphToMany  │ Many-to-many polymorphic relation         │
│ morphedByMany│ Inverse of morphToMany                    │
└──────────────┴────────────────────────────────────────────┘
```

## Working with Field Types

### String Fields

```bash
> Enter field name: title
> Enter field type: string
> Field is nullable? No
```

Creates: `$table->string('title');`

### Text Fields

```bash
> Enter field name: description
> Enter field type: text
> Field is nullable? No
```

Creates: `$table->text('description');`

### Integer Fields

```bash
> Enter field name: quantity
> Enter field type: integer
> Field is nullable? No
```

Creates: `$table->integer('quantity');`

### Decimal Fields

```bash
> Enter field name: price
> Enter field type: decimal
> Field is nullable? No
```

Creates: `$table->decimal('price');`

### Boolean Fields

```bash
> Enter field name: is_featured
> Enter field type: boolean
> Field is nullable? No
```

Creates: `$table->boolean('is_featured');`

### Date Fields

```bash
> Enter field name: publication_date
> Enter field type: date
> Field is nullable? Yes
```

Creates: `$table->date('publication_date')->nullable();`

### DateTime Fields

```bash
> Enter field name: expires_at
> Enter field type: datetime
> Field is nullable? Yes
```

Creates: `$table->datetime('expires_at')->nullable();`

### Enum Fields

```bash
> Enter field name: status
> Enter field type: enum
> Enter values (comma separated): draft,published,archived
> Field is nullable? No
```

Creates: `$table->enum('status', ['draft', 'published', 'archived'])->default('draft');`

### JSON Fields

```bash
> Enter field name: settings
> Enter field type: json
> Field is nullable? Yes
```

Creates: `$table->json('settings')->nullable();`

### UUID Fields

```bash
> Enter field name: uuid
> Enter field type: uuid
> Field is nullable? No
```

Creates: `$table->uuid('uuid');` and adds `use Illuminate\Database\Eloquent\Concerns\HasUuids;` to the model.

## Relationship Examples - New Advanced Features

### 1. Has One Through Relationship

```
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Model  │
├────────────┼──────────────┼────────────┼────────────┤
│ Supplier   │ hasOneThrough│ Account    │ User       │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Supplier

# When prompted:
> Add relationship? Yes
> Select relationship type: hasOneThrough
> Enter related model name: Account
> Enter intermediate model name: User
> Enter foreign key on intermediate model: supplier_id
> Enter foreign key on target model: user_id
```

Generated code:

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

### 2. Has Many Through Relationship (Enhanced)

```
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Model  │
├────────────┼──────────────┼────────────┼────────────┤
│ Country    │ hasManyThrough│ Post      │ User       │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Country

# When prompted:
> Add relationship? Yes
> Select relationship type: hasManyThrough
> Enter related model name: Post
> Enter intermediate model name: User
> Enter foreign key on intermediate model: country_id
> Enter foreign key on target model: user_id
```

Generated code:

```php
// In Country.php
public function posts(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
{
    return $this->hasManyThrough(
        Post::class, 
        User::class,
        'country_id', // Foreign key on User table...
        'user_id',    // Foreign key on Post table...
        'id',         // Local key on Country table...
        'id'          // Local key on User table...
    );
}
```

### 3. Polymorphic Relationships (Enhanced Support)

```
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Comment    │ morphTo      │ Commentable│ commentable│
│ Post       │ morphMany    │ Comment    │ comments   │
│ Video      │ morphMany    │ Comment    │ comments   │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Comment

# When prompted:
> Enter field name: commentable_id
> Enter field name: commentable_type
> Select relationship type: morphTo
> Enter polymorphic name: commentable
```

Generated code:

```php
// In Comment.php
public function commentable(): \Illuminate\Database\Eloquent\Relations\MorphTo
{
    return $this->morphTo();
}

// In Post.php (when creating Post model)
public function comments(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}

// In Video.php (when creating Video model)
public function comments(): \Illuminate\Database\Eloquent\Relations\MorphMany
{
    return $this->morphMany(Comment::class, 'commentable');
}
```

### 4. Many-to-Many Polymorphic Relationships

```
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Post       │ morphToMany  │ Tag        │ tags       │
│ Video      │ morphToMany  │ Tag        │ tags       │
│ Tag        │ morphedByMany│ Post       │ posts      │
│ Tag        │ morphedByMany│ Video      │ videos     │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Post

# When prompted:
> Add relationship? Yes
> Select relationship type: morphToMany
> Enter related model name: Tag
> Enter morph name: taggable
> Add timestamps to pivot table? Yes
```

Generated code:

```php
// In Post.php
public function tags(): \Illuminate\Database\Eloquent\Relations\MorphToMany
{
    return $this->morphToMany(Tag::class, 'taggable')
        ->withTimestamps();
}

// In Tag.php (when creating or updating Tag model)
> Add relationship? Yes
> Select relationship type: morphedByMany
> Enter related model name: Post
> Enter morph name: taggable
> Add timestamps to pivot table? Yes

public function posts(): \Illuminate\Database\Eloquent\Relations\MorphedByMany
{
    return $this->morphedByMany(Post::class, 'taggable')
        ->withTimestamps();
}
```

## Working with Existing Models

### Adding Fields to Existing Models

```bash
php artisan rapids:model Product

# If Product exists:
> Model Product already exists.
> What would you like to do?
  > Add new migration for existing model
```

Adding a new field:

```bash
# Selected "Add new migration for existing model"
> Enter field name: discount
> Enter field type: float
> Field is nullable? Yes
```

This will generate a new migration to add the field to the existing table.

### Adding Relationships to Existing Models

```bash
# Selected "Add new migration for existing model"
> Enter field name: supplier_id
> Enter related model name for supplier_id: Supplier
> Select relationship type: belongsTo
> Select inverse relationship type: hasMany
```

This will:

1. Create a migration to add the supplier_id field
2. Add the relationship method to your Product model
3. Add the inverse relationship method to your Supplier model

## Complete Workflow Examples

### Blog System Example

```
Full Blog System Workflow:
┌────────────────────┬─────────────────────────────────────────────────────┐
│ Step               │ Process                                             │
├────────────────────┼─────────────────────────────────────────────────────┤
│ 1. Create User     │ php artisan rapids:model User                       │
│                    │ - Add name (string)                                 │
│                    │ - Add email (string)                                │
│                    │ - Add password (string)                             │
├────────────────────┼─────────────────────────────────────────────────────┤
│ 2. Create Category │ php artisan rapids:model Category                   │
│                    │ - Add name (string)                                 │
│                    │ - Add slug (string)                                 │
│                    │ - Add description (text)                            │
├────────────────────┼─────────────────────────────────────────────────────┤
│ 3. Create Post     │ php artisan rapids:model Post                       │
│                    │ - Add title (string)                                │
│                    │ - Add content (text)                                │
│                    │ - Add status (enum: draft,published,archived)       │
│                    │ - Add user_id (foreign key)                         │
│                    │   - belongsTo User / hasMany Posts                  │
│                    │ - Add category_id (foreign key)                     │
│                    │   - belongsTo Category / hasMany Posts              │
├────────────────────┼─────────────────────────────────────────────────────┤
│ 4. Create Tag      │ php artisan rapids:model Tag                        │
│                    │ - Add name (string)                                 │
│                    │ - Add relationship to Post (belongsToMany)          │
├────────────────────┼─────────────────────────────────────────────────────┤
│ 5. Create Comment  │ php artisan rapids:model Comment                    │
│                    │ - Add content (text)                                │
│                    │ - Add user_id (foreign key)                         │
│                    │   - belongsTo User / hasMany Comments               │
│                    │ - Add commentable_id & commentable_type             │
│                    │   - morphTo commentable                             │
└────────────────────┴─────────────────────────────────────────────────────┘
```

### Resulting Relationships

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Post       │ belongsTo    │ User       │ user       │
│ User       │ hasMany      │ Post       │ posts      │
│ Post       │ belongsTo    │ Category   │ category   │
│ Category   │ hasMany      │ Post       │ posts      │
│ Post       │ belongsToMany│ Tag        │ tags       │
│ Tag        │ belongsToMany│ Post       │ posts      │
│ Comment    │ belongsTo    │ User       │ user       │
│ User       │ hasMany      │ Comment    │ comments   │
│ Comment    │ morphTo      │ Commentable│ commentable│
│ Post       │ morphMany    │ Comment    │ comments   │
└────────────┴──────────────┴────────────┴────────────┘
```

## Advanced Features

### Advanced Foreign Key Constraints

When adding foreign key fields, you can specify the constraint type:

```bash
> Enter field name: user_id
> Enter related table name for user_id: users
> Select constraint type for user_id:
  > cascade
  > restrict
  > nullify
```

This generates different foreign key constraints:

- `cascade`: `->foreignId('user_id')->constrained('users')->cascadeOnDelete();`
- `restrict`: `->foreignId('user_id')->constrained('users')->restrictOnDelete();`
- `nullify`: `->foreignId('user_id')->constrained('users')->nullOnDelete();`

### Field Modifiers and Default Values

You can add modifiers and default values to your fields:

```bash
> Field is nullable? Yes
> Field is unique? Yes
> Add default value? Yes
> Enter default value: user@example.com
```

Generates: `$table->string('email')->nullable()->unique()->default('user@example.com');`

## PHP 8.2+ Features

RapidsModels now takes advantage of modern PHP features including:

```php
// Readonly class for data integrity
readonly class ModelDefinition
{
    public function __construct(
        private string $name,
        private array $fields = [],
        private array $relations = [],
        private bool $useFillable = true,
        private bool $useSoftDelete = false,
    ) {
    }
    
    // Immutable methods returning new instances
    public function withFields(array $fields): self
    {
        return new self(
            $this->name,
            $fields,
            $this->relations,
            $this->useFillable,
            $this->useSoftDelete
        );
    }
    
    // Rest of the class...
}
```

## Benefits of RapidsModels

```
Key Benefits:
┌─────────────────────┬───────────────────────────────────────────────────┐
│ Benefit             │ Description                                       │
├─────────────────────┼───────────────────────────────────────────────────┤
│ Time Savings        │ Reduces model setup time by 70-80%                │
│ Consistency         │ Ensures standard model structure and relationships │
│ Reduced Errors      │ Prevents common mistakes in relationships         │
│ Better Documentation│ Self-documented relationships and field types     │
│ Easier Maintenance  │ Standardized approach to model creation           │
│ Automatic Testing   │ Generated factories for testing all models        │
│ Full Relation Support│ All Laravel relation types including polymorphic │
│ Modern PHP Support  │ Takes advantage of PHP 8.2+ features             │
└─────────────────────┴───────────────────────────────────────────────────┘
```

## Troubleshooting

### Common Issues and Solutions

```
Common Issues:
┌─────────────────────────────┬───────────────────────────────────────────┐
│ Issue                       │ Solution                                  │
├─────────────────────────────┼───────────────────────────────────────────┤
│ Migration already exists    │ Use a unique name or timestamp            │
│ Model already exists        │ Choose "Add new migration" option         │
│ Missing related models      │ Create required models first              │
│ Invalid field types         │ Check supported types in documentation    │
│ Relationship errors         │ Ensure inverse relationships are correct  │
│ PHP version compatibility   │ Ensure PHP 8.2 or higher is installed    │
└─────────────────────────────┴───────────────────────────────────────────┘
```

## Best Practices

1. **Create models in dependency order**: Create parent models before child models
2. **Use consistent naming**: Follow Laravel conventions for table and column names
3. **Add relationships as needed**: Don't overcomplicate your initial model
4. **Use meaningful field names**: Be descriptive about the data the field holds
5. **Document special relationships**: Add comments for complex relationships
6. **Take advantage of PHP 8.2+ features**: Use readonly classes for data integrity
7. **Use typed properties**: Always specify return types for clarity

## Conclusion

RapidsModels transforms Laravel model creation from a multi-step process into a streamlined, interactive experience. By
automating the creation of models, migrations, factories, and seeders with proper relationships, it significantly
accelerates development while ensuring consistency across your application.

With full support for all Laravel relationships and modern PHP 8.2+ features, RapidsModels provides a comprehensive
solution for model creation and management in your Laravel projects.
