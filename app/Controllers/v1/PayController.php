<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;
use App\Models\OperEntidadTramiteModel;
use App\Models\OperPagoTramiteModel;

class PayController {

    public static function get_index($folio) {
//        DB::listen(function($query) {
//            //Imprimimos la consulta ejecutada
//            echo "<pre> {$query->sql } </pre>";
//        });
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
                            ->join('oper_banco as OB', 'OCB.banco_id', '=', 'OB.id')
                            ->joinSub($tramitesTotales, 'TT', function ($join) {
                                $join->on('OT.id_transaccion_motor', '=', 'TT.id_transaccion_motor')
                                ->where('OPT.estatus', '=', 1);
                            })
                            ->select('OCB.metodopago_id', 'OT.id_transaccion_motor', 'OPT.cuentasbanco_id', 'OCB.banco_id',
                                    \DB::raw('COUNT(cuentasbanco_id) as conteoCuentas'),
                                    'conteoTramites', 'OB.imagen', 'OB.nombre')
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
                'banco' => $valor->banco_id,
                'nombre_banco' => $valor->nombre,
                'imagen' => $valor->imagen
            );
        }

        return $arrCuentas;
    }

    public static function post_index($request) {
//        DB::listen(function($query) {
//            //Imprimimos la consulta ejecutada
//            echo "<pre> {$query->sql } </pre>";
//        });
        extract(get_object_vars($request));

        if (validaTransaccionDuplicada($entidad, $id_transaccion)) {
            throw new ShowableException(422, "La transaccion ya existe en la entidad");
        }
        $reciboPago = ""; //variable para decir que se pago en cero
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

            }
                if ($sumaDetalle != $key->importe_tramite)//validamos el la suma de los conceptos no sean mayor al importe tramite
                    throw new ShowableException(422, "La suma de los conceptos es mayor al importe del tramite" . $sumaDetalle . "-" . $key->importe_tramite);
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
                        ->leftJoin('oper_cuentasbanco as CB', function($join) use($montoMinimoTramite, $montoMaximoTramite) {
                            $join->on('PT.cuentasbanco_id', '=', 'CB.id')
                            ->where('CB.monto_min', '<=', $montoMinimoTramite)
                            ->where('CB.monto_max', '>=', $montoMaximoTramite)
                            ;
                        })
                        ->leftJoin('oper_banco as OB', 'CB.banco_id', '=', 'OB.id')
                        ->select(
                                'CB.metodopago_id', 'ET.entidad_id', 'ET.tipo_servicios_id', 'CB.id', 'CB.banco_id', 'E.Tipo_Descripcion',
                                \DB::raw('(CASE WHEN PT.tramite_id IS NULL THEN 0 ELSE PT.tramite_id END) AS tramite_id'),
                                'OB.imagen'
                        )
                        ->where('ET.entidad_id', '=', $entidad)
                        ->whereIn("ET.tipo_servicios_id", $arrTipoServicioRequestUnico)
                        ->orderByDesc('ET.tipo_servicios_id')
                        ->get()->toArray();
        $arrCuentasTramite = $arrCuentasFinal = $arrDatosCuentas = array();

        $conteo = $tramiteIndex = $tramiteAnterior = $tipoTramiteGeneral = 0;
        $tramiteSinCuenta = 0;
        $tramitesDescripcion = array();
//        dd($tramitesEntidad);
        foreach ($tramitesEntidad as $valor) {
            $tramitesDescripcion[$valor->tipo_servicios_id] = $valor->Tipo_Descripcion;
            if ($valor->tramite_id == 0) {
                $tramiteSinCuenta = 1;
                $arrTipoServicioQuery[]=0;
                break;
            }
            if ($conteo == 0) {
                $tipoTramiteGeneral = $tramiteAnterior = $tramiteIndex = $valor->tipo_servicios_id;
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
                    "metodopago_id" => $valor->metodopago_id,
                    "imagen" => $valor->imagen
                );
            }
            $tramiteIndex = $valor->tipo_servicios_id;
            $arrTipoServicioQuery[] = $valor->tipo_servicios_id;
            $conteo++;
        }

        $arrTipoServicioQueryUnico = array_unique($arrTipoServicioQuery);

        if (count($arrTipoServicioQueryUnico) != count($arrTipoServicioRequestUnico))
            throw new ShowableException(422, "Tramite no permitido para la entidad");

        if ($tramiteSinCuenta == 1)
            throw new ShowableException(422, "El Tramite no contiene cuenta(s) asosiada(s)");

        if (count($arrCuentasFinal) == 0)
            throw new ShowableException(422, "Los Tramites no comparten cuentas");



        if ($sumaTramites != $importe_transaccion)
            throw new ShowableException(422, "La suma de los tramites no es igual al importe de la transaccion");

        $datosTransaccion = [
            [
                'id_transaccion' => $id_transaccion,
                'entidad' => $entidad,
                'estatus' => 45, //en proceso
                'importe_transaccion' => $importe_transaccion,
                'metodo_pago_id' => 0, //no definido
                'referencia' => "tmp",
                'fecha_transaccion' => date("Y-m-d H:i:s"),
                'json' => json_encode($request)
            ]
        ];

        //insertamos la transaccion
        DB::table('oper_transacciones')->insert($datosTransaccion);
        $idTransaccionInsertada = DB::getPdo()->lastInsertId();

        //calculamos la fecha limite de la referencia
        $fechaLimiteReferencia = calcularFechaLimiteReferencia($tipoTramiteGeneral);

        //actualizamos la referencia en la transaccion
        $referenciaGenerada = generarReferencia($idTransaccionInsertada, $importe_transaccion, $fechaLimiteReferencia);
        DB::table('oper_transacciones')
                ->where('id_transaccion_motor', $idTransaccionInsertada)
                ->update(['referencia' => $referenciaGenerada]);
        DB::table('oper_transacciones')
                ->where('id_transaccion_motor', $idTransaccionInsertada)
                ->update(['fecha_limite_referencia' => $fechaLimiteReferencia . ' 23:59:59']);

        //si todo es ok y el importe total es cero
        if ($importe_transaccion == 0) {
            $parametros = array(
                'id_transaccion' => $idTransaccionInsertada,
                'referencia' => $referenciaGenerada
            );
            actualizaPagoCero($parametros);
            $variablesEnt = explode("|", getenv("FORMATO_RECIBO"));
            $reciboPago = $variablesEnt[1] . $idTransaccionInsertada;
        } else {
            
        }


        $tramitesLista = array();
        foreach ($tramite as $key) {//recorremos los tramites para crear la insersion 
            $datosSolicitante = $key->datos_solicitante;
            $datosFactura = $key->datos_factura;
            //insertamos el tramite.
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
            "pago_cero" => $reciboPago,
            "referencia" => $referenciaGenerada,
            "folio" => $idTransaccionInsertada,
            "cuentas" => $arrDatosCuentas,
            "tramites" => $tramitesLista
        );
        
        return $arrRespuesta;
    }

}

function actualizaPagoCero($parametros) {
    $dia = date("d");
    $mes = date("m");
    $anio = date("Y");
    $fechaEjecucion = $anio . '-' . $mes . '-' . $dia;

    DB::table('oper_transacciones')
            ->where('id_transaccion_motor', $parametros['id_transaccion'])
            ->update(['estatus' => 0]); //pagado
    DB::table('oper_transacciones')
            ->where('id_transaccion_motor', $parametros['id_transaccion'])
            ->update(['metodo_pago_id' => 3]); //ventanilla
    //buscamos si existe un registro con fecha de hoy y diferente al banco 18
    $existeRegistro = DB::table('oper_processedregisters as A')
                    ->where('A.fecha_ejecucion', $fechaEjecucion)
                    ->where('A.banco_id', '!=', 18)
                    ->get()->toArray();
    if ($existeRegistro) {
        $fechaEjecucion = date('Y', time() + 84600) . '-' . date('m', time() + 84600) . '-' . date('d', time() + 84600);
    }

    $datosRegistro = [
        [
            'origen' => 999,
            'day' => $dia,
            'month' => $mes,
            'year' => $anio,
            'monto' => 0,
            'filename' => '',
            'mensaje' => '',
            'banco_id' => 18,
            'cuenta_banco' => '',
            'cuenta_alias' => '',
            'tipo_servicio' => 0,
            'archivo_corte' => '',
            'info_transacciones' => '',
            'transaccion_id' => $parametros['id_transaccion'],
            'referencia' => $parametros['referencia'],
            'fecha_ejecucion' => $fechaEjecucion
        ]
    ];
    //insertamos el registro
    DB::table('oper_processedregisters')->insert($datosRegistro);
}

function validaTransaccionDuplicada($entidad, $idTransaccion) {
    $existe = false;

    $datosLimite = DB::table('oper_transacciones')
                    ->where('entidad', $entidad)
                    ->where('id_transaccion', $idTransaccion)
                    ->get()->toArray();

    if ($datosLimite) {
        $existe = true;
    }

    return $existe;
}

function calcularFechaLimiteReferencia($tipoServicio) {
    $datosLimite = DB::table('oper_limitereferencia as A')
                    ->join('egobierno.tipo_servicios as B', 'A.id', '=', 'B.limitereferencia_id')
                    ->select('A.vencimiento', 'A.periodicidad')
                    ->where('B.tipo_code', $tipoServicio)
                    ->get()->toArray();

    $periodicidad = isset($datosLimite[0]->periodicidad) ? $datosLimite[0]->periodicidad : "Anual";
    $vigencia = isset($datosLimite[0]->vencimiento) ? $datosLimite[0]->vencimiento : "1";

    $anio = date("Y");
    $mes = date("m");
    $dia = date("d");
    switch ($periodicidad) {
        case "Anual":
            if ($vigencia < $mes) {
                $anio += 1;
            }
            $fechaLimite = date("Y-m-t", strtotime($anio . "-" . $vigencia . "-" . $dia));
            break;
        case "Mensual":

            if ($vigencia == 0) {
                $fechaLimite = date("Y-m-t", strtotime($anio . "-" . $mes . "-" . $dia));
            } else {
                $fechaLimite = fechaVigenciaMensual($dia);
            }

            break;

        default:
            break;
    }
    return $fechaLimite;
}

function validaFechaVigenciaEstablecida($dia) {
    $fechaVigenciaEstablecidaTemp = date("Y-m-d", strtotime(date("Y") . "-" . date("m") . "-" . $dia));
    //validamos que la fecha limite establecida no sea ni sabado ni domingo
    $diasSumados = 0;
    $diaSemana = date("w", strtotime($fechaVigenciaEstablecidaTemp));
    if ($diaSemana == 6) {//si es sabado sumamos 2 dias
        $diasSumados = 2;
    } elseif ($diaSemana == 0) {//si es domingo sumamos 1 dia
        $diasSumados = 1;
    }
    $fechaVigenciaEstablecida = date("Y-m-d", strtotime($fechaVigenciaEstablecidaTemp . "+ " . $diasSumados . " days"));
    return $fechaVigenciaEstablecida;
}

function fechaVigenciaMensual($dia) {
    $diasVigencia = 3;
    $hoy = date("Y-m-d");
    $fechaVigenciaEstablecida = validaFechaVigenciaEstablecida($dia);
    $fechaVigencia = date("Y-m-d", strtotime($hoy . "+ 5 days"));
    //regresa la fecha contando 3 dias habiles omitiendo los feriados
    $fechaVigenciaBD = DB::select('SELECT nuevaFechaHabil(now(), ' . $diasVigencia . ' , 1) AS Resultado');

    if (isset($fechaVigenciaBD[0]->Resultado) && $fechaVigenciaBD[0]->Resultado != "") {
        $fechaVigencia = $fechaVigenciaBD[0]->Resultado;
    }

    $revisarFecha = explode("-", $fechaVigencia);
    $mes = date("m");
    $datetime1 = date_create($fechaVigencia);
    $datetime2 = date_create($fechaVigenciaEstablecida);
    $contador = date_diff($datetime1, $datetime2);
    $differenceFormat = '%a';


    if (
            $fechaVigenciaEstablecida < $fechaVigencia //y la fecha de vigencia es mayor
            && ($contador->format($differenceFormat) <= $diasVigencia)
    ) {
        $fechaVigencia = $fechaVigenciaEstablecida;
    }


    return $fechaVigencia;
}

function generarReferencia($idTransaccion, $importe_transaccion, $fechaLimiteReferencia) {
    $referencia = 11; //(2)servicio numero fjo 
    $referencia .= obtenerIdentificadorReferencia(); //(8)identificador de referencia se obtiene de base de datos
    $referencia .= str_pad($idTransaccion, 10, 0, STR_PAD_LEFT); //(10)id transaccion
    $referencia .= date("m"); //(2)periodo
    $referencia .= obtenerFechaCondensada($fechaLimiteReferencia); //(4)fecha condensada
    $referencia .= obtenerImporteCondensado($importe_transaccion); //(1)importe condensada
    $referencia .= 2; //(1)Verficador
    $referencia .= obtenerVerificador($referencia); //(2)digito verificador
    return $referencia;
}

function obtenerFechaCondensada($fechaLimiteReferencia) {

    $fechaLimite = explode("-", $fechaLimiteReferencia);
    $primerOp = ($fechaLimite[0] - 2013) * 372;
    $segundoOp = ($fechaLimite[1] - 1) * 31;
    $terceOp = $fechaLimite[2] - 1;

    $fechaCondensada = $primerOp + $segundoOp + $terceOp;

    return $fechaCondensada;
}

function obtenerIdentificadorReferencia() {
    $idReferencia = DB::table('identificadorreferencia')->get();
    return $idReferencia[0]->IdentificadorReferencia;
}

function obtenerImporteCondensado($importe) {
    $importeInicial = number_format($importe, 2, '', '');


    $listaFactosMultiplicacion = "73173173173173173173";
    $suma = 0;


    $longitudCantidad = strlen($importeInicial) - 1;
    for ($i = $longitudCantidad; $i >= 0; $i--) {
        $j = abs($i - $longitudCantidad);
        $suma += $importeInicial[$i] * $listaFactosMultiplicacion[$j];
        $mul = $importeInicial[$i] * $listaFactosMultiplicacion[$j];
    }
    $importeCondesado = $suma % 10;
    return $importeCondesado;
}

function obtenerVerificador($referencia) {
    $listaFactorMultiplicacion = array(11, 13, 17, 19, 23, 11, 13, 17, 19, 23, 11, 13, 17, 19, 23, 11, 13, 17, 19, 23, 11, 13, 17, 19, 23, 11, 13, 17);
    $suma = 0;
    $longitudReferencia = strlen($referencia) - 1;
    for ($i = $longitudReferencia; $i >= 0; $i--) {
        $j = abs($i - $longitudReferencia);
        $suma += $referencia[$i] * $listaFactorMultiplicacion[$j];
    }
    $digitoVerificador = ($suma % 97) + 1;

    return str_pad($digitoVerificador, 2, 0, STR_PAD_LEFT);
}
