const { test, expect } = require('@playwright/test');
const { COMPTES, connecterClient, ouvrirVoyage } = require('./helpers');

test.describe('Client connecté', () => {
  test.beforeEach(async ({ page }) => {
    await connecterClient(page, COMPTES.lucas);
  });

  test('réservation complète : formulaire, récapitulatif, paiement, confirmation', async ({ page }) => {
    await page.goto('/voyages');
    await ouvrirVoyage(page, 'Rome');
    await page.getByRole('link', { name: 'Réserver maintenant' }).click();
    await expect(page).toHaveURL(/\/reservation\/nouvelle\/\d+$/);

    await page.locator('[name="reservation_form[nombrePersonnes]"]').fill('2');
    await page.locator('[name="reservation_form[hotel]"]').selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Continuer vers le récapitulatif' }).click();

    await expect(page).toHaveURL(/\/reservation\/recap\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Récapitulatif' })).toBeVisible();
    await expect(page.locator('body')).toContainText('(8 nuits)');

    await page.getByText('PayPal', { exact: true }).click();
    await page.getByRole('button', { name: /Confirmer et payer/ }).click();

    await expect(page).toHaveURL(/\/reservation\/confirmation\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Réservation Confirmée !' })).toBeVisible();
    const reference = (await page.locator('.text-gold.fw-bold.fs-5').innerText()).trim();
    expect(reference).not.toBe('');

    // La réservation apparaît dans l'espace client
    await page.goto('/mon-compte/reservations');
    await expect(page.locator('body')).toContainText(reference);
  });

  test("le contrôle des places s'affiche dès la saisie (JavaScript)", async ({ page }) => {
    await page.goto('/voyages');
    await ouvrirVoyage(page, 'Bali');
    await page.getByRole('link', { name: 'Réserver maintenant' }).click();

    const champ = page.locator('[name="reservation_form[nombrePersonnes]"]');
    const erreur = page.locator('#erreur-places');
    const places = Number(await champ.getAttribute('data-places'));

    await champ.fill(String(places + 1));
    await expect(erreur).toBeVisible();
    await expect(erreur).toContainText(`il ne reste que ${places} place(s)`);
    await expect(champ).toHaveClass(/is-invalid/);

    // L'envoi est bloqué côté navigateur
    await page.getByRole('button', { name: 'Continuer vers le récapitulatif' }).click();
    await expect(page).toHaveURL(/\/reservation\/nouvelle\/\d+$/);

    await champ.fill('1');
    await expect(erreur).toBeHidden();
  });
});
