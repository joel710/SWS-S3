# Design Frontend — Object Storage Service (Ultra-détaillé)

> Ce document décrit, page par page, composant par composant, l'architecture UI/UX, la logique d'interaction et les spécifications visuelles pour le frontend du service de stockage d'objets hébergé sur LWS. Le design respecte la charte couleurs demandée : **jaune soleil**, **gris métallique**, **noir**, **blanc**.

---

## Table des matières
1. Principes visuels & Design Tokens
2. Système de layout global
3. Composants réutilisables (spec détaillée)
4. Pages & écrans — Structure et logique
5. Flux utilisateur (upload, accès public/privé, admin)
6. Chart & visualisations (style + interactions)
7. Accessibilité, responsive, performances
8. Assets, icons & recommandations techniques
9. Annexes : snippets CSS variables, Tailwind tokens, exemples d'ARIA

---

## 1. Principes visuels & Design Tokens
### 1.1 Palette principale
- **Jaune Soleil (Accent principal)** — `--color-sun: #FFC300` (ou `#FFD54A` pour plus doux)  
- **Gris Métallique (Surface / bordures / textes secondaires)** — `--color-metal: #7D848A` (ou `#8A8F93`)  
- **Noir (Texte principal / UI sombre)** — `--color-black: #0A0A0A`  
- **Blanc (Fond / surfaces)** — `--color-white: #FFFFFF`

> Remarques d'usage :
> - Le **jaune soleil** est réservé aux actions principales (CTA), aux accents des charts et aux états actifs. Ne pas l'utiliser comme fond massif (risque fatigue visuelle).
> - Le **gris métallique** sert pour les textes secondaires, les séparateurs, les ombres légères et les backgrounds alternatifs.
> - Le **noir** est utilisé pour le texte principal et les éléments à forte hiérarchie.
> - Le **blanc** domine les surfaces pour garder une lecture nette.

### 1.2 Typographie
- Police principale : `Inter` (ou `Roboto` si indisponible) — claire, très lisible.  
- Échelles : `h1 32–36px`, `h2 24–28px`, `h3 18–20px`, `body 14–16px`, `small 12px`.

### 1.3 Espacement & grille
- Grille principale : **12-col** flexible (desktop), gutter 20px.  
- Breakpoints :
  - `sm` ≤ 640px
  - `md` 641–1024px
  - `lg` 1025–1440px
  - `xl` > 1440px

### 1.4 Tokens CSS (exemples)
```css
:root{
  --color-sun:#FFC300;
  --color-metal:#7D848A;
  --color-black:#0A0A0A;
  --color-white:#FFFFFF;
  --radius-sm:6px;
  --radius-md:12px;
  --shadow-sm: 0 1px 3px rgba(10,10,10,0.06);
  --shadow-md: 0 8px 20px rgba(10,10,10,0.08);
}
```

---

## 2. Système de layout global
### 2.1 Header (top bar)
- Hauteur : 64px
- Contenu : logo (gauche), barre de recherche (centrée), actions (droite : notifications, user avatar, bouton + pour créer projet)
- Style : fond blanc, bord-bottom 1px solide `--color-metal` 10% opacité, ombre légère.

### 2.2 Sidebar (desktop) / Bottom nav (mobile)
- **Desktop** : Sidebar gauche fixe, largeur 260px, fond `#FFFFFF`, séparateur `--color-metal` 8%.
  - Sections : Dashboard, Projets, Buckets, Upload, Analytics, Admin
  - Comportement : collapsible (collapsed -> icons only, width 72px)
- **Mobile** : bottom navigation with 4 icons (Dashboard, Upload, Projects, Profile). Floating action button (FAB) jaune soleil centré pour upload.

### 2.3 Main Content
- Zone fluide, responsive. Utilise carte (card) containers avec `--shadow-sm` et `--radius-md`.
- Max-width 1280px centré.

---

## 3. Composants réutilisables (spec détaillée)
Chaque composant décrit avec HTML structure, props, states et interactions.

### 3.1 Card (File Card)
- Usage : afficher un fichier image (thumbnail), actions (download, delete, copy url)
- Dimensions : 220px (w) x 220px (h) en grille, responsive 3–4 colonnes desktop, 2 colonnes mobile.
- Structure :
  - thumbnail (cover, center crop)
  - meta area (filename, size)
  - overlay qui apparaît au hover (actions) : fond `rgba(10,10,10,0.5)` + icons blancs
- States : default, hover, selected (outline `--color-sun` 2px)

### 3.2 File Grid
- Lazy loading + infinite scroll.
- Masonry-like for varying heights but prefer **fixed-square** thumbs pour cohérence.
- Pagination / cursor-based API.

### 3.3 Upload Modal / Drawer
- Upload multiple files (drag & drop) + preview thumbnails.
- Auto-resize option: dropdown (original / 1024px / 800px / custom).
- Progress bar per file, global progress.
- Buttons : Cancel, Upload (primary yellow), Optimize & Upload (secondary metal border)

### 3.4 Top Search / Filter
- Search by filename, filter by type (jpg/png/webp), size slider, date range.
- Autocomplete suggestions (recent filenames)

### 3.5 Project Card (list)
- Name, bucket count, quota used (bar), created_at, actions (open, settings, regenerate key)
- Quota bar: background `--color-metal` 10%, filled `--color-sun` proportional

### 3.6 Navbar items
- Notifications with badge (sun yellow dot)
- Avatar menu -> Profile, Settings, Logout

---

## 4. Pages & écrans — Structure et logique
Pour chaque page : but, layout, composants, interactions.

### 4.1 Landing / Home (Admin)
**But** : vue d’ensemble des projets et usages.
**Layout** : header + 3 colonnes (summary cards, charts, recent activity)
**Composants** : Project List compact, Total storage usage chart (donut), Recent uploads feed
**Interactions** : click project -> Project Detail

### 4.2 Dashboard Projet
**But** : gérer un projet (buckets, clés, quotas)
**Sections** :
- Header projet : nom, api key (masquée) + copy/regenerate
- Quick actions : create bucket, upload file, settings
- Buckets grid : card par bucket (name, public/private badge, files count, used space, quick actions)
- Storage usage chart (bar + donut)

### 4.3 Bucket View (Listing des fichiers)
**But** : explorer & gérer fichiers
**Layout** : toolbar (search, filtre, upload), file grid, pagination
**Actions par fichier** : view → open lightbox, download, delete (avec confirmation), copy URL, rename
**Logic** : selection multiple (checkbox), bulk actions (download zip, delete, set public/private)

### 4.4 File Viewer / Lightbox
- Full-screen overlay
- Metadata panel (right) : filename, path, mime, size, date, direct url, signed url creator (durée), versions
- Image tools : download original, download optimized, set as profile image (API call)

### 4.5 Upload Flow (détaillé)
1. User ouvre upload (modal ou drag zone)
2. Sélection multiple → previews + validation (size/type)
3. Option resize/compress toggle
4. Start upload → progress bars
5. On success → toast (sun yellow) + file cards inserted in grid
6. On fail → toast rouge avec retry

### 4.6 Admin / Settings
- Global quotas, backups, logs, support tools
- Danger zone : supprimer projet (confirmation avec typing project name)

---

## 5. Flux utilisateur (upload, accès public/privé, admin)
### 5.1 Upload -> accès public (public bucket)
- Upload file -> stored at `/storage/projects/<p>/buckets/<b>/file` -> accessible via `https://domain/storage/...`
- UI montre le lien direct + bouton copy

### 5.2 Upload -> accès privé (signedurl)
- Les fichiers ne sont pas accessibles directement. L’utilisateur demande une signed url via l’API (expires param)
- UI : bouton `Generer URL temporaire` (modal) -> user saisit durée -> token visible + bouton copy

### 5.3 Admin créant projet/bucket
- Form rapide -> validation -> upon success, toast, bucket created & folder created server side

---

## 6. Chart & visualisations (style + interactions)
### 6.1 Palette charts
- Primary fill : `--color-sun` (#FFC300) — barres & donut
- Secondary / grid / axis : `--color-metal` (#7D848A)
- Background chart area : `--color-white`
- Tooltip bg : `--color-black` 95% with white text

### 6.2 Donut : Storage usage
- Centre: large % (h2), label (Used / Total)
- Segments : used (yellow), free (metallic grey)
- Interaction : hover segment -> tooltip with breakdown (images, profiles, logs)

### 6.3 Bar chart : Uploads over time
- Bars filled with gradient sun -> metal (subtle)
- Hover -> show exact bytes
- Click on bar day -> filter file listing by date

### 6.4 Table statistiques
- Rows: buckets, columns: files count, used space, last upload
- Sorting & search inline

---

## 7. Accessibilité, responsive, performances
### 7.1 Accessibilité
- Tous les boutons doivent avoir `aria-label`/`role` appropriés
- Keyboard navigation : tab order clair, modals focus trap
- Contrast : tests WCAG 2.1 — ajuster jaune si contraste insuffisant sur blanc (ajouter outline noir léger pour CTA)

### 7.2 Responsive behaviour
- Mobile : sidebar -> bottom nav; grid -> 2 cols; modals -> fullscreen
- Tablet : sidebar collapsible
- Desktop : full features

### 7.3 Performance
- Images servies en WebP si possible
- Thumbnails créés au moment de l'upload (600x600, 300x300)
- Lazy load `loading="lazy"` + intersection observer
- Cache control headers (long expiry pour assets immuables)

---

## 8. Assets, icons & recommandations techniques
- Icons : `lucide` ou `heroicons` (SVG inline)
- Illustrations : utiliser SVG simples, éviter images lourdes
- Animations : framer-motion (React) ou CSS transitions (prefers-reduced-motion respecté)
- Fonts : variable font Inter for performance

---

## 9. Annexes : snippets CSS variables, Tailwind tokens, exemples d'ARIA
### 9.1 CSS : variables et classes utiles
```css
:root{
  --sun: #FFC300;
  --metal: #7D848A;
  --black: #0A0A0A;
  --white: #FFFFFF;
}
.btn-primary{background:var(--sun);color:var(--black);border-radius:10px;padding:10px 16px;font-weight:600}
.card{background:var(--white);box-shadow:var(--shadow-sm);border-radius:var(--radius-md)}
```

### 9.2 Tailwind mapping (exemple)
- `bg-sun` → `bg-[#FFC300]`
- `text-metal` → `text-[#7D848A]`
- `ring-sun` → `ring-[#FFC300]`

### 9.3 Exemples ARIA
```html
<button aria-label="Télécharger le fichier" title="Télécharger">...</button>
<div role="dialog" aria-modal="true" aria-labelledby="uploadTitle">...</div>
```

---

## 10. Checklist d'implémentation (MVP -> Prod)
- [ ] Créer layout & components réutilisables
- [ ] Auth API key flow
- [ ] Upload modal (drag & drop) + preview
- [ ] File Grid + lazy load
- [ ] File Viewer + metadata
- [ ] Signed URLs
- [ ] Admin pages (create project/bucket)
- [ ] Charts & analytics
- [ ] Tests accessibilité
- [ ] Tests de charge basics (concurrent upload)

---

*Fin du document — design frontend détaillé prêt pour implémentation.*

