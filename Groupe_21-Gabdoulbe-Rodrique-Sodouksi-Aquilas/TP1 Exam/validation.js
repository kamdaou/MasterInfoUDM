/**
 * Leçon 2 – Validation côté client du formulaire d'inscription étudiant.
 *
 * Principe : chaque champ est associé à une règle (fonction qui retourne
 * un message d'erreur, ou une chaîne vide si la valeur est valide).
 * Cette approche « pilotée par les données » évite la répétition de code
 * et facilite l'ajout de nouveaux champs.
 */
'use strict';

const form = document.getElementById('formEtudiant');

const REGEX = {
  matricule: /^[A-Za-z0-9][A-Za-z0-9-]{1,19}$/,
  nom: /^[A-Za-zÀ-ÖØ-öø-ÿ' -]{2,50}$/,
  email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
  telInternational: /^\+(?:[1-9]\d{0,2})(?:[\s.-]?\d){6,14}$/
};

const regles = {
  idEtu:       v => REGEX.matricule.test(v) ? '' : 'Matricule invalide (ex. 21A0123 ou G21A0123).',
  nomEtu:      v => REGEX.nom.test(v) ? '' : 'Nom invalide (2 à 50 lettres).',
  prenomEtu:   v => REGEX.nom.test(v) ? '' : 'Prénom invalide (2 à 50 lettres).',
  sexeEtu:     v => ['Masculin', 'Féminin', 'Autre'].includes(v) ? '' : 'Veuillez choisir le sexe.',
  dateNaiss:   v => verifierDateNaissance(v),
  lieuNaiss:   v => v.length >= 2 ? '' : 'Le lieu de naissance est obligatoire.',
  emailEtu:    v => REGEX.email.test(v) ? '' : 'Adresse email invalide.',
  numTelEtu:   v => REGEX.telInternational.test(v) ? '' : 'Numéro invalide (format international attendu : +237 6XX XX XX XX).',
  regEtu:      v => v.length >= 2 ? '' : 'La région est obligatoire.',
  departEtu:   v => v.length >= 2 ? '' : 'Le département est obligatoire.',
  arrondisEtu: v => v.length >= 2 ? '' : "L'arrondissement est obligatoire.",
  mentionEtu:  v => v !== '' ? '' : 'Veuillez choisir une mention.',
  cycleEtu:    v => v !== '' ? '' : 'Veuillez choisir un cycle.',
  ParcoursEtu: v => v.length >= 2 ? '' : 'Le parcours est obligatoire.'
};

function verifierDateNaissance(valeur) {
  if (!valeur) return 'La date de naissance est obligatoire.';
  const naissance = new Date(valeur);
  const aujourdhui = new Date();
  let age = aujourdhui.getFullYear() - naissance.getFullYear();
  const anniversairePasse =
    aujourdhui.getMonth() > naissance.getMonth() ||
    (aujourdhui.getMonth() === naissance.getMonth() && aujourdhui.getDate() >= naissance.getDate());
  if (!anniversairePasse) age--;
  return (age >= 15 && age <= 70) ? '' : 'Âge incohérent (entre 15 et 70 ans).';
}

function validerChamp(champ) {
  if (champ.type === 'hidden' || !regles[champ.name]) return true;

  const estRadio = champ.type === 'radio';
  const valeur = estRadio ? form.elements[champ.name].value : champ.value.trim();
  const message = regles[champ.name](valeur);
  const conteneur = champ.closest('.champ');

  if (estRadio) {
    form.querySelectorAll(`input[name="${champ.name}"]`)
      .forEach(r => r.classList.toggle('invalide', message !== ''));
    conteneur.classList.toggle('groupe-invalide', message !== '');
  } else {
    champ.classList.toggle('invalide', message !== '');
  }
  conteneur.querySelector('.erreur').textContent = message;
  return message === '';
}

form.querySelectorAll('input, select').forEach(champ => {
  if (champ.type === 'hidden') return;
  if (champ.type === 'radio') {
    champ.addEventListener('change', () => validerChamp(champ));
    return;
  }
  champ.addEventListener('blur', () => validerChamp(champ));
  champ.addEventListener('input', () => { if (champ.classList.contains('invalide')) validerChamp(champ); });
});

form.addEventListener('submit', evenement => {
  evenement.preventDefault();
  const champs = [...form.querySelectorAll('input:not([type="hidden"]), select')];
  const toutValide = champs.map(validerChamp).every(Boolean);
  const resultat = document.getElementById('resultat');

  if (toutValide) {
    form.submit();
  } else {
    resultat.textContent = '';
    form.querySelector('.invalide')?.focus();
  }
});
