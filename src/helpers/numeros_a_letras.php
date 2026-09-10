<?php
/**
 * Helper para convertir números a su representación en letras en Bolivianos
 * Formato estándar comercial/bancario: "SEISCIENTOS 00/100 BOLIVIANOS"
 */

function numeroALetrasBolivianos($numero) {
    $numero = floatval(str_replace(',', '', $numero));
    $enteros = floor($numero);
    $centavos = round(($numero - $enteros) * 100);
    $strCentavos = str_pad($centavos, 2, '0', STR_PAD_LEFT) . '/100 BOLIVIANOS';

    if ($enteros == 0) {
        return 'CERO ' . $strCentavos;
    }

    $letras = convertirEnteroALetras($enteros);
    return trim($letras) . ' ' . $strCentavos;
}

function convertirEnteroALetras($n) {
    $unidades = [
        1 => 'UN', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO', 5 => 'CINCO',
        6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE', 10 => 'DIEZ',
        11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
        16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
        20 => 'VEINTE', 21 => 'VEINTIÚN', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS',
        24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE',
        28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE'
    ];

    $decenas = [
        30 => 'TREINTA', 40 => 'CUARENTA', 50 => 'CINCUENTA',
        60 => 'SESENTA', 70 => 'SETENTA', 80 => 'OCHENTA', 90 => 'NOVENTA'
    ];

    $centenas = [
        100 => 'CIENTO', 200 => 'DOSCIENTOS', 300 => 'TRESCIENTOS',
        400 => 'CUATROCIENTOS', 500 => 'QUINIENTOS', 600 => 'SEISCIENTOS',
        700 => 'SETECIENTOS', 800 => 'OCHOCIENTOS', 900 => 'NOVECIENTOS'
    ];

    if ($n <= 29) {
        return $unidades[$n] ?? '';
    }

    if ($n < 100) {
        $d = floor($n / 10) * 10;
        $u = $n % 10;
        if ($u == 0) {
            return $decenas[$d];
        }
        return $decenas[$d] . ' Y ' . $unidades[$u];
    }

    if ($n == 100) {
        return 'CIEN';
    }

    if ($n < 1000) {
        $c = floor($n / 100) * 100;
        $resto = $n % 100;
        if ($resto == 0) {
            return $centenas[$c];
        }
        return $centenas[$c] . ' ' . convertirEnteroALetras($resto);
    }

    if ($n < 1000000) {
        $miles = floor($n / 1000);
        $resto = $n % 1000;
        $strMiles = ($miles == 1) ? 'MIL' : convertirEnteroALetras($miles) . ' MIL';
        if ($resto == 0) {
            return $strMiles;
        }
        return $strMiles . ' ' . convertirEnteroALetras($resto);
    }

    if ($n < 1000000000) {
        $millones = floor($n / 1000000);
        $resto = $n % 1000000;
        $strMillones = ($millones == 1) ? 'UN MILLÓN' : convertirEnteroALetras($millones) . ' MILLONES';
        if ($resto == 0) {
            return $strMillones;
        }
        return $strMillones . ' ' . convertirEnteroALetras($resto);
    }

    return (string)$n;
}
