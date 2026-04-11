<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Colección paginada de facturas.
 * Se usa en FacturaController::index() para el listado paginado.
 */
class FacturaCollection extends ResourceCollection
{
    public $collects = FacturaResource::class;

    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
