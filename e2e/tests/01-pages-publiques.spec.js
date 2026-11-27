const { test, expect } = require('@playwright/test');
const { ouvrirVoyage } = require('./helpers');

test.describe('Visiteur non connecté', () => {
  test("la page d'accueil présente les voyages à la une", async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/DreamTravel/i);
    await expect(page.getByRole('heading', { name: 'Voyages à la Une' })).toBeVisible();
  });

  test('le catalogue liste les 6 voyages et ouvre une fiche détaillée', async ({ page }) => {
    await page.goto('/voyages');
    await expect(page.getByRole('heading', { name: 'Nos Voyages' })).toBeVisible();
    await expect(page.locator('h5', { hasText: /Bali|Dubaï|Marrakech|New York|Rome|Paris/ })).toHaveCount(6);

    await ouvrirVoyage(page, 'Rome');
    await expect(page).toHaveURL(/\/voyages\/\d+$/);
    await expect(page.getByRole('heading', { name: /Rome Éternelle/ }).first()).toBeVisible();
  });

  test('réserver sans être connecté renvoie vers la connexion', async ({ page }) => {
    await page.goto('/voyages');
    await ouvrirVoyage(page, 'Bali');
    await page.locator('a.btn-gold[href="/connexion"]').first().click();
    await expect(page).toHaveURL(/\/connexion$/);
    await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();
  });

  test("l'espace admin est protégé", async ({ page }) => {
    await page.goto('/admin/reservations');
    await expect(page).toHaveURL(/\/admin\/connexion$/);
  });

  test('un mauvais mot de passe est refusé', async ({ page }) => {
    await page.goto('/connexion');
    await page.locator('input[name="email"]').fill('lucas@example.com');
    await page.locator('input[name="password"]').fill('mauvais-mot-de-passe');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/connexion$/);
    await expect(page.getByRole('link', { name: 'Déconnexion' })).toHaveCount(0);
  });
});
