# M-Files Integration Project

Application web permettant d'interagir avec M-Files via son API REST.

## Prérequis

- PHP 8.1+
- Composer
- Node.js 16+
- M-Files Server installé localement
- Un vault M-Files configuré

## Installation

### Backend (Laravel)

1. Cloner le repo
2. Installer les dépendances :
```bash
cd backend
composer install
```

3. Copier .env.example vers .env et configurer :
```
MFILES_BASE_URL=http://localhost/REST
MFILES_VAULT_ID={votre-vault-guid}
```

4. Générer la clé d'application :
```bash
php artisan key:generate
```

5. Démarrer le serveur :
```bash
php artisan serve
```

### Frontend (React)

1. Installer les dépendances :
```bash
cd frontend
npm install
```

2. Démarrer le serveur de développement :
```bash
npm run dev
```

## Utilisation

1. Accéder à http://localhost:5173
2. Se connecter avec vos identifiants M-Files
3. Gérer les clients et documents via l'interface

## Structure du projet

- `/backend` : API Laravel
  - `/app/Services/MFilesService.php` : Service principal M-Files
  - `/app/Http/Controllers` : Contrôleurs API
  - `/routes/api.php` : Routes API

- `/frontend` : Application React
  - `/src/pages` : Composants de pages
  - `/src/services` : Services API
