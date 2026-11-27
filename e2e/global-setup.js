// Avant les tests : vérifie que l'application répond, puis remet la base dans l'état des fixtures
// (sinon les places restantes, réservations, etc. dépendraient des essais précédents).
// E2E_RESET=0 pour ne pas toucher à la base.
const { execSync } = require('node:child_process');
const path = require('node:path');

module.exports = async (config) => {
  const baseURL = config.projects[0].use.baseURL;
  try {
    const res = await fetch(baseURL);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
  } catch (e) {
    throw new Error(`L'application ne répond pas sur ${baseURL} (${e.message}). Lancez d'abord : docker compose up -d`);
  }

  if (process.env.E2E_RESET !== '0') {
    console.log('Réinitialisation de la base (fixtures)...');
    execSync('docker compose exec -T app php bin/console doctrine:fixtures:load --no-interaction', {
      cwd: path.resolve(__dirname, '..'),
      stdio: 'inherit',
    });
  }
};
