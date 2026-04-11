<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de facturación electrónica — núcleo del dominio.
 *
 * Gestiona el ciclo de vida completo de una factura:
 *   borrador → emitida → (anulada)
 *
 * Responsabilidades:
 *   1. CRUD de borradores (crear, actualizar, eliminar).
 *   2. Emisión ante la DIAN vía la API de Factus:
 *      - Construye el payload con formato Factus desde la factura local.
 *      - Llama a FactusClient::emitirFactura().
 *      - Persiste los datos de respuesta (CUFE, QR, PDF) mediante el repositorio.
 *   3. Anulación de facturas emitidas vía Factus.
 *   4. Consulta de rangos de numeración disponibles para el selector del frontend.
 *
 * Reglas de negocio:
 *   - Solo los borradores son editables o emitibles.
 *   - Solo las facturas emitidas pueden anularse.
 *   - Los números de factura se asignan de forma provisional al crear el borrador
 *     consultando el rango activo en Factus. El número definitivo es el retornado
 *     por Factus en el campo `factus_numero` al emitir.
 */

use App\DTOs\Factura\CreateFacturaDTO;
use App\DTOs\Factura\FacturasFilterDTO;
use App\Exceptions\FacturaNoBorradorException;
use App\Exceptions\FacturaNotFoundException;
use App\Exceptions\FacturaYaAnuladaException;
use App\Integrations\DTOs\FactusFacturaDTO;
use App\Integrations\Factus\FactusClient;
use App\Models\Factura;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FacturaService
{
    /**
     * @param  FacturaRepositoryInterface  $facturaRepository  Repositorio de facturas inyectado por IoC
     * @param  FactusClient                $factusClient       Cliente HTTP de la API Factus (singleton)
     */
    public function __construct(
        private readonly FacturaRepositoryInterface $facturaRepository,
        private readonly FactusClient $factusClient,
    ) {}

    // ── Consultas ─────────────────────────────────────────────────────────────

    /**
     * Retorna las facturas del usuario paginadas aplicando los filtros del DTO.
     *
     * @param  int               $userId   ID del usuario autenticado
     * @param  FacturasFilterDTO $filtros  DTO con los filtros y configuración de paginación
     * @return LengthAwarePaginator<Factura>
     */
    public function listar(int $userId, FacturasFilterDTO $filtros): LengthAwarePaginator
    {
        return $this->facturaRepository->paginar(
            $userId,
            $filtros->toArray(),
            $filtros->perPage
        );
    }

    /**
     * Busca una factura por su ID verificando pertenencia al usuario.
     * La factura se retorna con sus relaciones cliente e items cargadas.
     *
     * @param  int  $id      ID de la factura
     * @param  int  $userId  ID del usuario autenticado
     * @return Factura  La factura con relaciones
     * @throws FacturaNotFoundException  Si no existe o no pertenece al usuario
     */
    public function buscar(int $id, int $userId): Factura
    {
        $factura = $this->facturaRepository->buscarPorId($id, $userId);

        if ($factura === null) {
            throw FacturaNotFoundException::conId($id);
        }

        return $factura;
    }

    /**
     * Obtiene los rangos de numeración disponibles desde la API de Factus.
     * El frontend los muestra en el selector al crear o editar una factura.
     *
     * @return array  Array de rangos de numeración activos en Factus
     */
    public function obtenerRangosNumeracion(): array
    {
        return $this->factusClient->obtenerRangosNumeracion();
    }

    // ── Mutaciones ────────────────────────────────────────────────────────────

    /**
     * Crea una nueva factura en estado borrador.
     *
     * El número provisional se determina consultando el rango activo en Factus
     * y buscando el máximo número existente para ese rango en la base de datos
     * local. El número definitivo se confirma en `factus_numero` al emitir.
     *
     * @param  CreateFacturaDTO  $dto  DTO con los datos validados de la factura
     * @return Factura  La factura creada en estado borrador
     */
    public function crear(CreateFacturaDTO $dto): Factura
    {
        // Determinar el número provisional consultando Factus
        [$numero, $numeroCompleto] = $this->determinarNumeroProvisional($dto->numberingRangeId, $dto->prefijo);

        // Persistir cabecera de la factura
        $factura = $this->facturaRepository->crear($dto, $numero, $numeroCompleto);

        // Persistir ítems en bulk
        $this->facturaRepository->crearItems($factura, $dto->itemsToArray());

        return $factura->load(['cliente', 'items']);
    }

    /**
     * Actualiza una factura en estado borrador.
     * Reemplaza todos los ítems existentes con los nuevos del DTO.
     *
     * @param  int               $id      ID de la factura a actualizar
     * @param  CreateFacturaDTO  $dto     DTO con los nuevos datos validados
     * @param  int               $userId  ID del usuario autenticado
     * @return Factura  La factura actualizada
     * @throws FacturaNotFoundException     Si no existe o no pertenece al usuario
     * @throws FacturaNoBorradorException   Si la factura no está en estado borrador
     */
    public function actualizar(int $id, CreateFacturaDTO $dto, int $userId): Factura
    {
        $factura = $this->buscar($id, $userId);

        if (! $factura->esEditable()) {
            throw FacturaNoBorradorException::alEditar($factura->id, $factura->estado);
        }

        // Reasignar número si cambió el rango de numeración
        [$numero, $numeroCompleto] = $this->determinarNumeroProvisional($dto->numberingRangeId, $dto->prefijo);

        $factura = $this->facturaRepository->actualizar($factura, $dto, $numero, $numeroCompleto);

        // Reemplazar ítems (el repositorio borra los existentes antes del insert)
        $this->facturaRepository->crearItems($factura, $dto->itemsToArray());

        return $factura->load(['cliente', 'items']);
    }

    /**
     * Elimina (soft delete) una factura en estado borrador.
     * Solo los borradores pueden eliminarse — las emitidas deben anularse.
     *
     * @param  int  $id      ID de la factura a eliminar
     * @param  int  $userId  ID del usuario autenticado
     * @return bool  True si la eliminación fue exitosa
     * @throws FacturaNotFoundException    Si no existe o no pertenece al usuario
     * @throws FacturaNoBorradorException  Si la factura no está en estado borrador
     */
    public function eliminar(int $id, int $userId): bool
    {
        $factura = $this->buscar($id, $userId);

        if (! $factura->esEditable()) {
            throw FacturaNoBorradorException::alEditar($factura->id, $factura->estado);
        }

        return $this->facturaRepository->eliminar($factura);
    }

    // ── Integración con Factus ─────────────────────────────────────────────────

    /**
     * Emite una factura borrador ante la DIAN vía la API de Factus.
     *
     * Flujo:
     *   1. Verifica que la factura existe y pertenece al usuario.
     *   2. Verifica que la factura está en estado borrador con ítems.
     *   3. Carga las relaciones necesarias (cliente, items).
     *   4. Construye el payload requerido por la API de Factus.
     *   5. Envía el payload a POST /v1/bills/validate.
     *   6. Parsea la respuesta a FactusFacturaDTO.
     *   7. Actualiza la factura local con CUFE, QR, PDF y estado 'emitida'.
     *
     * @param  int  $id      ID de la factura a emitir
     * @param  int  $userId  ID del usuario autenticado
     * @return Factura  La factura actualizada en estado 'emitida' con los datos de Factus
     * @throws FacturaNotFoundException    Si no existe o no pertenece al usuario
     * @throws FacturaNoBorradorException  Si la factura no está en borrador o no tiene ítems
     * @throws \App\Exceptions\FactusApiException  Si la API de Factus retorna error
     */
    public function emitir(int $id, int $userId): Factura
    {
        $factura = $this->buscar($id, $userId);

        // Cargar relaciones necesarias para construir el payload
        $factura->loadMissing(['cliente', 'items']);

        if (! $factura->puedeEmitirse()) {
            throw FacturaNoBorradorException::alEmitir($factura->id, $factura->estado);
        }

        // Construir y enviar el payload a la API de Factus
        $payload  = $this->construirPayloadFactus($factura);
        $response = $this->factusClient->emitirFactura($payload);

        // Parsear la respuesta y persistir los datos de Factus
        $factusDto = FactusFacturaDTO::fromResponse($response);

        return $this->facturaRepository->marcarComoEmitida($factura, $factusDto->toRepositoryArray());
    }

    /**
     * Anula una factura emitida ante la DIAN vía la API de Factus.
     *
     * Flujo:
     *   1. Verifica que la factura existe y pertenece al usuario.
     *   2. Verifica que no está ya anulada.
     *   3. Verifica que está en estado emitida (puedeAnularse).
     *   4. Llama a POST /v1/bills/cancel/{numero} en Factus.
     *   5. Actualiza la factura local a estado 'anulada'.
     *
     * @param  int     $id      ID de la factura a anular
     * @param  string  $motivo  Motivo de anulación requerido por la DIAN
     * @param  int     $userId  ID del usuario autenticado
     * @return Factura  La factura actualizada en estado 'anulada'
     * @throws FacturaNotFoundException    Si no existe o no pertenece al usuario
     * @throws FacturaYaAnuladaException   Si la factura ya está anulada
     * @throws \RuntimeException           Si la factura no está en estado emitida
     * @throws \App\Exceptions\FactusApiException  Si la API de Factus retorna error
     */
    public function anular(int $id, string $motivo, int $userId): Factura
    {
        $factura = $this->buscar($id, $userId);

        if ($factura->estaAnulada()) {
            throw FacturaYaAnuladaException::conId($factura->id);
        }

        if (! $factura->puedeAnularse()) {
            throw new \RuntimeException(
                "La factura {$factura->id} no puede anularse porque está en estado '{$factura->estado}'. Solo las facturas emitidas pueden anularse."
            );
        }

        // El número de factura con el que Factus identifica la factura
        $numeroCancelacion = $factura->factus_numero ?? $factura->numero_completo;

        $this->factusClient->anularFactura((string) $numeroCancelacion);

        return $this->facturaRepository->marcarComoAnulada($factura, $motivo);
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    /**
     * Determina el número provisional de la factura para el borrador.
     *
     * Estrategia: consultar el rango de numeración en Factus para obtener
     * el número actual y calcular el siguiente. Si el rango no se encuentra,
     * usar el máximo número local para ese rango + 1.
     *
     * @param  int          $numberingRangeId  ID del rango de numeración en Factus
     * @param  string|null  $prefijo           Prefijo del rango (ej: "SETP")
     * @return array{int, string|null}  [numero, numero_completo]
     */
    private function determinarNumeroProvisional(int $numberingRangeId, ?string $prefijo): array
    {
        try {
            $rangos = $this->factusClient->obtenerRangosNumeracion();
            $rango  = $this->buscarRangoPorId($numberingRangeId, $rangos);

            if ($rango !== null) {
                // Usar el número actual del rango como base — Factus asignará el definitivo al emitir
                $numero  = (int) ($rango['current_number'] ?? $rango['current'] ?? $rango['from_number'] ?? $rango['from'] ?? 1);
                $prefijo = $rango['prefix'] ?? $prefijo ?? '';

                $numeroCompleto = $prefijo !== '' ? "{$prefijo}{$numero}" : (string) $numero;

                return [$numero, $numeroCompleto ?: null];
            }
        } catch (\Throwable) {
            // Si Factus no está disponible, continuar con numeración local
        }

        // Fallback: máximo número local para ese rango + 1
        $ultimoNumero = Factura::where('numbering_range_id', $numberingRangeId)
            ->withTrashed()
            ->max('numero') ?? 0;

        $numero         = $ultimoNumero + 1;
        $numeroCompleto = $prefijo !== null && $prefijo !== ''
            ? "{$prefijo}{$numero}"
            : (string) $numero;

        return [$numero, $numeroCompleto ?: null];
    }

    /**
     * Busca un rango de numeración en el array de rangos por su ID.
     *
     * @param  int    $id      ID del rango a buscar
     * @param  array  $rangos  Array de rangos retornados por Factus
     * @return array|null  El rango encontrado o null
     */
    private function buscarRangoPorId(int $id, array $rangos): ?array
    {
        foreach ($rangos as $rango) {
            if (isset($rango['id']) && (int) $rango['id'] === $id) {
                return $rango;
            }
        }

        return null;
    }

    /**
     * Construye el payload JSON requerido por la API de Factus para emitir la factura.
     *
     * Mapea los datos locales (Factura + Cliente + FacturaItems) al formato
     * esperado por POST /v1/bills/validate de la API de Factus.
     *
     * @param  Factura  $factura  Factura con relaciones cliente e items ya cargadas
     * @return array  Payload listo para enviar a la API de Factus
     */
    private function construirPayloadFactus(Factura $factura): array
    {
        $cliente = $factura->cliente;

        return [
            'numbering_range_id'  => $factura->numbering_range_id,
            'reference_code'      => $factura->numero_completo ?? (string) $factura->numero,
            'observation'         => $factura->observaciones ?? '',
            'payment_form'        => (string) $factura->payment_form,
            'payment_method_code' => (string) $factura->payment_method_code,
            'payment_due_date'    => $factura->esCredito()
                ? ($factura->payment_due_date?->format('Y-m-d'))
                : null,
            'billing_period'      => null,
            'customer'            => $this->construirCustomer($cliente),
            'items'               => $this->construirItems($factura),
        ];
    }

    /**
     * Construye el bloque "customer" del payload de Factus desde el modelo Cliente.
     *
     * @param  \App\Models\Cliente  $cliente  Cliente receptor de la factura
     * @return array  Bloque customer con los campos requeridos por Factus
     */
    private function construirCustomer(\App\Models\Cliente $cliente): array
    {
        // Para persona jurídica: company = razon_social, names = ""
        // Para persona natural: company = "", names = nombre completo
        $esJuridica = $cliente->tipo_persona === \App\Models\Cliente::TIPO_PERSONA_JURIDICA;

        $names = $esJuridica
            ? ''
            : trim(implode(' ', array_filter([
                $cliente->primer_nombre,
                $cliente->segundo_nombre,
                $cliente->primer_apellido,
                $cliente->segundo_apellido,
            ])));

        return [
            'identification'          => $cliente->numero_documento,
            'dv'                      => $cliente->digito_verificacion ?? '',
            'company'                 => $esJuridica ? ($cliente->razon_social ?? '') : '',
            'trade_name'              => $esJuridica ? ($cliente->razon_social ?? '') : '',
            'names'                   => $names,
            'address'                 => $cliente->direccion,
            'email'                   => $cliente->email,
            'mobile'                  => $cliente->telefono ?? '',
            'tribute_id'              => $cliente->tribute_id,
            'identification_document_id' => $cliente->tipo_documento_identidad_id,
            'municipality_id'         => $cliente->municipio_id,
        ];
    }

    /**
     * Construye el array de ítems del payload de Factus desde los FacturaItems cargados.
     *
     * @param  Factura  $factura  Factura con la relación items cargada
     * @return array  Array de ítems en el formato requerido por Factus
     */
    private function construirItems(Factura $factura): array
    {
        return $factura->items->map(function (\App\Models\FacturaItem $item): array {
            // El tax_rate va como string con dos decimales: "19.00", "5.00", "0.00"
            $taxRate = number_format((float) $item->porcentaje_iva, 2, '.', '');

            return [
                'code_reference'    => $item->codigo,
                'name'              => $item->nombre,
                'quantity'          => (float) $item->cantidad,
                'discount_rate'     => (float) $item->porcentaje_descuento,
                'price'             => (float) $item->precio_unitario,
                'tax_rate'          => $taxRate,
                'unit_measure_id'   => (int) $item->unidad_medida_id,
                'standard_code_id'  => (int) $item->tipo_item_identificacion_id,
                'is_excluded'       => 0,
                'tribute_id'        => (int) $item->tribute_id,
            ];
        })->all();
    }
}
