# Object Storage Service – Documentation Complète

## 1. Introduction
Ce document décrit un système complet de stockage d’objets (Object Storage) auto‑hébergé sur un serveur mutualisé LWS. L’objectif est de disposer d’une alternative stable et personnalisable aux buckets Supabase, tout en permettant :
- La création de projets indépendants.
- La création de buckets par projet.
- L’upload, la lecture, la suppression et la gestion d’objets.
- Une API REST sécurisée.
- Une interface d’administration simple.
- Une organisation et une architecture évolutives.

Ce système permet à tes backends Python, tes apps mobiles ou web, et n’importe quel autre client HTTP, d’utiliser un stockage d’objets fiable.

---

## 2. Objectifs du Système
### 2.1 Objectifs Fonctionnels
- Créer plusieurs **projets**.
- Associer à chaque projet :
  - un ou plusieurs **buckets**.
  - une **clé API** dédiée.
- Réaliser les opérations de stockage :
  - Upload
  - Download
  - Delete
  - Listing
- Générer des URL sécurisées (signées ou publiques).
- Fournir une API universelle compatible avec les apps backend/mobiles.
- Avoir une **interface d’administration web**.

### 2.2 Objectifs Techniques
- Compatible **PHP + MySQL**.
- Hébergement sur serveur mutualisé (limitations fortes acceptées).
- Architecture modulaire.
- Sécurité robuste (auth via API keys + signature d’URL).
- Possibilité d’évoluer vers un VPS plus tard.

---

## 3. Architecture Globale
```
/ (racine LWS)
│
├── /storage               # Contient les fichiers uploadés
│   ├── /project_1
│   │   ├── /bucket_1
│   │   ├── /bucket_2
│   ├── /project_2
│       ├── /bucket_X
│
├── /api                   # API REST PHP
│   ├── index.php          # Router principal
│   ├── /controllers       # Logique métier
│   ├── /services          # Services (auth, upload...)
│   ├── /config
│   ├── /utils
│
├── /admin                 # Interface d’administration
│   ├── index.php
│   ├── /assets
│
└── database.sql           # Schéma de la base de données
```

---

## 4. Base de Données
### 4.1 Tables Principales
#### **projects**
| champ | type | description |
|-------|-------|-------------|
| id | INT | Identifiant unique |
| name | VARCHAR | Nom du projet |
| api_key | VARCHAR | Clé API du projet |
| created_at | DATETIME | Date de création |

#### **buckets**
| champ | type | description |
|-------|-------|-------------|
| id | INT | Identifiant |
| project_id | INT | Lien avec `projects` |
| name | VARCHAR | Nom du bucket |
| is_public | BOOLEAN | Définit si les fichiers sont accessibles publiquement |

#### **objects**
| champ | type | description |
|-------|-------|-------------|
| id | INT | ID |
| bucket_id | INT | Lien bucket |
| filename | VARCHAR | Nom du fichier final |
| path | VARCHAR | Chemin physique |
| mime_type | VARCHAR | MIME détecté |
| size | INT | Taille fichier |
| created_at | DATETIME | Date upload |

---

## 5. API REST
### 5.1 Authentification
Chaque requête HTTP doit inclure :
```
Authorization: Bearer <API_KEY>
```
La clé est associée à un projet.

### 5.2 Endpoints
#### **POST /upload**
Upload d’un fichier vers un bucket.

Body (multipart) :
- file
- bucket

Réponse :
```
{
  "status": "success",
  "url": "https://domaine.com/storage/project/bucket/file.jpg"
}
```

#### **GET /object?bucket=...&file=...**
Download d’un fichier.

#### **DELETE /object**
Suppression d’un objet.

Body JSON :
```
{
  "bucket": "bucket_name",
  "file": "nom.jpg"
}
```

#### **GET /list?bucket=...**
Lister les fichiers d’un bucket.

---

## 6. Gestion des Permissions
Deux modes sont possibles :
### 6.1 Buckets Publics
Tous les fichiers sont accessibles par URL directe.

### 6.2 Buckets Privés
Les URL sont signées :
```
https://domaine.com/api/get-file?token=XXXX&expires=123456789
```
Un token HMAC SHA‑256 valide la requête.

---

## 7. Interface Admin
### 7.1 Fonctionnalités
- Création / suppression de projets.
- Gestion des buckets.
- Regénération des API Keys.
- Visualisation des erreurs et logs.
- Gestion de la visibilité des buckets.

### 7.2 Écrans
1. Dashboard général
2. Liste des projets
3. Page d’un projet :
   - API Key
   - Buckets
   - Statistiques
4. Page d’un bucket :
   - Liste des fichiers
   - Upload manuel
   - Suppression

---

## 8. Upload des Fichiers (Détails Techniques)
### 8.1 Sécurité
- Vérification type MIME.
- Taille maximale configurable.
- Génération automatique de noms uniques.
- Protection contre l’exécution (fichiers .php interdits).

### 8.2 Structure Physique
```
/storage/project_id/bucket_name/uuid.extension
```

### 8.3 Résilience
- Vérification existence dossier.
- Recréation automatique en cas de suppression.

---

## 9. Performance
### 9.1 Optimisations
- Cache navigateur sur les fichiers statiques.
- Utilisation d’`etag` pour les images.
- Compression automatique (si actif côté serveur).
- Désactivation de PHP dans `/storage`.

---

## 10. Sécurité Globale
- API Keys uniques générées via `random_bytes()`.
- Validation des extensions autorisées.
- Dossiers protégés via `.htaccess`.
- Quotas configurables par projet.
- Rate limiting simple via table SQL `requests_logs`.

---

## 11. Exemple d’Utilisation dans un Backend Python
```python
import requests

API_KEY = "clé_du_projet"
url = "https://ton-domaine.com/api/upload"

files = {"file": open("profil.jpg", "rb")}
data = {"bucket": "users"}

res = requests.post(url, files=files, data=data, headers={
    "Authorization": f"Bearer {API_KEY}"
})

print(res.json())
```

---

## 12. Cas d’Usage dans tes Projets
### 12.1 App sociale
- Photo de profil
- Photo de publication
- Stories

### 12.2 App food
- Images des plats
- Logos restaurants
- Bannières publicitaires

### 12.3 SaaS
- Images clients
- Logos entreprises
- Ressources statiques

---

## 13. Roadmap d’Amélioration
### Étape 1 — MVP
- Upload / Download / Delete
- Admin pour gérer projet + bucket
- API Key

### Étape 2 — Sécurité avancée
- URL signées
- Limites de quotas
- Logs API

### Étape 3 — Fonctionnalités avancées
- Versioning des fichiers
- CDN externe
- Optimisation automatique des images

### Étape 4 — Migration future
Vers un VPS + MinIO pour reproduire un vrai S3.

---

## 14. Conclusion
Ce système offre une solution robuste, flexible et totalement contrôlée pour remplacer les buckets Supabase tout en restant compatible avec un hébergement mutualisé. Il est pensé pour gérer plusieurs projets, isoler les données, sécuriser les accès et fournir une API universelle utilisable par toutes tes applications.

