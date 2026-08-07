/**
 * Shared contact-form validation (browser mirror of lib/validation/contact.php).
 * Keep rules in sync with the PHP file.
 */
(function (global) {
  "use strict";

  function isValidPhone(phone) {
    if (!/^[0-9+\s().-]{8,20}$/.test(phone)) return false;
    var digits = phone.replace(/\D+/g, "");
    return digits.length >= 8 && digits.length <= 15;
  }

  /**
   * @param {{ nomComplet?: string, telephone?: string, email?: string, message?: string }} input
   * @returns {{ success: boolean, data?: object, errors?: Record<string, string> }}
   */
  function validateContact(input) {
    var errors = {};
    var nomComplet = String((input && input.nomComplet) || "").trim();
    var telephone = String((input && input.telephone) || "").trim();
    var email = String((input && input.email) || "").trim();
    var message = String((input && input.message) || "").trim();

    if (!nomComplet) {
      errors.nomComplet = "Le nom complet est requis.";
    } else if (nomComplet.length < 2) {
      errors.nomComplet = "Le nom doit contenir au moins 2 caractères.";
    }

    if (!telephone) {
      errors.telephone = "Le numéro de téléphone est requis.";
    } else if (!isValidPhone(telephone)) {
      errors.telephone = "Veuillez entrer un numéro de téléphone valide.";
    }

    if (!email) {
      errors.email = "L’adresse email est requise.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = "Veuillez entrer une adresse email valide.";
    }

    if (!message) {
      errors.message = "Le message est requis.";
    } else if (message.length < 10) {
      errors.message = "Le message doit contenir au moins 10 caractères.";
    }

    if (Object.keys(errors).length) {
      return { success: false, errors: errors };
    }

    return {
      success: true,
      data: { nomComplet: nomComplet, telephone: telephone, email: email, message: message },
    };
  }

  global.DuneContactValidation = { validateContact: validateContact };
})(typeof window !== "undefined" ? window : globalThis);
