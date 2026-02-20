#!/bin/bash
# ======================================================
# Script universel de génération et de traduction des fichiers de langues Dolibarr
# Auteur : Daxit Solutions
# Compatible Linux + macOS
# ======================================================

# Se place à la racine du module (que le script soit lancé depuis /script ou la racine)
SCRIPT_DIR="$( cd "$( dirname "$0" )" && pwd )"
cd "$SCRIPT_DIR/.." || exit 1

# Détection automatique du nom du module et du fichier source FR
MODULE_NAME=$(basename "$(pwd)")
MODULE_LANG_DIR="langs/fr_FR"

# Recherche du premier fichier .lang dans le dossier FR
if [ -d "$MODULE_LANG_DIR" ]; then
  MODULE_LANG_FILE=$(ls "$MODULE_LANG_DIR"/*.lang 2>/dev/null | head -n 1)
else
  MODULE_LANG_FILE=""
fi

if [ -z "$MODULE_LANG_FILE" ] || [ ! -f "$MODULE_LANG_FILE" ]; then
  echo "❌ Fichier source FR non trouvé dans $MODULE_LANG_DIR"
  echo "Assure-toi d'exécuter ce script depuis le dossier /script de ton module Dolibarr."
  exit 1
fi

TARGET_FILE=$(basename "$MODULE_LANG_FILE")

# Vérifie la présence de translate-shell
if ! command -v trans &> /dev/null; then
  echo "❌ Le programme 'translate-shell' n'est pas installé."
  echo "Installe-le avec :"
  echo " - sous Linux : sudo apt-get install translate-shell -y"
  echo " - sous macOS : brew install translate-shell"
  exit 1
fi

echo "------------------------------------------------------"
echo " 🌍 Module détecté : $MODULE_NAME"
echo " 📘 Fichier source : $MODULE_LANG_FILE"
echo "------------------------------------------------------"
echo " Génération + traduction automatique des langues Dolibarr"
echo "------------------------------------------------------"

LANGS="
en_US:en
es_ES:es
de_DE:de
it_IT:it
nl_NL:nl
pt_PT:pt
pt_BR:pt
ca_ES:ca
pl_PL:pl
cs_CZ:cs
sk_SK:sk
hu_HU:hu
ro_RO:ro
bg_BG:bg
hr_HR:hr
sr_RS:sr
el_GR:el
tr_TR:tr
ru_RU:ru
uk_UA:uk
ar_SA:ar
he_IL:he
zh_CN:zh-CN
zh_TW:zh-TW
ja_JP:ja
ko_KR:ko
vi_VN:vi
th_TH:th
da_DK:da
sv_SE:sv
no_NO:no
fi_FI:fi
et_EE:et
lv_LV:lv
lt_LT:lt
id_ID:id
ms_MY:ms
hi_IN:hi
bn_BD:bn
ta_IN:ta
sw_KE:sw
af_ZA:af
fa_IR:fa
is_IS:is
sl_SI:sl
bs_BA:bs
sq_AL:sq
tl_PH:tl
"

# Boucle principale
echo "$LANGS" | while IFS=":" read -r LANGCODE TARGET_LANG; do
  [ -z "$LANGCODE" ] && continue
  [ "$LANGCODE" = "fr_FR" ] && continue

  LANGDIR="langs/${LANGCODE}"
  mkdir -p "$LANGDIR"

  echo "# Auto-generated from fr_FR on $(date)" > "${LANGDIR}/${TARGET_FILE}"
  echo "CHARSET=UTF-8" >> "${LANGDIR}/${TARGET_FILE}"

  echo "🌐 Traduction vers ${LANGCODE} (${TARGET_LANG})..."

  while IFS='=' read -r key value; do
    if [[ -z "$key" || "$key" =~ ^# || "$key" == "CHARSET" ]]; then
      continue
    fi

    translated=$(trans -b -e google :${TARGET_LANG} "$value" 2>/dev/null)

    if [ -z "$translated" ]; then
      translated="$value"
      echo "⚠️  Pas de traduction pour '$value' → conservé tel quel"
    fi

    echo "${key}=${translated}" >> "${LANGDIR}/${TARGET_FILE}"
    sleep 0.3
  done < "$MODULE_LANG_FILE"

  echo "✅ Fichier généré : ${LANGDIR}/${TARGET_FILE}"
done

echo "------------------------------------------------------"
echo " ✅ Traductions terminées pour le module ${MODULE_NAME}"
echo "------------------------------------------------------"