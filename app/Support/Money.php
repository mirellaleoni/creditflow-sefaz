<?php

namespace App\Support;

/**
 * Utilitário para valores monetários em centavos inteiros.
 *
 * Nunca usa ponto flutuante: toda a conversão é feita com aritmética inteira,
 * evitando erros de arredondamento.
 */
final class Money
{
    /**
     * Converte centavos para texto com duas casas decimais.
     *
     * Exemplos: 1050 => "10.50", 5 => "0.05", 0 => "0.00", 12500 => "125.00"
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}
