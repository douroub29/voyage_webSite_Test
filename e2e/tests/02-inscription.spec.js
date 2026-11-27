const { test, expect } = require('@playwright/test');
const { dernierEmail } = require('./helpers');

test('inscription, activation par e-mail (Mailpit) puis connexion', async ({ page, request }) => {
  const email = `e2e.${Date.now()}@example.com`;
  const motDePasse = 'Voyage@2026';

  await page.goto('/inscription');
  await page.locator('[name="registration_form[prenom]"]').fill('Camille');
  await page.locator('[name="registration_form[nom]"]').fill('Testeur');
  await page.locator('[name="registration_form[email]"]').fill(email);
  await page.locator('[name="registration_form[telephone]"]').fill('+33 6 12 34 56 78');
  await page.locator('[name="registration_form[dateNaissance]"]').fill('1995-05-12');
  await page.locator('[name="registration_form[plainPassword][first]"]').fill(motDePasse);
  await page.locator('[name="registration_form[plainPassword][second]"]').fill(motDePasse);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();

  await expect(page).toHaveURL(/\/verifier-email$/);
  await expect(page.locator('body')).toContainText(email);

  // Compte pas encore activé : la connexion est refusée
  await page.goto('/connexion');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(motDePasse);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.locator('body')).toContainText('pas encore activé');

  // L'e-mail d'activation arrive dans Mailpit
  const message = await dernierEmail(request, email);
  expect(message.Subject).toBe('Activez votre compte DreamTravel');
  const lien = message.HTML.match(/\/activation\/[a-f0-9]{64}/);
  expect(lien, "lien d'activation dans l'e-mail").not.toBeNull();

  await page.goto(lien[0]);
  await expect(page).toHaveURL(/\/connexion$/);
  await expect(page.locator('.flash-success')).toContainText('Compte activé');

  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(motDePasse);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('link', { name: 'Déconnexion' }).first()).toBeAttached();
});
