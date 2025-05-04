# Changelog

Toutes les modifications notables apportées au projet Rapids seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2025-05-04

### Ajouté
- Support de compatibilité avec Laravel 12
- Tests de compatibilité pour assurer le fonctionnement avec Laravel 10, 11, et 12

### Modifié
- Mise à jour des dépendances pour inclure Laravel 12 explicitement
- Mise à jour de orchestra/testbench pour support Laravel 12

## [2.0.0] - 2025-05-04

### Ajouté
- Support complet de toutes les relations Laravel, y compris:
  - HasOneThrough avec paramétrage complet
  - HasManyThrough avec options avancées
  - Relations polymorphiques (morphOne, morphMany, morphTo)
  - Relations polymorphiques many-to-many (morphToMany, morphedByMany)
- Support complet pour PHP 8.2, 8.3 et 8.4
- Implémentation des classes readonly pour une meilleure intégrité des données
- Ajout de documentations complètes avec exemples
- Ajout du support des timestamps pour les tables pivot
- Personnalisation des noms de tables pivot
- Personnalisation des noms morphiques
- Support avancé des contraintes de clés étrangères

### Modifié
- Refactorisation majeure pour utiliser les fonctionnalités PHP 8.2+
- Amélioration des générateurs de relations
- Documentation complètement révisée
- Amélioration des interfaces utilisateur et des prompts
- Amélioration des messages d'erreur et des validations

### Corrigé
- Correction des problèmes de compatibilité avec Laravel 10 et 11
- Correction des problèmes avec les migrations de clés étrangères
- Amélioration de la génération des relations inverses
- Correction de divers problèmes mineurs

## [1.0.0] - 2024-01-15

### Ajouté
- Première version publique
- Support pour les relations de base (hasOne, belongsTo, hasMany, belongsToMany)
- Génération de modèles avec attributs
- Génération de migrations
- Génération de factories et seeders