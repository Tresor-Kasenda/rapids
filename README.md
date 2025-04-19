# RapidsModels Documentation

## Overview

RapidsModels is a Laravel package that simplifies model creation by generating a complete model ecosystem with one
command.
Create models, migrations, factories, and seeders through a simple interactive process.

## Installation

```bash
composer require rapids/rapids
```

## Basic Usage

```bash
php artisan rapids:model Product
```

The interactive assistant will guide you through:
. Adding fields with types
. Managing foreign keys and relationships
. Customizing options

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

## Working with Existing Models

RapidsModels can be seamlessly integrated into existing Laravel projects.
When working with existing models, the package provides several features to enhance and extend your application's model
ecosystem.

### Adding Fields to Existing Models

When you run rapids:model on an existing model name:

```bash
php artisan rapids:model Product
```

The system will detect the existing model and provide options:

1. **Add new migration for existing model** : Create a migration to add fields to an existing table
2. **Update existing model file** : Add relationships or methods to the model class
2. **Generate additional components** : Create missing factory or seeder files

### Creating Migrations for Existing Tables

```bash
php artisan rapids:model Product
> Model Product already exists.
> What would you like to do? Add new migration for existing model
> Enter field name: sale_price
> Enter field type: decimal
> Field is nullable? Yes
```

This creates a migration that adds the new field to your existing table, preserving all current data.

### Adding Relationships Between Existing Models

```bash
php artisan rapids:model Order
> Model Order already exists.
> What would you like to do? Update existing model file
> Add relationship? Yes
> Enter related model name: Product
> Select relationship type: belongsToMany
```

This will update both model files with the appropriate relationship methods and generate a migration for any required
pivot tables.

This will:

1. Create a migration to add the supplier_id field
2. Add the relationship method to your Product model
3. Add the inverse relationship method to your Supplier model

## Contributing to RapidsModels

### How to Contribute

We welcome contributions from the community! Here's how you can help:

```markdown
## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add some amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request
```

### Development Setup

```bash
# Clone the repository
git clone https://github.com/your-username/rapids-models.git

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit
```

## Support the Developer

If you find RapidsModels useful in your projects, consider supporting the development:

- **Star the repository** on GitHub
- **Share your experience** on social media using #RapidsModels
- **Donate** via [GitHub Sponsors](https://github.com/sponsors/Tresor-Kasenda)
- **Hire me** for your Laravel projects

## Vision and Roadmap

RapidsModels aims to streamline Laravel application development by eliminating repetitive boilerplate creation. Our
vision includes:

### Short-term Goals

- Support for more complex field types
- Enhanced relationship management
- Custom template support for generated files

### Long-term Goals

- Visual model builder interface
- Integration with popular Laravel packages
- Database reverse engineering capabilities

## Community and Support

- **Documentation**: [https://github.com/Tresor-Kasenda/rapids/README.md](https://github.com/Tresor-Kasenda/rapids)
- **Issues**: [GitHub Issues](https://github.com/Tresor-Kasenda/rapids/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Tresor-Kasenda/rapids/discussions)
- **Twitter**: [@TresorKasenda](https://x.com/TresorKasenda)

## License

RapidsModels is open-sourced software licensed under the [MIT license](LICENSE.md).

---

Made with ❤️ by [Tresor Kasenda](https://github.com/Tresor-Kasenda)
