<?php

declare(strict_types=1);

namespace App\Dados;

use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use NumberFormatter;

final readonly class Formatador
{
    public function __construct(
        private string $locale,
        private string $moeda,
    ) {}

    public function moeda(float|int|string $valor): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency((float) $valor, $this->moeda);
    }

    public function numero(float|int|string $valor, int $casas = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $casas);

        return $formatter->format((float) $valor);
    }

    public function percentual(float $valor, int $casas = 0): string
    {
        $formatter = new NumberFormatter($this->locale, NumberFormatter::PERCENT);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $casas);

        return $formatter->format($valor);
    }

    public function data(DateTimeInterface|string $valor, int $estilo = IntlDateFormatter::SHORT): string
    {
        $formatter = new IntlDateFormatter($this->locale, $estilo, IntlDateFormatter::NONE);

        return $formatter->format($this->paraData($valor));
    }

    public function dataHora(DateTimeInterface|string $valor): string
    {
        $formatter = new IntlDateFormatter($this->locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);

        return $formatter->format($this->paraData($valor));
    }

    private function paraData(DateTimeInterface|string $valor): DateTimeInterface
    {
        return $valor instanceof DateTimeInterface ? $valor : new DateTimeImmutable($valor);
    }
}
