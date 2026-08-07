/**
 * Wires #contact "Nous Contacter" form → POST /api/contact.php
 * Uses shared rules from contact-validation.js
 */
(function () {
  "use strict";

  var API_URL = "/api/contact.php";
  var BOUND = "data-dune-contact-bound";

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function fieldMap(form) {
    var nameInput = qs(form, 'input[type="text"]');
    var phoneInput = qs(form, 'input[type="tel"]');
    var emailInput = qs(form, 'input[type="email"]');
    var messageInput = qs(form, "textarea");
    return {
      name: nameInput,
      phone: phoneInput,
      email: emailInput,
      message: messageInput,
    };
  }

  function ensureErrorSlot(fieldEl, key) {
    if (!fieldEl) return null;
    var wrap = fieldEl.parentElement;
    if (!wrap) return null;
    var existing = wrap.querySelector('[data-error-for="' + key + '"]');
    if (existing) return existing;
    var p = document.createElement("p");
    p.className = "contact-field-error";
    p.setAttribute("data-error-for", key);
    p.setAttribute("role", "alert");
    p.hidden = true;
    wrap.appendChild(p);
    return p;
  }

  function ensureHoneypot(form) {
    var existing = form.querySelector('input[name="website"]');
    if (existing) return existing;
    var input = document.createElement("input");
    input.type = "text";
    input.name = "website";
    input.id = "contact-website";
    input.autocomplete = "off";
    input.tabIndex = -1;
    input.setAttribute("aria-hidden", "true");
    input.className = "contact-honeypot";
    form.appendChild(input);
    return input;
  }

  function ensureBanner(form) {
    var existing = form.querySelector("[data-contact-banner]");
    if (existing) return existing;
    var banner = document.createElement("div");
    banner.setAttribute("data-contact-banner", "1");
    banner.className = "contact-form-banner";
    banner.hidden = true;
    banner.setAttribute("role", "alert");
    form.insertBefore(banner, form.firstChild);
    return banner;
  }

  function clearErrors(form) {
    form.querySelectorAll("[data-error-for]").forEach(function (el) {
      el.hidden = true;
      el.textContent = "";
    });
    var banner = form.querySelector("[data-contact-banner]");
    if (banner) {
      banner.hidden = true;
      banner.textContent = "";
      banner.classList.remove("is-error", "is-success");
    }
    Object.values(fieldMap(form)).forEach(function (el) {
      if (el) el.classList.remove("contact-input-invalid");
    });
  }

  function showFieldErrors(form, errors) {
    var map = {
      nomComplet: "name",
      telephone: "phone",
      email: "email",
      message: "message",
    };
    var fields = fieldMap(form);
    Object.keys(errors || {}).forEach(function (apiKey) {
      var domKey = map[apiKey] || apiKey;
      var input = fields[domKey];
      var slot = ensureErrorSlot(input, domKey);
      if (slot) {
        slot.textContent = errors[apiKey];
        slot.hidden = false;
      }
      if (input) input.classList.add("contact-input-invalid");
    });
  }

  function showBanner(form, message, type) {
    var banner = ensureBanner(form);
    banner.textContent = message;
    banner.hidden = false;
    banner.classList.remove("is-error", "is-success");
    banner.classList.add(type === "success" ? "is-success" : "is-error");
  }

  function setLoading(form, loading) {
    var btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    if (loading) {
      btn.disabled = true;
      btn.dataset.originalLabel = btn.textContent;
      btn.textContent = "Envoi en cours…";
      btn.classList.add("is-loading");
    } else {
      btn.disabled = false;
      btn.textContent = btn.dataset.originalLabel || "Envoyer";
      btn.classList.remove("is-loading");
    }
  }

  function readPayload(form) {
    var f = fieldMap(form);
    var honeypot = form.querySelector('input[name="website"]');
    return {
      nomComplet: f.name ? f.name.value : "",
      telephone: f.phone ? f.phone.value : "",
      email: f.email ? f.email.value : "",
      message: f.message ? f.message.value : "",
      website: honeypot ? honeypot.value : "",
    };
  }

  function showSuccess(form) {
    var success = document.createElement("div");
    success.className = "contact-form-success";
    success.setAttribute("role", "status");
    success.innerHTML =
      "<p class=\"contact-form-success-title\">Merci ! Votre message a bien été envoyé.</p>" +
      "<p class=\"contact-form-success-text\">Nous vous répondrons dans les plus brefs délais.</p>";
    form.replaceWith(success);
  }

  async function onSubmit(event) {
    var form = event.currentTarget;
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === "function") {
      event.stopImmediatePropagation();
    }

    clearErrors(form);

    var payload = readPayload(form);
    var validate =
      (window.DuneContactValidation && window.DuneContactValidation.validateContact) ||
      null;

    if (validate) {
      var clientResult = validate(payload);
      if (!clientResult.success) {
        showFieldErrors(form, clientResult.errors);
        showBanner(form, "Veuillez corriger les champs indiqués.", "error");
        return;
      }
    }

    setLoading(form, true);
    try {
      var res = await fetch(API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      var rawText = await res.text();
      var data = {};
      try {
        data = rawText ? JSON.parse(rawText) : {};
      } catch (_) {
        showBanner(
          form,
          "Le serveur d’envoi n’est pas disponible (PHP requis). Sur Cap Connect, déployez l’API ; en local, lancez php -S.",
          "error"
        );
        return;
      }

      if (res.ok && data.success) {
        showSuccess(form);
        return;
      }

      if (data.errors) {
        showFieldErrors(form, data.errors);
        showBanner(
          form,
          data.error || "Veuillez corriger les champs indiqués.",
          "error"
        );
      } else {
        showBanner(
          form,
          data.error || "Impossible d’envoyer votre message. Réessayez plus tard.",
          "error"
        );
      }
    } catch (_) {
      showBanner(
        form,
        "Erreur réseau. Vérifiez votre connexion et réessayez.",
        "error"
      );
    } finally {
      if (document.body.contains(form)) setLoading(form, false);
    }
  }

  function bindForm(form) {
    if (!form || form.getAttribute(BOUND) === "1") {
      ensureHoneypot(form);
      return;
    }
    form.setAttribute(BOUND, "1");
    ensureHoneypot(form);
    ensureBanner(form);
    var fields = fieldMap(form);
    ensureErrorSlot(fields.name, "name");
    ensureErrorSlot(fields.phone, "phone");
    ensureErrorSlot(fields.email, "email");
    ensureErrorSlot(fields.message, "message");
    form.addEventListener("submit", onSubmit, true);
  }

  function scan() {
    var form = document.querySelector("#contact form");
    if (form) bindForm(form);
  }

  function start() {
    scan();
    var obs = new MutationObserver(function () {
      scan();
    });
    obs.observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
