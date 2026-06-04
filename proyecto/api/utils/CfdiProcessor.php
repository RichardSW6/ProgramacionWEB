<?php
declare(strict_types=1);

class CfdiProcessor
{
    private const NS_CFDI = 'http://www.sat.gob.mx/cfd/4';
    private const NS_TFD  = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    public static function parse(string $xmlContent): array
    {
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        $dom = new DOMDocument();
        if (!$dom->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOENT)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \Exception('XML no válido o no es CFDI v4.0');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('cfdi', self::NS_CFDI);
        $xpath->registerNamespace('tfd',  self::NS_TFD);

        // Validate it is CFDI v4.0
        $comprobante = $xpath->query('/cfdi:Comprobante');
        if ($comprobante->length === 0) {
            throw new \Exception('XML no válido o no es CFDI v4.0');
        }

        $comp = $comprobante->item(0);
        $version = $comp->getAttribute('Version');
        if ($version !== '4.0') {
            throw new \Exception('XML no válido o no es CFDI v4.0');
        }

        // ── UUID from TimbreFiscalDigital ──────────────────────────────────
        $tfdNode = $xpath->query('//tfd:TimbreFiscalDigital');
        $uuid = '';
        if ($tfdNode->length > 0) {
            $uuid = $tfdNode->item(0)->getAttribute('UUID');
        }

        // ── Emisor ────────────────────────────────────────────────────────
        $emisorNode = $xpath->query('/cfdi:Comprobante/cfdi:Emisor');
        $rfcEmisor    = '';
        $nombreEmisor = '';
        if ($emisorNode->length > 0) {
            $rfcEmisor    = $emisorNode->item(0)->getAttribute('Rfc');
            $nombreEmisor = $emisorNode->item(0)->getAttribute('Nombre');
        }

        // ── Receptor ──────────────────────────────────────────────────────
        $receptorNode = $xpath->query('/cfdi:Comprobante/cfdi:Receptor');
        $rfcReceptor    = '';
        $nombreReceptor = '';
        $usoCfdi        = '';
        if ($receptorNode->length > 0) {
            $rfcReceptor    = $receptorNode->item(0)->getAttribute('Rfc');
            $nombreReceptor = $receptorNode->item(0)->getAttribute('Nombre');
            $usoCfdi        = $receptorNode->item(0)->getAttribute('UsoCFDI');
        }

        // ── Totales ───────────────────────────────────────────────────────
        $total    = (float)$comp->getAttribute('Total');
        $subtotal = (float)$comp->getAttribute('SubTotal');

        // ── IVA ───────────────────────────────────────────────────────────
        $iva = 0.0;
        $traslados = $xpath->query('/cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado');
        foreach ($traslados as $traslado) {
            if ($traslado->getAttribute('Impuesto') === '002') {
                $iva += (float)$traslado->getAttribute('Importe');
            }
        }

        // ── Fechas y otros atributos ──────────────────────────────────────
        $fechaEmision = $comp->getAttribute('Fecha');
        $formaPago    = $comp->getAttribute('FormaPago');

        // ── Conceptos ─────────────────────────────────────────────────────
        $conceptos   = [];
        $conceptoNodes = $xpath->query('/cfdi:Comprobante/cfdi:Conceptos/cfdi:Concepto');
        foreach ($conceptoNodes as $concepto) {
            $conceptos[] = [
                'descripcion'   => $concepto->getAttribute('Descripcion'),
                'cantidad'      => (float)$concepto->getAttribute('Cantidad'),
                'valor_unitario'=> (float)$concepto->getAttribute('ValorUnitario'),
                'importe'       => (float)$concepto->getAttribute('Importe'),
            ];
        }

        return [
            'uuid'            => $uuid,
            'rfc_emisor'      => $rfcEmisor,
            'nombre_emisor'   => $nombreEmisor,
            'rfc_receptor'    => $rfcReceptor,
            'nombre_receptor' => $nombreReceptor,
            'total'           => $total,
            'subtotal'        => $subtotal,
            'iva'             => $iva,
            'fecha_emision'   => substr($fechaEmision, 0, 10), // YYYY-MM-DD
            'forma_pago'      => $formaPago,
            'uso_cfdi'        => $usoCfdi,
            'version'         => $version,
            'conceptos'       => $conceptos,
        ];
    }
}
