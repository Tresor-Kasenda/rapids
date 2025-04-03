## Enhanced Rapids Package Documentation

## Overview

Rapids is a Laravel package that helps you analyze and visualize your application's model structure. The package
provides a command to audit your Laravel models, examine their fields, and display relationships between different
tables.

## Installation

Install the package via Composer:

```bash
composer require rapids/rapids
```

## Usage

### Auditing Models

The package provides the `rapids:audit-models` command to analyze your Laravel models:

```bash
php artisan rapids:audit-models
```

By default, this command scans models in the `app/Models` directory. You can specify a custom path using the `--path`
option:

```bash
php artisan rapids:audit-models --path=app/Domain/Models
```

### Output

The command generates a comprehensive audit of your models with the following information:

1. **Summary table** - Lists all models with their field and relation counts
2. **Detailed model information** - For each model:
    - Field details (name, type, relation status)
    - Relation details (name, type, related model)
3. **Relationship diagram** - Shows connections between tables:
    - Source table
    - Relationship type
    - Target table
    - Connection method

### Example Output

When you run the command on a project with models like `User`, `Role`, `Service`, etc., you'll see:

```
Model Audit Results:
┌───────┬─────────────┬────────────────┐
│ Model │ Fields Count│ Relations Count│
├───────┼─────────────┼────────────────┤
│ User  │ 12          │ 5              │
│ Role  │ 4           │ 2              │
│ ...   │ ...         │ ...            │
└───────┴─────────────┴────────────────┘

Model: User
Fields:
┌─────────────────┬──────────┬─────────────┐  
│ Name            │ Type     │ Is Relation │
├─────────────────┼──────────┼─────────────┤
│ id              │ integer  │ No          │
│ name            │ string   │ No          │
│ email           │ string   │ No          │
│ ...             │ ...      │ ...         │
└─────────────────┴──────────┴─────────────┘

Relations:
┌────────────┬─────────────┬────────────┐
│ Name       │ Type        │ Related To │
├────────────┼─────────────┼────────────┤
│ role       │ belongsTo   │ Role       │
│ sales      │ hasMany     │ Sale       │
│ ...        │ ...         │ ...        │
└────────────┴─────────────┴────────────┘

[...]

Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ User       │ belongsTo    │ Role       │ role       │
│ User       │ hasMany      │ Sale       │ sales      │
│ ...        │ ...          │ ...        │ ...        │
└────────────┴──────────────┴────────────┴────────────┘
```

## Use Cases

### 1. Database Architecture Review

When joining a project with a complex database structure, running:

```bash
php artisan rapids:audit-models
```

Helps you quickly understand:

- How many models exist in the application
- What fields each model contains
- How models are related to each other

### 2. Refactoring Preparation

Before refactoring a section of your application, run:

```bash
php artisan rapids:audit-models --path=app/Models/Finance
```

This gives you visibility into:

- Which models will be affected by your changes
- What relationships might break during refactoring
- Hidden dependencies between models

### 3. Documentation Generation

Generate documentation for your database schema by capturing Rapids' output:

```bash
php artisan rapids:audit-models > docs/database-schema.txt
```

### 4. Relationship Validation

Ensure relationships are properly defined by examining the relationship diagram section:

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ User       │ belongsTo    │ Role       │ role       │
└────────────┴──────────────┴────────────┴────────────┘
```

This table helps identify:

- Missing inverse relationships
- Incorrectly defined relationships
- Orphaned models without relationships

I'll add clear documentation for auditing a specific model that's helpful for both junior and senior developers:

## Single Model Audit

### Basic Usage

To audit a specific model, use the `--path` option:

```bash
php artisan rapids:audit-models --path=app/Models/User.php
```

### Understanding the Results

When auditing a specific model like `User.php`, Rapids generates a detailed analysis table:

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ User       │ belongsTo    │ Role       │ role       │
└────────────┴──────────────┴────────────┴────────────┘
```

### Example Output

When you run the command on a specific model, you'll see:

```
Table Components Explained:
┌───────────────┬───────────────────────────┬────────────┐
│ Column        │ Description               │ Example    │
├───────────────┼───────────────────────────┼────────────┤
│ From Table    │ The model being audited   │ User       │
│ Relation Type │ Laravel relationship type │ belongsTo  │
│ To Table      │ Related model             │ Role       │
│ Via Method    │ Method name in the model  │ role       │
└───────────────┴───────────────────────────┴────────────┘
```

### 1. **Reading the Table**:

    - Each row shows one relationship
    - `From Table` is your current model
    - `Relation Type` tells you how models are connected
    - `Via Method` is the function name to use in your code

### 2. **Performance Analysis**:

```bash
# Get detailed performance metrics
php artisan rapids:audit-models --path=app/Models/User.php
```

### 3. **Relationship Check Points**:

    - Inverse relationships existence
    - Foreign key constraints
    - Index coverage
    - Eager loading opportunities

### 4. **Advanced Usage**:

```bash
   # Export detailed analysis
php artisan rapids:audit-models \
    --path=app/Models/User.php \
    > user-model-audit.json
```

### Common Issues

```
┌────────────────┬──────────────────────────────────────────┬───────────────────────────────┐
│ Issue          │              Meaning                     │            Solution           │
├────────────────┼──────────────────────────────────────────┼───────────────────────────────┤
│ Missing Method │ Relationship defined but no method exists│ Add method to model           │
│ Wrong Type     │ Incorrect relationship type              │ Review relationship definition│
│ Missing Inverse│ One-sided relationship                   │ Add reciprocal relationship   │
└────────────────┴──────────────────────────────────────────┴───────────────────────────────┘
```

## Relationship Examples

### Example: User Model Relationships

The `User` model has multiple relationships that Rapids detects:

```php
class User extends Authenticatable
{
    /**
     * @return BelongsTo<Role, User>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasMany<Sale, User>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
    
    /**
     * @return BelongsTo<Service, User>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
    
    /**
     * @return HasMany<Report, User>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
    
    /**
     * @return HasMany<Appointment, User>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
```

Rapids will identify all these relationships and display them in the output.

### Example: Many-to-Many Relationship

For a `belongsToMany` relationship:

```php
class Post extends Model
{
    /**
     * @return BelongsToMany<Tag, Post>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

Rapids will show this in the relationship diagram:

```
Table Relationships:
┌────────────┬──────────────┬────────────┬────────────┐
│ From Table │ Relation Type│ To Table   │ Via Method │
├────────────┼──────────────┼────────────┼────────────┤
│ Post       │ belongsToMany│ Tag        │ tags       │
└────────────┴──────────────┴────────────┴────────────┘
```

## Best Practices

### Documenting Relationships

To get the most out of Rapids, document your relationships with PHPDoc:

```php
/**
 * @return BelongsTo<Role, User>
 */
public function role(): BelongsTo
{
    return $this->belongsTo(Role::class);
}
```

### Reviewing Complex Systems

For applications with many models, focus on specific directories:

```bash
php artisan rapids:audit-models --path=app/Models/Accounting
php artisan rapids:audit-models --path=app/Models/HumanResources
```

## Tips

1. **Run Rapids regularly**: As your application grows, run Rapids regularly to keep track of your database structure.

2. **Compare outputs**: Save previous outputs to compare how your model structure changes over time.

3. **Review relationship counts**: Unusually high relationship counts might indicate overly complex models that could
   benefit from refactoring.

4. **Check for "Unknown" relationships**: These indicate relationships that Rapids couldn't properly analyze and might
   need better documentation.

## Benefits

- Quickly understand your database structure
- Discover relationships between models
- Validate model field configurations
- Aid in documentation efforts
- Support database refactoring
- Identify potential performance bottlenecks in your data relationships
- Facilitate knowledge transfer to new team members

## Requirements

- PHP 8.0 or higher
- Laravel 8.0 or higher
