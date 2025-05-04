<?php

declare(strict_types=1);

namespace Rapids\Rapids\Contract;

interface PromptServiceInterface
{
    /**
     * Affiche un prompt pour saisir du texte
     *
     * @param string $label Le libellé du prompt
     * @param string $placeholder Texte d'exemple
     * @param string $default Valeur par défaut
     * @return string La valeur saisie par l'utilisateur
     */
    public function text(string $label, string $placeholder = '', string $default = ''): string;

    /**
     * Affiche un menu de sélection
     *
     * @param string $label Le libellé du prompt
     * @param array $options Les options disponibles
     * @param string|null $default Valeur par défaut sélectionnée
     * @return string L'option sélectionnée par l'utilisateur
     */
    public function select(string $label, array $options, ?string $default = null): string;

    /**
     * Affiche un menu de recherche
     *
     * @param string $label Le libellé du prompt
     * @param array $options Les options disponibles
     * @param string|null $default Valeur par défaut
     * @return string L'option sélectionnée par l'utilisateur
     */
    public function search(string $label, array $options, ?string $default = null): string;

    /**
     * Affiche une demande de confirmation
     *
     * @param string $label Le libellé du prompt
     * @param bool $default Valeur par défaut
     * @return bool La réponse de l'utilisateur
     */
    public function confirm(string $label, bool $default = false): bool;

    /**
     * Affiche un tableau d'informations
     *
     * @param array $headers Les en-têtes du tableau
     * @param array $data Les données du tableau
     */
    public function table(array $headers, array $data): void;

    /**
     * Affiche un message d'information
     *
     * @param string $message Le message à afficher
     */
    public function info(string $message): void;

    /**
     * Affiche un message d'erreur
     *
     * @param string $message Le message d'erreur
     */
    public function error(string $message): void;

    /**
     * Affiche un message de succès
     *
     * @param string $message Le message de succès
     */
    public function success(string $message): void;
}
