<?php

declare(strict_types=1);

namespace DuneEtCreme\Validation;

/**
 * Shared contact-form validation (PHP side of the former zod schema).
 * Keep rules in sync with /assets/contact-validation.js (used by the frontend).
 *
 * Fields: nomComplet, telephone, email, message
 * (+ optional honeypot "website" is ignored here; handled by the API).
 */

/**
 * @param array<string, mixed> $input
 * @return array{
 *   success: bool,
 *   data?: array{nomComplet: string, telephone: string, email: string, message: string},
 *   errors?: array<string, string>
 * }
 */
function validateContact(array $input): array
{
    $errors = [];

    $nomComplet = trim((string) ($input['nomComplet'] ?? ''));
    $telephone = trim((string) ($input['telephone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));

    if ($nomComplet === '') {
        $errors['nomComplet'] = 'Le nom complet est requis.';
    } elseif (strlen($nomComplet) < 2) {
        $errors['nomComplet'] = 'Le nom doit contenir au moins 2 caractères.';
    }

    if ($telephone === '') {
        $errors['telephone'] = 'Le numéro de téléphone est requis.';
    } elseif (!isValidPhone($telephone)) {
        $errors['telephone'] = 'Veuillez entrer un numéro de téléphone valide.';
    }

    if ($email === '') {
        $errors['email'] = 'L’adresse email est requise.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Veuillez entrer une adresse email valide.';
    }

    if ($message === '') {
        $errors['message'] = 'Le message est requis.';
    } elseif (strlen($message) < 10) {
        $errors['message'] = 'Le message doit contenir au moins 10 caractères.';
    }

    if ($errors !== []) {
        return [
            'success' => false,
            'errors' => $errors,
        ];
    }

    return [
        'success' => true,
        'data' => [
            'nomComplet' => $nomComplet,
            'telephone' => $telephone,
            'email' => $email,
            'message' => $message,
        ],
    ];
}

/**
 * Basic international-friendly phone check: digits, spaces, +, (), -, min 8 digits.
 */
function isValidPhone(string $phone): bool
{
    if (!preg_match('/^[0-9+\s().-]{8,20}$/u', $phone)) {
        return false;
    }
    $digits = preg_replace('/\D+/', '', $phone);
    return is_string($digits) && strlen($digits) >= 8 && strlen($digits) <= 15;
}
