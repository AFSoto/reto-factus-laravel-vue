<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción de dominio: producto no encontrado.
 *
 * Se lanza en ProductoService cuando un producto no existe o no pertenece
 * al usuario autenticado. El Handler la convierte en HTTP 404.
 */
class ProductoNotFoundException extends \RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param  int  $id  ID del producto no encontrado
     * @return static
     */
    public static function conId(int $id): static
    {
        return new static("Producto con ID {$id} no encontrado.");
    }

    /**
     * @param  string  $codigo  Código del producto no encontrado
     * @return static
     */
    public static function conCodigo(string $codigo): static
    {
        return new static("No se encontró un producto con el código '{$codigo}'.");
    }
}
