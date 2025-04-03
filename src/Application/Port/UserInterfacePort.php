<?php

namespace Rapids\Rapids\Application\Port;

interface UserInterfacePort
{
    public function getUserInput(string $prompt): string;

    public function displayMessage(string $message): void;

    public function confirmAction(string $message): bool;

    public function displayError(string $error): void;

    public function displaySuccess(string $message): void;
}
