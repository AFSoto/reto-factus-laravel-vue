<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción de dominio: se intentó editar o emitir una factura que no es borrador.
 *
 * Se lanza en FacturaService cuando se intenta modificar o emitir una factura
 * que ya está en estado 'emitida' o 'anulada'. El Handler la convierte en HTTP 422.
 */
class FacturaNoBorradorException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Se intentó editar una factura que no está en borrador.
     *
     * @param  int     $id      ID de la factura
     * @param  string  $estado  Estado actual de la factura
     * @return static
     */
    public static function alEditar(int $id, string $estado): static
    {
        return new static(
            "No se puede editar la factura {$id} porque está en estado '{$estado}'. Solo los borradores son editables."
        );
    }

    /**
     * Se intentó emitir una factura que no está en borrador.
     *
     * @param  int     $id      ID de la factura
     * @param  string  $estado  Estado actual de la factura
     * @return static
     */
    public static function alEmitir(int $id, string $estado): static
    {
        return new static(
            "No se puede emitir la factura {$id} porque está en estado '{$estado}'. Solo los borradores pueden emitirse."
        );
    }
}
