<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;
use App\Models\OperEntidadTramiteModel;
use App\Models\OperPagoTramiteModel;

class PayController {

    public static function get_index($folio) {
//        dd($folio);//folio
        DB::listen(function($query) {
            //Imprimimos la consulta ejecutada
            echo "<pre> {$query->sql } </pre>";
        });
//         SELECT 
//        A.id_transaccion_motor,
//            COUNT(A.id_transaccion_motor) AS conteoTramites
//    FROM
//        oper_tramites A
//    WHERE
//        A.id_transaccion_motor = 2000000092
//SELECT 
//    *
//FROM
//    (
//    SELECT 
//        B.id_transaccion_motor,
//            cuentasbanco_id,
//            metodopago_id,
//            banco_id,
//            COUNT(cuentasbanco_id) conteoCuentas,
//            conteoTramites
//    FROM
//        oper_pagotramite A
//    INNER JOIN oper_tramites B ON A.tramite_id = B.id_tipo_servicio
//    INNER JOIN oper_cuentasbanco C ON A.cuentasbanco_id = C.id
//    INNER JOIN (
//    SELECT 
//        A.id_transaccion_motor,
//            COUNT(A.id_transaccion_motor) AS conteoTramites
//    FROM
//        oper_tramites A
//    WHERE
//        A.id_transaccion_motor = 2000000092
//        ) tbl 
//        ON B.id_transaccion_motor = tbl.id_transaccion_motor
//        AND A.estatus = 1
//    WHERE
//        B.id_transaccion_motor = 2000000092
//    GROUP BY cuentasbanco_id) tblT
//WHERE
//    conteoCuentas = conteoTramites

        $tramitesTotales = DB::table('oper_tramites as A_')
                ->select('A_.id_transaccion_motor', DB::raw('COUNT(A_.id_transaccion_motor) AS conteoTramites'))
                ->where('A_.id_transaccion_motor', $folio);

        $sqlConteoTramites = DB::query()->fromSub(function ($query) use($folio, $tramitesTotales) {
                            $query->from('oper_pagotramite as OPT')
                            ->join('oper_tramites as OT', 'OPT.tramite_id', '=', 'OT.id_tipo_servicio')
                            ->join('oper_cuentasbanco as OCB', 'OPT.cuentasbanco_id', '=', 'OCB.id')
                            ->joinSub($tramitesTotales, 'TT', function ($join) {
                                $join->on('OT.id_transaccion_motor', '=', 'TT.id_transaccion_motor')
                                ->where('OPT.estatus', '=', 1);
                            })
                            ->select('OCB.metodopago_id', 'OT.id_transaccion_motor', 'OPT.cuentasbanco_id', 'OCB.banco_id',
                                    \DB::raw('COUNT(cuentasbanco_id) as conteoCuentas'),
                                    'conteoTramites')
                            ->where("OT.id_transaccion_motor", "=", $folio)
                            ->groupBy('OPT.cuentasbanco_id')
                            ;
                        }, 'tblT')
                        ->where(\DB::raw('conteoTramites'), \DB::raw('conteoCuentas'))
                        ->get()->toArray();
        $arrCuentas = array();
        foreach ($sqlConteoTramites as $valor) {
            $arrCuentas[] = array(
                'metodo_pago' => $valor->metodopago_id,
                'cuenta' => $valor->cuentasbanco_id,
                'banco' => $valor->banco_id
            );
        }

        return $arrCuentas;
    }

    public static function post_index($request) {

        extract(get_object_vars($request));
        $arrTipoServicioQuery = $arrTipoServicioRequest = array();
        $sumaTramites = 0; //para sumar el importe de los tramites
        $montoMaximoTramite = 0;
        $montoMinimoTramite = 99999999999999999999;
        foreach ($tramite as $key) {//recorremos los tramites para validar montos
            if ($montoMaximoTramite < $key->importe_tramite) {
                $montoMaximoTramite = $key->importe_tramite;
            }
            if ($montoMinimoTramite > $key->importe_tramite) {
                $montoMinimoTramite = $key->importe_tramite;
            }

            $sumaDetalle = 0;
            $arrTipoServicioRequest[] = $key->id_tipo_servicio; //almacenamos los tipos de tramite
            $sumaTramites += $key->importe_tramite; //sumamos los importes de cada tramite
            foreach ($key->detalle as $keyDetalle) {//recorremos el detalle de cada tramite
                $sumaDetalle += $keyDetalle->importe_concepto; //sumamos el importe de cada detalle
                if (isset($keyDetalle->descuentos)) {
                    $sumaDescuentos = 0;
                    foreach ($keyDetalle->descuentos as $keyDescuentos) {//recorremos los descuentos
                        $sumaDescuentos += $keyDescuentos->importe_descuento; //sumamos los importes de los descuentos
                    }
                    if ($sumaDescuentos > $keyDetalle->importe_concepto)//validamos que no sean mayores que el importe del concepto
                        throw new ShowableException(422, "El descuento es mayor al importe del detalle");

                    $sumaDetalle -= $sumaDescuentos; //restamos la suma de los descuentos
                }

                if ($sumaDetalle != $key->importe_tramite)//validamos el la suma de los conceptos no sean mayor al importe tramite
                    throw new ShowableException(422, "La suma de los conceptos es mayor al importe del tramite" . $sumaDetalle . "-" . $key->importe_tramite);
            }
        }
        $arrTipoServicioRequestUnico = array_unique($arrTipoServicioRequest);
        $tramitesEntidad = DB::table('oper_entidadtramite as ET')
                        ->join('egobierno.tipo_servicios as E', 'ET.tipo_servicios_id', '=', 'E.Tipo_Code')
                        ->leftJoin('oper_pagotramite as PT', function($join) {
                            $join->on('ET.tipo_servicios_id', '=', 'PT.tramite_id')
                            ->where('PT.estatus', '=', 1)
                            ->where(function ($query) {
                                $query->where('PT.fecha_fin', '=', '0000-00-00 00:00:00')
                                ->orWhere(function ($query) {
                                    $query->where('PT.fecha_inicio', "<=", "now()")
                                    ->where("PT.fecha_fin", ">=", "now()");
                                });
                            });
                        })
//                        ->leftJoin('oper_cuentasbanco as CB', 'PT.cuentasbanco_id', '=', 'CB.id')
                        ->leftJoin('oper_cuentasbanco as CB', function($join) use($montoMinimoTramite, $montoMaximoTramite) {
                            $join->on('PT.cuentasbanco_id', '=', 'CB.id')
                            ->where('CB.monto_min', '<=', $montoMinimoTramite)
                            ->where('CB.monto_max', '>=', $montoMaximoTramite)
                            ;
                        })
                        ->select(
                                'CB.metodopago_id', 'ET.entidad_id', 'ET.tipo_servicios_id', 'CB.id', 'CB.banco_id', 'E.Tipo_Descripcion',
                                \DB::raw('(CASE WHEN PT.tramite_id IS NULL THEN 0 ELSE PT.tramite_id END) AS tramite_id')
                        )
                        ->where('ET.entidad_id', '=', $entidad)
                        ->whereIn("ET.tipo_servicios_id", $arrTipoServicioRequestUnico)
                        ->orderByDesc('ET.tipo_servicios_id')
                        ->get()->toArray();
        $arrCuentasTramite = $arrCuentasFinal = $arrDatosCuentas = array();

        $conteo = $tramiteIndex = $tramiteAnterior = 0;
        $tramiteSinCuenta = 0;
        $tramitesDescripcion = array();
        foreach ($tramitesEntidad as $valor) {
            $tramitesDescripcion[$valor->tipo_servicios_id] = $valor->Tipo_Descripcion;
            if ($valor->tramite_id == 0) {
                $tramiteSinCuenta = 1;
                break;
            }
            if ($conteo == 0) {
                $tramiteAnterior = $tramiteIndex = $valor->tipo_servicios_id;
            }

            if ($tramiteIndex !== $valor->tipo_servicios_id) {
                $tramiteAnterior = $tramiteIndex;
                $arrCuentasTramite[$tramiteIndex] = $arrCuentasFinal;
                $arrDatosCuentas = $arrCuentasFinal = array();
            } else {
                $arrCuentasTramite[$tramiteIndex][] = $valor->id;
            }
            if (in_array($valor->id, $arrCuentasTramite[$tramiteAnterior]) && $valor->id != "") {
                $arrCuentasFinal[] = $valor->id;
                $arrDatosCuentas[] = array(
                    "cuenta_id" => $valor->id,
                    "banco_id" => $valor->banco_id,
                    "metodopago_id" => $valor->metodopago_id
                );
            }
            $tramiteIndex = $valor->tipo_servicios_id;
            $arrTipoServicioQuery[] = $valor->tipo_servicios_id;
            $conteo++;
        }

        $arrTipoServicioQueryUnico = array_unique($arrTipoServicioQuery);


        if ($tramiteSinCuenta == 1)
            throw new ShowableException(422, "El Tramite no contiene cuenta(s) asosiada(s)");

        if (count($arrCuentasFinal) == 0)
            throw new ShowableException(422, "Los Tramites no comparten cuentas");

        if (count($arrTipoServicioQueryUnico) != count($arrTipoServicioRequestUnico))
            throw new ShowableException(422, "Tramite no permitido para la entidad");

        if ($sumaTramites != $importe_transaccion)
            throw new ShowableException(422, "La suma de los tramites no es igual al importe de la transaccion");

        $datosTransaccion = [
            [
                'id_transaccion' => $id_transaccion,
                'entidad' => $entidad,
                'estatus' => 45, //en proceso
                'importe_transaccion' => $importe_transaccion,
                'metodo_pago_id' => 0,
                'referencia' => "tmp",
                'fecha_transaccion' => date("Y-m-d H:i:s"),
                'json' => json_encode($request)
            ]
        ];

        //insertamos la transaccion
        DB::table('oper_transacciones')->insert($datosTransaccion);
        $idTransaccionInsertada = DB::getPdo()->lastInsertId();

        //actualizamos la referencia en la transaccion
        $referenciaGenerada = generarReferencia($idTransaccionInsertada);
        DB::table('oper_transacciones')
                ->where('id_transaccion_motor', $idTransaccionInsertada)
                ->update(['referencia' => $referenciaGenerada]);

        $tramitesLista = array();
        foreach ($tramite as $key) {//recorremos los tramites para crear la insersion 
//            dd($key->datos_factura,$key->datos_solicitante, $key->datos_solicitante->nombre);
            $datosSolicitante = $key->datos_solicitante;
            $datosFactura = $key->datos_factura;
            //insertamos el tramite
            $datosTramite = [
                'id_transaccion_motor' => $idTransaccionInsertada,
                'id_tramite' => $key->id_tramite,
                'id_tipo_servicio' => $key->id_tipo_servicio,
                'id_seguimiento' => $key->id_seguimiento,
                'importe_tramite' => $key->importe_tramite,
                'auxiliar_1' => $key->auxiliar_1,
                'auxiliar_2' => $key->auxiliar_2,
                'auxiliar_3' => $key->auxiliar_3,
                'nombre' => $datosSolicitante->nombre,
                'apellido_paterno' => $datosSolicitante->apellido_paterno,
                'apellido_materno' => $datosSolicitante->apellido_materno,
                'razon_social' => $datosSolicitante->razon_social,
                'rfc' => $datosSolicitante->rfc,
                'curp' => $datosSolicitante->curp,
                'email' => $datosSolicitante->email,
                'calle' => $datosSolicitante->calle,
                'colonia' => $datosSolicitante->colonia,
                'numexterior' => $datosSolicitante->numexterior,
                'numinterior' => $datosSolicitante->numinterior,
                'municipio' => $datosSolicitante->municipio,
                'codigopostal' => $datosSolicitante->codigopostal,
                'nombre_factura' => $datosFactura->nombre,
                'apellido_paterno_factura' => $datosFactura->apellido_paterno,
                'apellido_materno_factura' => $datosFactura->apellido_materno,
                'razon_social_factura' => $datosFactura->razon_social,
                'rfc_factura' => $datosFactura->rfc,
                'curp_factura' => $datosFactura->curp,
                'email_factura' => $datosFactura->email,
                'calle_factura' => $datosFactura->calle,
                'colonia_factura' => $datosFactura->colonia,
                'numinterior_factura' => $datosFactura->numinterior,
                'municipio_factura' => $datosFactura->municipio,
                'codigopostal_factura' => $datosFactura->codigopostal,
                'numexterior_factura' => $datosFactura->numexterior
            ];
            DB::table('oper_tramites')->insert($datosTramite);
            $idTramiteInsertado = DB::getPdo()->lastInsertId();
            $detalleLista = array();
            foreach ($key->detalle as $keyDetalle) {//recorremos el detalle de cada tramite
                $detalleLista[] = array(
                    "descripcion" => $keyDetalle->concepto,
                    "importe" => "$" . $keyDetalle->importe_concepto
                );
                $datosDetalle = [
                    'id_transaccion_motor' => $idTransaccionInsertada,
                    'id_tramite_motor' => $idTramiteInsertado,
                    'concepto' => $keyDetalle->concepto,
                    'importe_concepto' => $keyDetalle->importe_concepto,
                    'partida' => $keyDetalle->partida,
                    'id_descuento' => 0,
                    'importe_descuento' => 0
                ];
                DB::table('oper_detalle_tramite')->insert($datosDetalle);
                $sumaDescuento = 0;

                if (isset($keyDetalle->descuentos)) {// si cuenta con descuentos
                    $idDetalleInsertado = DB::getPdo()->lastInsertId();
                    foreach ($keyDetalle->descuentos as $keyDescuentos) {//recorremos los descuentos
                        $detalleLista[] = array(
                            "descripcion" => $keyDescuentos->concepto_descuento,
                            "importe" => "- $" . $keyDescuentos->importe_descuento
                        );
                        $datosDetalle = [
                            'id_transaccion_motor' => $idTransaccionInsertada,
                            'id_tramite_motor' => $idTramiteInsertado,
                            'concepto' => $keyDescuentos->concepto_descuento,
                            'importe_concepto' => $keyDescuentos->importe_descuento,
                            'partida' => $keyDescuentos->partida_descuento,
                            'id_descuento' => $idDetalleInsertado,
                            'importe_descuento' => 0
                        ];
                        //insertamos el descuento
                        DB::table('oper_detalle_tramite')->insert($datosDetalle);
                        $sumaDescuento += $keyDescuentos->importe_descuento;
                    }
                    //actualizamos el descuento
                    DB::table('oper_detalle_tramite')
                            ->where('id_detalle_tramite', $idDetalleInsertado)
                            ->update(['importe_descuento' => $sumaDescuento]);
                }
            }
            $tramitesLista[] = array(
                "descripcion" => $tramitesDescripcion[$key->id_tipo_servicio],
                "importe" => "$" . $key->importe_tramite,
                "detalle" => $detalleLista
            );
        }
        $arrRespuesta = array(
            "folio" => $idTransaccionInsertada,
            "cuentas" => $arrDatosCuentas,
            "tramites" => $tramitesLista
        );
        return $arrRespuesta;
    }

}

function generarReferencia($idTransaccion) {
    $referencia = 11; //(2)numero fijo
    $referencia .= obtenerIdentificadorReferencia(); //(8)identificador de referencia
    $referencia .= $idTransaccion; //(8)id transaccion
    $referencia .= date("m"); //(2)periodo
    $referencia .= obtenerFechaCondensada($idTransaccion); //(4)fecha condensada
    $referencia .= obtenerImporteCondensado($idTransaccion); //(1)importe condensada
    $referencia .= obtenerVerificador($referencia); //(2)verificador
    return $referencia;
}

function obtenerFechaCondensada($idTransaccion) {
    return "2342";
}

function obtenerIdentificadorReferencia() {
    $idReferencia = DB::table('identificadorreferencia')->get();
    return $idReferencia[0]->IdentificadorReferencia;
}

function obtenerImporteCondensado($idTransaccion) {
    return "7";
}

function obtenerVerificador($referencia) {
    return 68;
}
