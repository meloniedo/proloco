#!/bin/bash
# ========================================
# TEST BACKUP MYSQLDUMP
# ========================================

DB_NAME="proloco_bar"
DB_USER="edo"
DB_PASS="5054"
TEST_FILE="/tmp/test_backup.sql"

echo "=== TEST MYSQLDUMP ==="
echo ""

# Test 1: Connessione MySQL
echo "1. Test connessione MySQL..."
RESULT=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT 'OK'" 2>&1)
if [ "$RESULT" = "OK" ]; then
    echo "   ✅ Connessione OK"
else
    echo "   ❌ Errore: $RESULT"
fi

# Test 2: Conta record
echo ""
echo "2. Conta record nel database..."
VENDITE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM vendite" 2>&1)
SPESE=$(mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} -N -e "SELECT COUNT(*) FROM spese" 2>&1)
echo "   Vendite: $VENDITE"
echo "   Spese: $SPESE"

# Test 3: mysqldump SENZA nascondere errori
echo ""
echo "3. Test mysqldump (mostra errori)..."
mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > ${TEST_FILE} 2>&1
DUMP_EXIT=$?
DUMP_SIZE=$(stat -c%s ${TEST_FILE} 2>/dev/null || echo "0")

echo "   Exit code: $DUMP_EXIT"
echo "   Dimensione file: $DUMP_SIZE bytes"

if [ "$DUMP_SIZE" -lt 1000 ]; then
    echo "   ⚠️  File troppo piccolo! Contenuto:"
    cat ${TEST_FILE}
else
    echo "   ✅ File sembra OK"
    # Conta INSERT nel file
    INSERT_COUNT=$(grep -c "INSERT INTO" ${TEST_FILE} 2>/dev/null || echo "0")
    echo "   INSERT trovati: $INSERT_COUNT"
fi

# Test 4: Prova con sintassi alternativa
echo ""
echo "4. Test mysqldump con sintassi alternativa..."
mysqldump -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" > /tmp/test_backup2.sql 2>&1
DUMP2_SIZE=$(stat -c%s /tmp/test_backup2.sql 2>/dev/null || echo "0")
echo "   Dimensione file: $DUMP2_SIZE bytes"

# Test 5: Verifica se mysql client richiede password interattiva
echo ""
echo "5. Verifica versione MySQL..."
mysql --version

# Cleanup
rm -f ${TEST_FILE} /tmp/test_backup2.sql

echo ""
echo "=== TEST COMPLETATO ==="
