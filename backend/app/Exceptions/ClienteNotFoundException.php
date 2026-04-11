<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción de dominio: cliente no encontrado.
 *
 * Se lanza en los Services cuando un cliente no existe en la base de datos
 * o no pertenece al usuario autenticado.
 * El Handler la convierte automáticamente en una respuesta HTTP 404.
 */
class ClienteNotFoundException extends \RuntimeException
{
    /**
     * @param  string  $message  Mensaje descriptivo
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Construye la excepción indicando el ID buscado.
     *
     * @param  int  $id  ID del cliente no encontrado
     * @return static
     */
    public static function conId(int $id): static
    {
        return new static("Cliente con ID {$id} no encontrado.");
    }

    /**
     * Construye la excepción indicando el número de documento buscado.
     *
     * @param  string  $numeroDocumento  Número del documento de identidad
     * @return static
     */
    public static function conDocumento(string $numeroDocumento): static
    {
        return new static("No se encontró un cliente con el documento {$numeroDocumento}.");
    }
}
