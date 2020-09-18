<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class DataBankController {

    public static function post_index($request) {
        DB::listen(function($query) {
            //Imprimimos la consulta ejecutada
            echo "<pre> {$query->sql } </pre>";
        });
        extract(get_object_vars($request));
        //obtenemos el metodo y el banco segun la cuenta que seleccionan
        $datosCuenta = DB::table('oper_cuentasbanco as CB')
                ->join('oper_banco as OB', 'OB.id', '=', 'CB.banco_id')
                ->where('CB.id', '=', $cuenta_id)
                ->select('CB.metodopago_id', 'CB.banco_id', 'OB.nombre')
                ->get();

        //obtenemos los datos de la transaccion
        $datosTransaccion = DB::table('oper_transacciones as T')
                ->leftJoin('oper_tramites as Tr', 'T.id_transaccion_motor', '=', 'Tr.id_transaccion_motor')
                ->select('T.id_transaccion_motor', 'T.referencia', 'T.importe_transaccion', 'Tr.nombre', 'Tr.apellido_paterno', 'Tr.apellido_materno',
                        'Tr.razon_social', 'Tr.id_tipo_servicio', \DB::raw('JSON_EXTRACT(T.json, "$.url_retorno") url_retorno')
                        , \DB::raw('JSON_EXTRACT(T.json, "$.url_confirma_pago") url_confirmapago'),
                        'T.id_transaccion', 'Tr.id_tramite_motor', 'Tr.id_tramite', 'Tr.importe_tramite')
                ->where('T.id_transaccion_motor', '=', $folio)
                ->get();
        $datosCuenta[0]->metodopago_id = 2;
        $datosCuenta[0]->nombre = 'Bancomer';
        switch ($datosCuenta[0]->metodopago_id) {
            case "1"://Tarjeta de credito
                $datos = datosEnvioBancoTC($datosTransaccion, $datosCuenta[0]->nombre);
                break;
            case "2"://spei
                //actualizamos la referencia en la transaccion
                $datos = datosEnvioReferencia($datosTransaccion);
                break;
            case "3"://ventanilla
                //actualizamos la referencia en la transaccion
                $datos = datosEnvioReferencia($datosTransaccion);
                break;

            default:
                $datos = "ND";
                break;
        }
        dd($datos);

        $arrRespuesta = $datos;
        return $arrRespuesta;
    }

}

function tipoServicioBanco($tipoServicioRepositorio, $banco) {

    $tipoServicio = DB::table('egobierno.tiposerviciobancos as TSB')
            ->select('TSB.Code_Banco')
            ->where('TSB.Tipo_Code', '=', $tipoServicioRepositorio)
            ->where('TSB.Banco', '=', $banco)
            ->get();
    $tipoServicioBanco = 00;
    if (count($tipoServicio) > 0) {
        $tipoServicioBanco = $tipoServicio[0]->Code_Banco;
    }
    return $tipoServicioBanco;
}

function datosEnvioBancoTC($dT, $banco) {
    $primerRegistro = $dT[0];
    $tipoServicioRepositorio = $primerRegistro->id_tipo_servicio;
    $tipoServicioBanco = tipoServicioBanco($tipoServicioRepositorio, $banco);
    $idTransaccion = $primerRegistro->id_transaccion_motor;
    $totalTransaccion = $primerRegistro->importe_transaccion;
    $referencia = $primerRegistro->referencia;
    $nombreRS = trim(
            $primerRegistro->nombre . ' ' . $primerRegistro->apellido_paterno . ' ' . $primerRegistro->apellido_materno . ' ' .
            $primerRegistro->razon_social
    );
    switch ($banco) {
        case "Bancomer"://bancomer
            $url_response = "paginaBancomer";
            $datosBanco = array(
                's_transm' => $idTransaccion,
                's_transm' => $idTransaccion,
                'c_referencia' => $referencia,
                't_servicio' => str_pad($tipoServicioBanco, 3, "0", STR_PAD_LEFT),
                't_importe' => $totalTransaccion,
                'n_contribuyente' => $nombreRS,
                'val_1' => number_format("0", 2),
                'val_2' => $dT[0]->id_tipo_servicio == 168 ? "8100000000" : "", // telefono
                'val_3' => "", // correo
                'val_4' => "A",
                'val_8' => "010", //tipopago del banco (TC)
                'mp_signature' => hash_hmac('sha256', $dT[0]->id_transaccion_motor . $dT[0]->referencia . $dT[0]->importe_transaccion, 'Nljuk3u99D8383899XE8399NLi98I653rv8273WQ80202mUbbI28AO762i3828')
            );
            break;
        case "NetPay"://netpay
            $url_response = "paginaNetPay";
            $datosBanco = array(
            );
            break;
        case "Banamex"://netpay
            $url_response = "paginaBanamex";
            $datosBanco = array(
            );
            break;
        default:
            $url_response = "paginaError";
            $datosBanco = array(
                "dato" => "1"
            );
            break;
    }
    $datosEnvio = array(
        "url_response" => $url_response,
        "datos" => $datosBanco
    );
    return $datosEnvio;
}

function datosEnvioReferencia($datosTransaccion) {
    $arrTramites = array();
    $idTransaccion = $idTransaccionEntidad = -1;
    $urlRetorno = $urlConfirmaPago = "";
    foreach ($datosTransaccion as $valor) {
        $idTransaccion = $valor->id_transaccion_motor;
        $idTransaccionEntidad = $valor->id_transaccion;
        $urlRetorno =$valor->url_retorno;
        $urlConfirmaPago =$valor->url_confirmapago;
        $arrTramites[] = array(
            "id_tramite_motor" => $valor->id_tramite_motor,
            "id_tramite" => $valor->id_tramite,
            "importe_tramite" => $valor->importe_tramite,
            "pdf" => 'http://cfdi2.nl.gob.mx/obtXMLPDF/obtenerPDF.php?folio=NL' . $valor->id_tramite_motor,
            "xml" => 'http://cfdi2.nl.gob.mx/obtXMLPDF/obtenerXML.php?folio=NL' . $valor->id_tramite_motor
        );
    }

    $json_retorno = array(
        'urlRetorno' => $urlRetorno,
        'urlConfirmaPago' => $urlConfirmaPago,
        'id_transaccion_motor' => $idTransaccion,
        'id_transaccion' => $idTransaccionEntidad,
        'tramites' => $arrTramites
    );
    return $json_retorno;
}
