<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción de dominio: se intentó anular una factura que ya está anulada.
 *
 * Se lanza en FacturaService::anular() cuando la factura ya tiene
 * estado 'anulada'. El Handler la convierte en HTTP 422.
 */
class FacturaYaAnuladaException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Construye la excepción con el ID de la factura.
     *
     * @param  int  $id  ID de la factura que ya estaba anulada
     * @return static
     */
    public static function conId(int $id): static
    {
        return new static("La factura con ID {$id} ya se encuentra anulada.");
    }
}
