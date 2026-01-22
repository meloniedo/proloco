#!/bin/bash
# ========================================
# FIX DUPLICATI PRODOTTI
# Esegui con: sudo bash fix_duplicati.sh
# ========================================

echo "🔧 Pulizia prodotti duplicati..."

DB_NAME="proloco_db"

# Elimina duplicati mantenendo solo il primo per ogni nome
mysql ${DB_NAME} -e "
DELETE p1 FROM prodotti p1
INNER JOIN prodotti p2 
WHERE p1.id > p2.id AND p1.nome = p2.nome;
"

# Aggiungi constraint UNIQUE se non esiste
mysql ${DB_NAME} -e "
ALTER TABLE prodotti ADD UNIQUE INDEX unique_nome (nome);
" 2>/dev/null

# Conta prodotti rimasti
COUNT=$(mysql ${DB_NAME} -N -e "SELECT COUNT(*) FROM prodotti;")

echo ""
echo "✅ Fatto! Prodotti nel database: $COUNT"
echo ""
echo "Ricarica l'app nel browser per vedere le modifiche."
