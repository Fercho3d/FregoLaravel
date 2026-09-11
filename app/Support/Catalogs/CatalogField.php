<?php

namespace App\Support\Catalogs;

use Closure;

/** Un campo de un catálogo: cómo se llama, cómo se captura y cómo se valida. */
final class CatalogField
{
    /**
     * @param  string  $type  text | number | date | boolean | select
     * @param  string[]  $rules
     * @param  ?Closure(): array<int|string, string>  $options  Solo para `select`.
     *                                                          Se resuelve al pintar y no al
     *                                                          declarar: si no, un catálogo
     *                                                          consultaría la base en cada
     *                                                          arranque, incluso sin usarse.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = 'text',
        public readonly array $rules = ['nullable', 'string', 'max:255'],
        public readonly bool $inList = true,
        public readonly ?Closure $options = null,
    ) {}

    /** @return array<int|string, string> */
    public function opciones(): array
    {
        return $this->options === null ? [] : ($this->options)();
    }

    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /** Valor listo para guardar en la base. */
    public function cast(mixed $valor): mixed
    {
        return match ($this->type) {
            'boolean' => $valor ? 1 : 0,
            'number' => $valor === '' || $valor === null ? null : (float) $valor,
            'select' => $valor === '' || $valor === null ? null : (int) $valor,
            default => $valor === '' ? null : $valor,
        };
    }
}
