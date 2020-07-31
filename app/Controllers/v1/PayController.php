<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;
use App\Models\OperEntidadTramiteModel;
use App\Models\OperPagoTramiteModel;

class PayController {

    public static function post_index($request) {

        //        DB::listen(function($query) {
//            //Imprimimos la consulta ejecutada
//            echo "<pre> {$query->sql } </pre>";
//        });
        extract(get_object_vars($request));

        $arrTipoServicioQuery = $arrTipoServicioRequest = array();
        $sumaTramites = 0; //para sumar el importe de los tramites

        foreach ($tramite as $key) {//recorremos los tramites para validar montos
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
                    throw new ShowableException(422, "La suma de los conceptos es mayor al importe del tramite");
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
                            })
                            ;
                        })
                        ->leftJoin('oper_cuentasbanco as CB', 'PT.cuentasbanco_id', '=', 'CB.id')
                        ->select(
                                'CB.metodopago_id', 'ET.entidad_id', 'ET.tipo_servicios_id', 'CB.id',
                                \DB::raw('(CASE WHEN PT.tramite_id IS NULL THEN 0 ELSE PT.tramite_id END) AS tramite_id')
                        )
                        ->where('ET.entidad_id', '=', $entidad)
                        ->whereIn("ET.tipo_servicios_id", $arrTipoServicioRequestUnico)
                        ->orderByDesc('ET.tipo_servicios_id')
                        ->get()->toArray();
        $arrCuentasTramite = $arrCuentasFinal = array();

        $conteo = $tramiteIndex = $tramiteAnterior = 0;
        $tramiteSinCuenta = 0;
        foreach ($tramitesEntidad as $valor) {

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
                $arrCuentasFinal = array();
            } else {
                $arrCuentasTramite[$tramiteIndex][] = $valor->id;
            }
            if (in_array($valor->id, $arrCuentasTramite[$tramiteAnterior])) {
                $arrCuentasFinal[] = $valor->id;
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
                'estatus' => 45,//en proceso
                'importe_transaccion' => $importe_transaccion,
                'metodo_pago_id' => 0,
                'referencia' => 'xx',
                'fecha_transaccion' => date("Y-m-d H:i:s"),
                'json' => json_encode($request)
            ]
        ];

        //insertamos la transaccion
        DB::table('oper_transacciones')->insert($datosTransaccion);
        $idTransaccionInsertada = DB::getPdo()->lastInsertId();

        foreach ($tramite as $key) {//recorremos los tramites para crear la insersion 
            //insertamos el tramite
            $datosTramite = [
                'id_transaccion_motor' => $idTransaccionInsertada,
                'id_tramite' => $key->id_tramite
            ];
            DB::table('oper_tramites')->insert($datosTramite);
            $idTramiteInsertado = DB::getPdo()->lastInsertId();
            foreach ($key->detalle as $keyDetalle) {//recorremos el detalle de cada tramite
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
        }

        $arrRespuesta = array("msg" => "Correcto",
            "folio"=>$idTransaccionInsertada);
        return $arrRespuesta;
    }

}
