# Stock Notifier - Automated Stock Alert System

## Description

Module Dolibarr pour l'envoi automatique d'alertes e-mail en temps réel lors de mouvements de stock critiques.

## Fonctionnalités

- **Déclenchement automatique** : Alerte envoyée à chaque mouvement de stock (entrée, sortie, correction, transfert)
- **Détection intelligente** : Vérifie si le stock est ≤ au seuil d'alerte après chaque mouvement
- **Anti-spam** : Une seule alerte par produit jusqu'à restauration du stock
- **E-mail HTML** : Message formaté avec informations détaillées du produit
- **Configuration flexible** : Options d'exclusion (produits hors vente/achat)
- **Réinitialisation** : Bouton pour réinitialiser toutes les alertes

## Installation

1. Télécharger le module ZIP
2. Installer via Dolibarr : Accueil → Modules/Applications
3. Activer le module "Stock Notifier"
4. Configurer l'adresse e-mail : Configuration → Stock Notifier → Paramètres

## Fonctionnement

Le module utilise un **trigger** qui s'exécute automatiquement sur les actions :
- `STOCK_MOVEMENT` : Mouvement de stock standard
- `STOCK_CORRECT` : Correction de stock
- `STOCK_TRANSFER` : Transfert entre entrepôts

Aucune configuration cron nécessaire - tout est automatique !

## Logique d'alerte

1. Mouvement de stock détecté → Trigger activé
2. Vérification du stock actuel vs seuil d'alerte
3. Si stock ≤ seuil ET alerte non déjà envoyée → Envoi e-mail
4. Si stock > seuil → Réinitialisation de l'alerte pour ce produit

## Structure du module

```
stocknotifier/
├── class/
│   ├── notifierconfig.class.php    (Configuration)
│   └── stockalert.class.php        (Logique alertes)
├── core/
│   ├── modules/stocknotifier/
│   │   └── modStocknotifier.class.php  (Descripteur module)
│   └── triggers/
│       └── interface_99_modStocknotifier_Stocknotifiertriggers.class.php
├── admin/
│   ├── setup.php                   (Configuration)
│   └── about.php                   (À propos)
├── lib/
│   └── stocknotifier.lib.php       (Fonctions helpers)
├── langs/fr_FR/
│   └── stocknotifier.lang          (Traductions)
├── descriptor.xml
└── README.md
```

## Compatibilité

- Dolibarr 19.0+
- PHP 8.2+

## Support

Daxit Solutions  
https://daxit.be

## Licence

GPL v3
