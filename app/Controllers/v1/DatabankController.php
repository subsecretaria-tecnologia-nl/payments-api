<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class DatabankController {

    public static function post_index($request) {
//        DB::listen(function($query) {
//            //Imprimimos la consulta ejecutada
//            echo "<pre> {$query->sql } </pre>";
//        });
        extract(get_object_vars($request));
        //obtenemos el metodo y el banco segun la cuenta que seleccionan
        $datosCuenta = DB::table('oper_cuentasbanco as CB')
                ->join('oper_banco as OB', 'OB.id', '=', 'CB.banco_id')
                ->where('CB.id', '=', $cuenta_id)
                ->select('CB.metodopago_id', 'CB.banco_id', 'OB.nombre AS nombre_banco')
                ->get();

        //obtenemos los datos de la transaccion
        $datosTransaccion = DB::table('oper_transacciones as T')
                ->leftJoin('oper_tramites as Tr', 'T.id_transaccion_motor', '=', 'Tr.id_transaccion_motor')
                ->select('T.id_transaccion_motor', 'T.referencia', 'T.importe_transaccion', 'Tr.nombre', 'Tr.apellido_paterno', 'Tr.apellido_materno',
                        'Tr.razon_social', 'Tr.id_tipo_servicio', \DB::raw('JSON_EXTRACT(CONVERT(T.json,CHAR), "$.url_retorno") url_retorno'),
                        \DB::raw('JSON_EXTRACT(CONVERT(T.json,CHAR),"$.url_confirma_pago") url_confirmapago'),
                        'T.id_transaccion', 'Tr.id_tramite_motor', 'Tr.id_tramite', 'Tr.importe_tramite')
                ->where('T.id_transaccion_motor', '=', $folio)
                ->get();
//        $datosCuenta[0]->metodopago_id = 1;
//        $datosCuenta[0]->nombre = 'Bancomer';
        switch ($datosCuenta[0]->metodopago_id) {
            case "1"://Tarjeta de credito
                $datos = datosEnvioBancoTC($datosTransaccion, $datosCuenta[0]->nombre_banco);
                break;
            case "2"://spei
                //actualizamos la referencia en la transaccion
                $datos = datosEnvioReferencia($datosTransaccion, 2);
                break;
            case "3"://ventanilla
                //actualizamos la referencia en la transaccion
                $datos = datosEnvioReferencia($datosTransaccion, 3);
                break;
            case "4"://bancos en linea
                //actualizamos la referencia en la transaccion
                $datos = datosEnvioBancoLinea($datosTransaccion, $datosCuenta[0]->nombre_banco);
                break;

            default:
                $datos = "ND";
                break;
        }
        return $datos;
    }

}

function tipoServicioBanco($tipoServicioRepositorio, $banco) {
    $tipoServicio = DB::table('egobierno.tiposerviciobancos as TSB')
            ->join('egobierno.tipo_servicios as TS', 'TSB.Tipo_Code', '=', 'TS.Tipo_Code')
            ->select('TSB.Code_Banco', 'TS.Tipo_Descripcion')
            ->where('TSB.Tipo_Code', '=', $tipoServicioRepositorio)
            ->where('TSB.Banco', '=', $banco)
            ->get();
    $tipoServicioBanco = array(
        'id' => 00,
        'descripcion' => 'ND'
    );
    if (count($tipoServicio) > 0) {
        $tipoServicioBanco = array(
            'id' => $tipoServicio[0]->Code_Banco,
            'descripcion' => $tipoServicio[0]->Tipo_Descripcion
        );
    }

    return $tipoServicioBanco;
}

function datosEnvioBancoLinea($dT, $banco) {
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
            $url_response = "paginaBancomerLinea";
            $datosBanco = array(
                's_transm' => $idTransaccion,
                'c_referencia' => $referencia,
                't_servicio' => str_pad($tipoServicioBanco['id'], 3, "0", STR_PAD_LEFT),
                't_importe' => $totalTransaccion,
                'n_contribuyente' => $nombreRS,
                'val_1' => number_format("0", 2),
                'val_2' => $dT[0]->id_tipo_servicio == 168 ? "8100000000" : "", // telefono
                'val_3' => "", // correo
                'val_4' => "A",
                'val_8' => "100", //tipopago del banco (TC)
                'mp_signature' => hash_hmac('sha256', $dT[0]->id_transaccion_motor . $dT[0]->referencia . $dT[0]->importe_transaccion, 'Nljuk3u99D8383899XE8399NLi98I653rv8273WQ80202mUbbI28AO762i3828')
            );
            actualizaTipoPago($idTransaccion, 9); //bancomer
            break;
        case "Banamex":
            $url_response = "paginaBanamex";
            $totalTramite_ = number_format($totalTransaccion, 2, '.', '');
            $extrados = extradosBanamex($tipoServicioRepositorio, $idTransaccion, $totalTramite_);
            $datosBanco = array(
                'EWF_SYS_0' => '4eebd5b1-3824-11d5-929d-0050dae9973a',
                'EWF_FORM_NAME' => 'index',
                'BANKID' => 'EDIFY',
                'PRODUCTNAME' => 'EBS',
                'EWFBUTTON' => '',
                'EXTRA1' => 'SPANISH',
                'EXTRA2' => $extrados,
                'EXTRA3' => '',
                'EXTRA4' => 'NO_ERROR',
                'LANGUAJEID' => '1',
                't1' => $totalTransaccion,
                'total_pagar_cc' => $totalTramite_,
                'mens' => '0',
                'imp' => $totalTramite_
            );

            actualizaTipoPago($idTransaccion, 3); //banamex
            break;
        case "Scotiabank":
            $url_response = "paginaBanamex";
            $datosBanco = array(
            );
            actualizaTipoPago($idTransaccion, 10); //scotiabank
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

    actualizaEstatusTransaccion($idTransaccion, 5);
    return $datosEnvio;
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
                'c_referencia' => $referencia,
                't_servicio' => str_pad($tipoServicioBanco['id'], 3, "0", STR_PAD_LEFT),
                't_importe' => $totalTransaccion,
                'n_contribuyente' => $nombreRS,
                'val_1' => number_format("0", 2),
                'val_2' => $dT[0]->id_tipo_servicio == 168 ? "8100000000" : "", // telefono
                'val_3' => "", // correo
                'val_4' => "A",
                'val_8' => "010", //tipopago del banco (TC)
                'mp_signature' => hash_hmac('sha256', $dT[0]->id_transaccion_motor . $dT[0]->referencia . $dT[0]->importe_transaccion, 'Nljuk3u99D8383899XE8399NLi98I653rv8273WQ80202mUbbI28AO762i3828')
            );
            actualizaTipoPago($idTransaccion, 8); //bancomer TC
            break;
        case "NetPay"://netpay
            $url_response = "paginaNetPay";
            $datosBanco = array(
            );
            actualizaTipoPago($idTransaccion, 26); //netpay
            break;
        case "Banamex"://netpay
            $url_response = "paginaBanamex";
            $datosBanco = array(
            );
            actualizaTipoPago($idTransaccion, 3); //banamex
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

    actualizaEstatusTransaccion($idTransaccion, 5);
    return $datosEnvio;
}

function datosEnvioReferencia($datosTransaccion, $metodoPago) {
    $arrTramites = array();
    $idTransaccion = $idTransaccionEntidad = -1;
    $urlRetorno = $urlConfirmaPago = "";
    $urlFormatoPago = 'http://egobierno.nl.gob.mx/egob/formatoRepositorioQA.php?Folio=';
    $estatus = 60; //ventanilla
    if ($metodoPago == 2) {//spei
        $estatus = 70; //spei
    }
    foreach ($datosTransaccion as $valor) {
        $idTransaccion = $valor->id_transaccion_motor;
        $referencia = $valor->referencia;
        $idTransaccionEntidad = $valor->id_transaccion;
        $urlRetorno = $valor->url_retorno;
        $urlConfirmaPago = $valor->url_confirmapago;
        $arrTramites[] = array(
            "id_tramite_motor" => $valor->id_tramite_motor,
            "id_tramite" => $valor->id_tramite,
            "importe_tramite" => $valor->importe_tramite
        );
    }
    $json_retorno = array(
        'id_transaccion_motor' => $idTransaccion,
        'id_transaccion' => $idTransaccionEntidad,
        'mensaje' => "referencia",
        'estatus' => "2",
        'referencia' => $referencia,
        'url_recibo' => $urlFormatoPago . $idTransaccion,
        'tramites' => $arrTramites
    );
    if ($urlConfirmaPago != '') {
        consumirUrlConfirmaPago($urlConfirmaPago, $json_retorno);
    }

    actualizaEstatusTransaccion($idTransaccion, $estatus);

    $datosEnvio = array(
        "url_response" => $urlRetorno,
        "datos" => $json_retorno
    );

    return $datosEnvio;
}

function consumirUrlConfirmaPago($urlConfirmaPago, $json_retorno) {
    //pendiente
}

function actualizaTipoPago($idTransaccion, $tipoPago) {
    DB::table('oper_transacciones')
            ->where('id_transaccion_motor', $idTransaccion)
            ->update(['tipo_pago' => $tipoPago]);
}

function actualizaEstatusTransaccion($idTransaccion, $estatus) {
    DB::table('oper_transacciones')
            ->where('id_transaccion_motor', $idTransaccion)
            ->update(['estatus' => $estatus]);
}

function extradosBanamex($ts, $folio, $importe) {

    $control = DB::table('egobierno.control as C')->get()->toArray();

    if ($ts == 1) {
        $suma = 559 + 899;
    } else {
        $suma = 319 + 635;
    }

// Genera digito verificador
    $Verifica = str_pad($folio, 8, '0', STR_PAD_LEFT);

    for ($i = 0; $i <= 7; $i++) {

        $Valor = substr($Verifica, $i, 1);
        switch ($i) {
            case 0:
                $suma = $suma + ($Valor * 11);
                break;
            case 1:
                $suma = $suma + ($Valor * 13);
                break;
            case 2:
                $suma = $suma + ($Valor * 17);
                break;
            case 3:
                $suma = $suma + ($Valor * 19);
                break;
            case 4:
                $suma = $suma + ($Valor * 23);
                break;
            case 5:
                $suma = $suma + ($Valor * 29);
                break;
            case 6:
                $suma = $suma + ($Valor * 31);
                break;
            case 7:
                $suma = $suma + ($Valor * 37);
                break;
        }
    }

    $r = fmod($suma, 97);
    $digito = 99 - $r;
    $Long = strlen($folio) + 2;
    $folioDig = str_pad($folio, $Long, $digito, STR_PAD_RIGHT);

    //verifica servicio
    $tipoServicioBanco = tipoServicioBanco($ts, 'Banamex');

    // Arma variable EXTRA2
    
    $EXTRA2 = $control[0]->Banamex_Cliente . '|' . $control[0]->Banamex_Dominio . '|' . $tipoServicioBanco['id'];
    $EXTRA2 = $EXTRA2 . '|' . number_format($importe, 2, "", "") . '|99/99/9999|' . $tipoServicioBanco['descripcion'];
    $EXTRA2 = $EXTRA2 . '|' . $folioDig;
    return $EXTRA2;
}
