<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción de dominio: factura no encontrada.
 *
 * Se lanza en FacturaService cuando una factura no existe o no pertenece
 * al usuario autenticado. El Handler la convierte en HTTP 404.
 */
class FacturaNotFoundException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Construye la excepción con el ID de la factura no encontrada.
     *
     * @param  int  $id  ID de la factura no encontrada
     * @return static
     */
    public static function conId(int $id): static
    {
        return new static("Factura con ID {$id} no encontrada.");
    }
}
