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
└────────────┴──────────────────────────┴───────────────────────────┘
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

## Relationship Examples

### 1. Belongs To Relationship

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Product    │ belongsTo    │ Category   │ category   │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Product

# When prompted:
> Enter field name: category_id
> Enter related model name for category_id: Category
> Select relationship type: belongsTo
> Select inverse relationship type: hasMany
```

Generated code:

```php
// In Product.php
public function category()
{
    return $this->belongsTo(Category::class);
}

// In Category.php
public function products()
{
    return $this->hasMany(Product::class);
}
```

### 2. Has One Relationship

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ User       │ hasOne       │ Profile    │ profile    │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Profile

# When prompted:
> Enter field name: user_id
> Enter related model name for user_id: User
> Select relationship type: belongsTo
> Select inverse relationship type: hasOne
```

Generated code:

```php
// In Profile.php
public function user()
{
    return $this->belongsTo(User::class);
}

// In User.php
public function profile()
{
    return $this->hasOne(Profile::class);
}
```

### 3. Has Many Relationship

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Author     │ hasMany      │ Book       │ books      │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Book

# When prompted:
> Enter field name: author_id
> Enter related model name for author_id: Author
> Select relationship type: belongsTo
> Select inverse relationship type: hasMany
```

Generated code:

```php
// In Book.php
public function author()
{
    return $this->belongsTo(Author::class);
}

// In Author.php
public function books()
{
    return $this->hasMany(Book::class);
}
```

### 4. Belongs To Many Relationship

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Post       │ belongsToMany│ Tag        │ tags       │
└────────────┴──────────────┴────────────┴────────────┘
```

**How to create:**

```bash
php artisan rapids:model Post

# Add regular fields, then:
> Add relationship? Yes
> Enter related model name: Tag
> Select relationship type: belongsToMany
```

Generated code:

```php
// In Post.php
public function tags()
{
    return $this->belongsToMany(Tag::class);
}

// In Tag.php
public function posts()
{
    return $this->belongsToMany(Post::class);
}

// Pivot migration also created automatically
```

### 5. Has Many Through Relationship

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Country    │ hasManyThrough│ Post      │ posts      │
└────────────┴──────────────┴────────────┴─────────��──┘
```

**How to create:**

```bash
# Create all models first, then:
> Add relationship? Yes
> Enter related model name: Post
> Select relationship type: hasManyThrough
> Enter intermediate model: User
```

Generated code:

```php
// In Country.php
public function posts()
{
    return $this->hasManyThrough(Post::class, User::class);
}
```

### 6. Polymorphic Relationships

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Comment    │ morphTo      │ Commentable│ commentable│
│ Post       │ morphMany    │ Comment    │ comments   │
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
public function commentable()
{
    return $this->morphTo();
}

// In Post.php (create separately)
public function comments()
{
    return $this->morphMany(Comment::class, 'commentable');
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
├────────────────────┼─────��───────────────────────────────────────────────┤
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
└────────────────────┴──────────────────────────────────────────────────��──┘
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

### Foreign Key Constraints

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

### Field Modifiers

You can add modifiers to your fields:

```bash
> Field is nullable? Yes
> Field is unique? Yes
```

Generates: `$table->string('email')->nullable()->unique();`

## Common Operations Examples

### Creating a New Model with Basic Fields

```bash
php artisan rapids:model Product
> Enter field name: name
> Enter field type: string
> Field is nullable? No
> Enter field name: description
> Enter field type: text
> Field is nullable? Yes
> Enter field name: price
> Enter field type: decimal
> Field is nullable? No
> Add another field? No
```

### Creating a Model with Relationships

```bash
php artisan rapids:model Order
> Enter field name: number
> Enter field type: string
> Field is nullable? No
> Enter field name: status
> Enter field type: enum
> Enter values (comma separated): pending,processing,completed,cancelled
> Field is nullable? No
> Enter field name: user_id
> Enter related model name for user_id: User
> Select relationship type: belongsTo
> Select inverse relationship type: hasMany
```

### Adding Fields to an Existing Model

```bash
php artisan rapids:model Product
> Model Product already exists.
> What would you like to do? Add new migration for existing model
> Enter field name: is_featured
> Enter field type: boolean
> Field is nullable? No
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
└─────────────────────────────┴───────────────────────────────────────────┘
```

## Best Practices

1. **Create models in dependency order**: Create parent models before child models
2. **Use consistent naming**: Follow Laravel conventions for table and column names
3. **Add relationships as needed**: Don't overcomplicate your initial model
4. **Use meaningful field names**: Be descriptive about the data the field holds
5. **Document special relationships**: Add comments for complex relationships

## Conclusion

RapidsModels transforms Laravel model creation from a multi-step process into a streamlined, interactive experience. By
automating the creation of models, migrations, factories, and seeders with proper relationships, it significantly
accelerates development while ensuring consistency across your application.
