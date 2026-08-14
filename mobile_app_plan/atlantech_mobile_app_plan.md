# Plan d'application mobile - AtlanTech Shop

Date : 26 juin 2026  
Projet : AtlanTech Shop  
Plateformes visees : Android en priorite, iOS ensuite  
Objectif : transformer le site e-commerce AtlanTech en application mobile rapide, claire et adaptee au marche haitien.

## 1. Vision

L'application mobile AtlanTech doit permettre aux clients de trouver rapidement des produits tech, comparer les prix, commander, payer, suivre leurs commandes et recevoir des offres. Elle doit aussi renforcer la confiance autour de la livraison, du paiement MonCash et du service client.

## 2. Public cible

- Clients particuliers qui achetent telephones, accessoires, ordinateurs et produits electroniques.
- Clients qui utilisent surtout leur smartphone pour naviguer et commander.
- Clients en Haiti qui veulent voir les prix en USD et HTG.
- Clients qui veulent payer via MonCash, cash a la livraison ou autre methode locale.

## 3. Modules principaux

### Client

- Accueil avec promotions, categories, recherche et produits populaires.
- Catalogue avec filtres : categorie, marque, prix, disponibilite, promotions.
- Fiche produit : images, prix USD/HTG, stock, description, avis, produits similaires.
- Panier : quantite, code promo, estimation livraison, total clair.
- Checkout : adresse, methode livraison, paiement, confirmation.
- Compte client : profil, adresses, commandes, favoris, notifications.
- Suivi commande : statut, paiement, preparation, livraison, historique.
- Support : contact rapide, WhatsApp/appel/email, FAQ.

### Admin leger mobile, optionnel phase 2

- Vue commandes recentes.
- Mise a jour statut livraison/preparation.
- Notifications internes.
- Scan ou recherche commande.

## 4. Navigation recommandee

Navigation basse a 5 onglets :

- Accueil
- Boutique
- Favoris
- Commandes
- Compte

Le panier reste accessible depuis l'en-tete ou un bouton flottant lorsque necessaire.

## 5. Parcours utilisateur principal

1. Le client ouvre l'application.
2. Il cherche un produit ou choisit une categorie.
3. Il consulte la fiche produit.
4. Il ajoute au panier.
5. Il se connecte ou cree un compte.
6. Il choisit adresse, livraison et paiement.
7. Il confirme la commande.
8. Il suit la commande jusqu'a la livraison.

Voir l'image : `05-user-flow.svg`.

## 6. Ecrans cles

### Accueil

Objectif : donner acces rapidement a la recherche, aux categories et aux offres.

Image : `01-home-screen.svg`

Elements :

- Barre de recherche visible.
- Bannier promotionnelle.
- Categories horizontales.
- Produits populaires.
- Bouton panier dans l'en-tete.

### Fiche produit

Objectif : convaincre et rassurer avant l'achat.

Image : `02-product-screen.svg`

Elements :

- Image produit large.
- Prix USD et HTG.
- Badge stock/promotion.
- Boutons Ajouter au panier et Acheter maintenant.
- Description courte.
- Produits similaires.

### Panier et checkout

Objectif : rendre la validation simple et sans confusion.

Image : `03-cart-checkout-screen.svg`

Elements :

- Liste produits.
- Code promo.
- Frais livraison.
- Total clair.
- Choix paiement : MonCash, cash, carte si disponible.
- CTA confirmer.

### Compte et commandes

Objectif : centraliser les informations client.

Image : `04-account-orders-screen.svg`

Elements :

- Profil client.
- Commandes recentes.
- Statuts visuels.
- Adresses.
- Favoris.
- Support.

## 7. Design system

Palette suggeree :

- Bleu AtlanTech : #0B5FFF
- Bleu fonce : #081B33
- Vert succes : #19A974
- Orange promo : #FF8A00
- Fond clair : #F6F8FB
- Texte principal : #1F2937

Style :

- Interface claire, rapide, professionnelle.
- Cartes produits simples.
- Prix tres visibles.
- Boutons larges et faciles a toucher.
- Peu de texte long dans les ecrans d'achat.

## 8. Fonctionnalites prioritaires

### Phase 1 - MVP

- Authentification client.
- Catalogue produits.
- Recherche et filtres.
- Fiche produit.
- Panier.
- Checkout.
- Commandes client.
- Favoris.
- Integration MonCash si l'API existante est stable.

### Phase 2

- Notifications push.
- Suivi livraison detaille.
- Avis produits.
- Chat/support.
- Coupons personnalises.
- Mode admin livraison/preparation.

### Phase 3

- Recommandations personnalisees.
- Programme fidelite.
- Wallet/credit client.
- Analytics mobile avancees.

## 9. Architecture technique recommandee

Option recommandee : Flutter ou React Native.

Si l'equipe est plus a l'aise avec JavaScript :

- React Native + Expo
- API PHP existante a normaliser en JSON
- Auth par token securise
- Stockage local minimal

Si l'equipe veut une experience tres stable Android/iOS :

- Flutter
- API REST PHP
- Gestion stricte des etats panier/commande

Backend :

- Creer une couche API mobile separee sous `/api/mobile/`.
- Ne pas exposer directement les scripts web existants.
- Retourner JSON standardise : `success`, `data`, `message`, `errors`.
- Ajouter rate limiting sur login, checkout et endpoints sensibles.

## 10. Endpoints API a prevoir

- `POST /api/mobile/auth/login`
- `POST /api/mobile/auth/register`
- `POST /api/mobile/auth/logout`
- `GET /api/mobile/products`
- `GET /api/mobile/products/{id}`
- `GET /api/mobile/categories`
- `POST /api/mobile/cart/items`
- `PUT /api/mobile/cart/items/{id}`
- `DELETE /api/mobile/cart/items/{id}`
- `POST /api/mobile/orders`
- `GET /api/mobile/orders`
- `GET /api/mobile/orders/{id}`
- `POST /api/mobile/payments/moncash/init`
- `GET /api/mobile/settings`

## 11. Securite

- HTTPS obligatoire.
- Tokens d'authentification avec expiration.
- Validation serveur de tous les prix, stocks, promos et totaux.
- Ne jamais faire confiance au total envoye par l'app.
- Protection contre brute force login.
- Logs d'audit checkout/paiement.
- Masquer les erreurs techniques cote client.

## 12. Livrables recommandes

- Maquettes Figma ou Canva a partir de ces ecrans.
- Specification API mobile.
- Backlog MVP.
- Prototype cliquable.
- Application Android beta interne.
- Tests checkout et paiement.

