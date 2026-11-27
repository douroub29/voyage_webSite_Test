const { test, expect } = require('@playwright/test');
const { COMPTES } = require('./helpers');

test("l'administrateur confirme une réservation en attente", async ({ page }) => {
  await page.goto('/admin/connexion');
  await page.locator('input[name="email"]').fill(COMPTES.admin.email);
  await page.locator('input[name="password"]').fill(COMPTES.admin.motDePasse);
  await page.getByRole('button', { name: 'Accéder au tableau de bord' }).click();
  await expect(page).toHaveURL(/\/admin$/);

  await page.goto('/admin/reservations');
  const ligne = page.locator('tbody tr', { hasText: /en attente/i }).first();
  await expect(ligne, 'une réservation en attente dans les fixtures').toBeVisible();
  const reference = (await ligne.locator('td .fw-bold').first().innerText()).trim();

  await ligne.locator('a[href^="/admin/reservations/"]').click();
  await page.getByRole('button', { name: 'Confirmer la réservation' }).click();

  await expect(page.locator('body')).toContainText(`Réservation #${reference} confirmée.`);
  await expect(page.getByRole('button', { name: 'Confirmer la réservation' })).toHaveCount(0);
});
