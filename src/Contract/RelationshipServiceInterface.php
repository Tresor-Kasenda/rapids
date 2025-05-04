<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

interface RelationshipServiceInterface
{
    /**
     * Génère les méthodes de relation pour un modèle donné
     * 
     * @param string $modelName Nom du modèle
     * @param array $relations Liste des relations
     * @return string Code PHP pour les méthodes de relation
     */
    public function generateRelationMethods(string $modelName, array $relations): string;

    /**
     * Génère une méthode de relation spécifique
     * 
     * @param string $modelName Nom du modèle courant
     * @param string $relationType Type de relation (hasOne, belongsTo, etc.)
     * @param string $methodName Nom de la méthode à générer
     * @param string $relatedModel Nom du modèle associé
     * @return string Code PHP pour la méthode de relation
     */
    public function generateRelationMethod(string $modelName, string $relationType, string $methodName, string $relatedModel): string;

    /**
     * Détermine le nom approprié pour une méthode de relation
     * 
     * @param string $relationType Type de relation (hasOne, belongsTo, etc.)
     * @param string $modelName Nom du modèle associé
     * @return string Nom de la méthode de relation
     */
    public function getRelationMethodName(string $relationType, string $modelName): string;
}
