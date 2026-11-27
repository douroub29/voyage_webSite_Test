const { expect } = require('@playwright/test');

const MAILPIT = process.env.E2E_MAILPIT_URL || 'http://localhost:8025';

/** Comptes créés par les fixtures (src/DataFixtures/AppFixtures.php). */
const COMPTES = {
  admin: { email: 'admin@dreamtravel.com', motDePasse: 'Admin@1234' },
  lucas: { email: 'lucas@example.com', motDePasse: 'Client@1234' },
};

/** Connexion par le vrai formulaire client /connexion. */
async function connecterClient(page, { email, motDePasse }) {
  await page.goto('/connexion');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(motDePasse);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('link', { name: 'Déconnexion' }).first()).toBeAttached();
}

/** Depuis /voyages, ouvre la fiche du voyage dont le titre contient le texte donné. */
async function ouvrirVoyage(page, titre) {
  const carte = page.locator(
    `xpath=//h5[contains(., "${titre}")]/ancestor::*[.//a[starts-with(@href, "/voyages/")]][1]`
  );
  await carte.locator('a[href^="/voyages/"]').first().click();
}

/** Attend l'e-mail reçu par Mailpit pour ce destinataire et renvoie son contenu. */
async function dernierEmail(request, destinataire) {
  let id;
  await expect
    .poll(async () => {
      const res = await request.get(`${MAILPIT}/api/v1/search`, { params: { query: `to:"${destinataire}"` } });
      id = (await res.json()).messages?.[0]?.ID;
      return id;
    }, { message: `aucun e-mail reçu pour ${destinataire}`, timeout: 15_000 })
    .toBeTruthy();
  return (await request.get(`${MAILPIT}/api/v1/message/${id}`)).json();
}

module.exports = { COMPTES, connecterClient, ouvrirVoyage, dernierEmail };
