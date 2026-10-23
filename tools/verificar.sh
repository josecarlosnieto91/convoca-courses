#!/usr/bin/env bash
#
# Verificación del plugin: una sola puerta para lo que ya corre el CI.
#
# Uso:
#   tools/verificar.sh            # sintaxis, estilo, estático y suite completa
#   tools/verificar.sh MiClase    # lo mismo, con la suite filtrada por nombre
#
# Los comandos son los de `composer.json` (phpcs, phpstan, phpunit): este script solo los encadena
# en el orden en que se rompen, para no llegar a la suite con el estilo roto.
set -uo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 1

FILTRO="${1:-}"
FALLOS=0

paso() { # paso <nombre> <comando...>
	local nombre="$1"
	shift
	printf '  %-38s' "$nombre"
	if salida=$("$@" 2>&1); then
		echo "OK"
	else
		echo "FALLO"
		echo "$salida" | tail -20 | sed 's/^/      /'
		FALLOS=$((FALLOS + 1))
	fi
}

echo "── verificación: $(basename "$PWD") ($(git rev-parse --short HEAD 2>/dev/null || echo 'sin git'))"

paso "php -l (sintaxis)" bash -c 'git ls-files "*.php" | xargs -r -n1 php -l >/dev/null'
paso "composer phpcs" composer phpcs --no-interaction

if [ -x vendor/bin/phpstan ]; then
	paso "composer phpstan" composer phpstan --no-interaction
fi

if [ -n "$FILTRO" ]; then
	paso "phpunit --filter $FILTRO" php vendor/bin/phpunit -c phpunit.xml.dist --testsuite unit --filter "$FILTRO"
else
	paso "composer phpunit" composer phpunit --no-interaction
fi

echo "── resultado: $([ "$FALLOS" -eq 0 ] && echo 'verde' || echo "$FALLOS paso(s) en rojo")"
exit "$FALLOS"
