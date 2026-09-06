# Contacts Sync

*[English version](README.md)*

Application Laravel permettant à chaque utilisateur de se connecter en SSO Microsoft 365 et de
synchroniser des personnes de l'annuaire de son organisation (Microsoft Graph) vers **son propre
dossier Contacts Outlook personnel** - manuellement, automatiquement via des règles par
département/poste, ou en synchronisant l'annuaire complet en un clic.

![Écran de connexion](docs/screenshots/login.png)

## Pourquoi ?

Le but de ce projet est simple : avoir les fiches de contact de l'entreprise sur son téléphone
**sans effort**, sans jamais les ajouter ni les mettre à jour soi-même.

Cette application dépose automatiquement les contacts de l'entreprise dans Outlook. Et comme
l'application **Microsoft Outlook** sur le téléphone peut synchroniser ses contacts avec le
répertoire du téléphone, ces fiches (nom, poste, téléphone...) apparaissent directement dans les
contacts du téléphone - reconnues par l'identification d'appel, le composeur, les messages -
sans que personne n'ait à les saisir à la main, et toujours à jour automatiquement.

Pour que ça marche, il suffit d'activer un réglage dans l'appli Outlook (une seule fois, sur
chaque téléphone) :

- **iPhone** : Outlook → photo de profil → ⚙️ Réglages → Contacts → activer
  **Enregistrer dans les contacts**.
- **Android** : Outlook → menu ☰ → ⚙️ Réglages → Contacts → activer
  **Synchroniser les contacts**.

## Fonctionnement

- SSO Microsoft Entra ID (Azure AD) via Laravel Socialite.
- Cache local de l'annuaire M365 (`directory_users`), rafraîchi par pagination Graph `/users`
  (toutes les 15 min max). Seules les personnes ayant un numéro **mobile ou fixe** exploitable
  sont conservées - les autres sont ignorées puis retirées si elles avaient été importées
  auparavant.
- Trois façons de choisir qui synchroniser, cumulables, avec priorité claire :
  1. **Sélection manuelle** par personne (`sync_selections`, source `manual`) - un opt-out manuel
     l'emporte toujours, même si une règle ou "tout synchroniser" matche cette personne.
  2. **Règles automatiques** (`sync_rules`) par département et/ou poste (correspondance exacte ou
     "contient").
  3. **"Synchroniser tout l'annuaire"** (`users.sync_all_enabled`) : bascule tout ou rien par
     utilisateur, prioritaire sur les règles (les règles deviennent alors redondantes, ce qui est
     signalé dans l'interface).
- Un changement de prénom, nom, poste, département ou téléphone dans l'annuaire M365 met à jour
  la fiche contact Outlook déjà créée (pas de doublon). Une personne qui quitte l'annuaire, ou
  dont le contact a été supprimé manuellement dans Outlook, voit son contact supprimé ou recréé
  automatiquement au prochain sync.
- Toute action utilisateur (toggle d'une personne, création/modification/suppression d'une règle,
  activation de "tout synchroniser") déclenche une synchronisation immédiate en tâche de fond, en
  plus du cycle automatique toutes les 15 minutes.
- Si le token Microsoft d'un utilisateur ne peut plus être renouvelé (refresh token expiré ou
  révoqué côté Microsoft), l'application le détecte (soit à l'échec du rafraîchissement, soit dès
  qu'un appel Graph est rejeté avec un 401 même si le token semblait encore valide localement) et
  affiche un bandeau "reconnexion requise" sur toutes les pages tant que l'utilisateur ne s'est pas
  reconnecté.
- Historique complet des créations/mises à jour/suppressions/erreurs (`sync_logs`), visible dans
  l'onglet Historique.
- Interface disponible en **français et anglais**, avec un sélecteur de langue (page de connexion
  et barre latérale). La préférence est enregistrée sur le compte utilisateur (`users.locale`) une
  fois connecté, et persiste donc d'une session à l'autre ; avant connexion, elle n'est gardée que
  dans le navigateur (`localStorage`).

## Prérequis

- Un tenant Microsoft Entra ID (Azure AD) où vous pouvez enregistrer une application et accorder
  le consentement admin.
- Docker et Docker Compose (méthode d'installation recommandée), **ou** PHP 8.3+, Composer et
  Node.js pour une installation locale.

## 1. Créer l'App Registration Azure AD (Microsoft Entra ID)

1. Sur [entra.microsoft.com](https://entra.microsoft.com) (ou portal.azure.com > Microsoft Entra ID),
   allez dans **App registrations** > **New registration**.
2. Nom : `Contacts Sync` (ou ce que vous voulez).
3. Type de compte : selon votre besoin (généralement *Accounts in this organizational directory only*
   pour un tenant unique).
4. **Redirect URI** : type `Web`, valeur `https://votre-domaine.com/auth/callback`
   (ou `http://localhost:8080/auth/callback` en local).
5. Une fois créée, notez :
   - **Application (client) ID** → `MICROSOFT_CLIENT_ID`
   - **Directory (tenant) ID** → `MICROSOFT_TENANT_ID`
6. **Certificates & secrets** > **New client secret** → notez la valeur (visible une seule fois)
   → `MICROSOFT_CLIENT_SECRET`.
7. **API permissions** > **Add a permission** > **Microsoft Graph** > **Delegated permissions**,
   ajoutez :
   - `openid`, `profile`, `email`, `offline_access
   - `User.Read`
   - `User.Read.All` *(nécessite le consentement d'un administrateur du tenant - c'est elle qui
     permet de lister les comptes de l'annuaire*
   - `Contacts.ReadWrite`
8. Cliquez sur **Grant admin consent for {tenant}** (nécessite un rôle admin du tenant). Sans ce
   consentement, la lecture de l'annuaire complet (`User.Read.All`) échouera pour les utilisateurs
   non-admin.

Aucune permission **Application** (client credentials) n'est nécessaire : tout est en **Delegated**,
chaque utilisateur synchronisant avec son propre compte/token.

## 2. Installation avec Docker (recommandé)

L'image publiée (`ghcr.io/jturazzi/contacts-sync`) embarque l'application avec toutes ses
dépendances PHP et les assets frontend déjà compilés - aucun outillage PHP/Node local requis.

1. Téléchargez `docker-compose.yml` depuis ce dépôt (ou copiez-le depuis la racine du repo).
2. Renseignez le bloc `environment:` des trois services (`app`, `queue`, `cron`) avec vos propres
   valeurs :

   ```yaml
   environment:
     APP_URL: "https://votre-domaine"
     APP_KEY: "base64:...."          # voir ci-dessous - ne pas sauter cette étape
     MICROSOFT_CLIENT_ID: "..."
     MICROSOFT_CLIENT_SECRET: "..."
     MICROSOFT_REDIRECT_URI: "https://votre-domaine/auth/callback"
     MICROSOFT_TENANT_ID: "..."
   ```

   > **`APP_KEY` est obligatoire et doit rester stable.** Les access et refresh tokens sont
   > stockés **chiffrés** en base avec cette clé. Générez-en une **une seule fois** - par exemple
   > avec `docker run --rm ghcr.io/jturazzi/contacts-sync php artisan key:generate --show` - puis
   > réutilisez exactement la même valeur sur chaque service et à chaque redéploiement. Si la clé
   > change un jour, tous les tokens Microsoft stockés deviennent illisibles et chaque utilisateur
   > doit se reconnecter.

3. Par défaut l'application utilise **SQLite**, stocké dans le volume `storage-data` : aucun
   conteneur de base de données séparé n'est nécessaire. Pour utiliser MySQL/MariaDB à la place,
   ajoutez les variables `DB_*` habituelles (`DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`,
   `DB_USERNAME`, `DB_PASSWORD`) au bloc `environment:` des trois services, pointant vers votre
   propre serveur de base de données.
4. Démarrez le tout :

   ```bash
   docker compose up -d
   ```

   Le conteneur `app` sert l'application (FrankenPHP) et exécute automatiquement les migrations
   en attente au démarrage. Le conteneur `queue` traite les jobs de synchronisation, et le
   conteneur `cron` exécute le scheduler Laravel (qui déclenche `sync:run-all` toutes les
   15 minutes) - **les deux sont indispensables** pour qu'une synchronisation ait réellement lieu ;
   le conteneur `app` seul ne fait que servir les pages.
5. Ouvrez `http://localhost:8080` (ou le port mappé via la variable d'environnement `PORT_APP`,
   ex : `PORT_APP=9000 docker compose up -d`).

### Mise à jour

```bash
docker compose pull
docker compose up -d
```

Les migrations en attente s'exécutent automatiquement au redémarrage du conteneur `app`.

## 3. Installation locale pour le développement (sans Docker)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build   # ou `npm run dev` en développement
php artisan serve
```

Renseignez dans votre `.env` local les mêmes variables `MICROSOFT_*` décrites ci-dessus.

### Synchronisation en arrière-plan (déploiement local / bare-metal uniquement)

Si vous n'utilisez pas les services Docker `queue` et `cron`, deux processus doivent tourner en
continu :

- **Le worker de queue**, qui exécute les jobs de synchronisation (le job gère lui-même ses
  tentatives et son timeout, ne pas les surcharger en ligne de commande) :
  ```bash
  php artisan queue:work
  ```
  À superviser avec `supervisor` ou un service `systemd` en production, pour qu'il redémarre seul
  en cas de crash ou de fermeture de terminal - un worker géré à la main est fragile : toute
  interruption en plein job (Ctrl+C, fermeture de session) laisse la synchronisation à moitié
  faite.

- **Le scheduler Laravel**, qui déclenche `sync:run-all` toutes les 15 minutes pour tous les
  utilisateurs ayant une sélection, une règle active, ou "tout synchroniser" activé. Ajoutez cette
  entrée crontab :
  ```
  * * * * * cd /chemin/vers/contacts-sync && php artisan schedule:run >> /dev/null 2>&1
  ```

Le toggle d'une personne dans l'annuaire et la création/modification d'une règle passent tous par
la queue (asynchrone) - la page répond immédiatement, la synchronisation réelle s'exécute en
arrière-plan dès que le worker la traite.

## Tests

```bash
php artisan test
```

Couvre la logique de correspondance des règles et de "tout synchroniser" (`RuleMatcher`), l'import
de l'annuaire avec filtre téléphone et pagination (`DirectoryService`, Graph simulé via
`Http::fake()`), et le moteur de synchronisation (`ContactSyncService`) : création, mise à jour sur
changement de champ, suppression après départ de l'annuaire, recréation d'un contact supprimé
manuellement dans Outlook, et priorité d'une désactivation manuelle sur une règle ou sur
"tout synchroniser".

## Licence

[MIT](LICENSE).
