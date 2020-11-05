<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;
use App\Controllers\v1\PayController;

class DatabankController {

    public static function post_index($request) {
//        DB::listen(function($query) {
//            //Imprimimos la consulta ejecutada
//            echo "<pre> {$query->sql } </pre>";
//        });
        extract(get_object_vars($request));
        $b = new PayController();
        $cuentasPermitidas = $b->get_index($folio);

        $datos = array(
            "error" => 1,
            "url_response" => "",
            "datos" => ""
        );

        $cuentaValida = 0;
        foreach ($cuentasPermitidas as $valor) {
            if ($valor['cuenta'] == $cuenta_id) {
                $cuentaValida = 1;
                break;
            }
        }
        if ($cuentaValida == 1) {
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
                            'Tr.razon_social', 'Tr.id_tipo_servicio', \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(CONVERT(T.json,CHAR), "$.url_retorno")) url_retorno'),
                            \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(CONVERT(T.json,CHAR),"$.url_confirma_pago")) url_confirmapago'),
                            'T.id_transaccion', 'Tr.id_tramite_motor', 'Tr.id_tramite', 'Tr.importe_tramite')
                    ->where('T.id_transaccion_motor', '=', $folio)
                    ->get();

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
            }


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
                            'Tr.razon_social', 'Tr.id_tipo_servicio', \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(CONVERT(T.json,CHAR), "$.url_retorno")) url_retorno'),
                            \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(CONVERT(T.json,CHAR),"$.url_confirma_pago")) url_confirmapago'),
                            'T.id_transaccion', 'Tr.id_tramite_motor', 'Tr.id_tramite', 'Tr.importe_tramite')
                    ->where('T.id_transaccion_motor', '=', $folio)
                    ->get();

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
                    $datos ['error'] = 3;
                    break;
            }
        } else {
            $datos ['error'] = 2;
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
    $error = 0;
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
            $variablesEnt = explode("|", getenv("BANCOMER_DATA"));
            $KeyHash = $variablesEnt[0];
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
                'mp_signature' => hash_hmac('sha256', $dT[0]->id_transaccion_motor . $dT[0]->referencia . $dT[0]->importe_transaccion, $KeyHash)
            );
            actualizaTipoPago($idTransaccion, 9); //bancomer
            break;
        case "Banamex":
            $url_response = "paginaBanamex";
            list($EWFSYS0, $EDIFY) = explode("|", getenv("BANAMEX_DATA"));
            $totalTramite_ = number_format($totalTransaccion, 2, '.', '');
            $extrados = extradosBanamex($tipoServicioRepositorio, $idTransaccion, $totalTramite_);
            $datosBanco = array(
                'EWF_SYS_0' => $EWFSYS0,
                'EWF_FORM_NAME' => 'index',
                'BANKID' => $EDIFY,
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
            $url_response = "paginaScotiabank";
            $variablesEnt = explode("|", getenv("SCOTIABANK_DATA"));
            $xhdnContrato = $variablesEnt[0];
            $transm = str_pad($idTransaccion, 20, "0", STR_PAD_LEFT);
            $referencia = str_pad($idTransaccion, 9, "0", STR_PAD_LEFT) . str_pad($dT[0]->id_tipo_servicio, 3, "0", STR_PAD_LEFT) . date('Ymd');
            $servicio = str_pad($dT[0]->id_tipo_servicio, 3, "0", STR_PAD_LEFT);

            $datosBanco = array(
                'hdnContrato' => $xhdnContrato,
                's_transm' => $transm,
                'c_referencia' => $referencia,
                't_servicio' => $servicio,
                't_importe' => $totalTransaccion,
                'val_1' => '0'
            );
            actualizaTipoPago($idTransaccion, 10); //scotiabank
            break;
        default:
            $error = 5;
            $url_response = "";
            $datosBanco = "";
            break;
    }
    $datosEnvio = array(
        "error" => $error,
        "url_response" => $url_response,
        "datos" => $datosBanco
    );
    $parametrosLog = array(
        "id_transaccion" => $idTransaccion,
        "proceso" => 'ENVIO',
        "banco" => $banco,
        "parametros" => json_encode($datosEnvio)
    );
    agregarLogEnvio($parametrosLog);
    actualizaEstatusTransaccion($idTransaccion, 5);
    return $datosEnvio;
}

function agregarLogEnvio($datosLog) {
    DB::table('oper_log_bancos')->insert($datosLog);
}

function datosEnvioBancoTC($dT, $banco) {
    $error = 0;
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
            $variablesEnt = explode("|", getenv("BANCOMER_DATA"));
            $KeyHash = $variablesEnt[0];
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
                'mp_signature' => hash_hmac('sha256', $dT[0]->id_transaccion_motor . $dT[0]->referencia . $dT[0]->importe_transaccion, $KeyHash)
            );
            $parametrosLog = array(
                "id_transaccion" => $idTransaccion,
                "proceso" => 'ENVIO',
                "banco" => $banco,
                "parametros" => json_encode($datosBanco)
            );
            agregarLogEnvio($parametrosLog);
            actualizaTipoPago($idTransaccion, 8); //bancomer TC
            break;
        case "NetPay"://netpay
            $url_response = "paginaNetPay";
            $variablesEnt = explode("|", getenv("NETPAY_DATA"));
            $URLNPC = $variablesEnt[0] . "/v2.1.0/checkout";
            $postid = $idTransaccion;
            $storeIdAcq = $variablesEnt[3];        ### SANDBOX
            $postttl = $totalTransaccion;

######## PREAPARCION DE JSON PARA CHECKOUT Y PAGO ##########################

            $lgk = getLoginToken();

            foreach ($dT as $ktram => $vtram) {
                $descripcion = tipoServicioDesc($vtram->id_tipo_servicio);
                $productName = $descSerEgob = $descripcion->Tipo_Descripcion;
                $itemList[] = array(
                    "id" => $vtram->id_tramite,
                    "productSKU" => "000",
                    "unitPrice" => $vtram->importe_tramite, // total de operacion
                    "productName" => $productName,
                    "quantity" => 1,
                    "productCode" => $vtram->id_tramite
                );
            }

            $data = array(
                "storeIdAcq" => $storeIdAcq,
                "transType" => "Auth",
                "promotion" => "000000",
                "checkout" => array(
                    "cardType" => "004",
                    "merchantReferenceCode" => $postid,
                    // "bill" => $bill,
                    // "ship" => $ship,
                    "itemList" => $itemList,
                    "purchaseTotals" => array(
                        "grandTotalAmount" => $postttl, // total de operacion
                        "currency" => "MXN"
                    ),
                    "merchanDefinedDataList" => array(
                        array("id" => 2, "value" => "Web"),
                        array("id" => 20, "value" => "SERVICIO"),
                        // array("id" => 23,"value" => "JUAN PEREZ"),           /// CAMBIAR POR PARAM DE PROD.
                        // array("id" => 35,"value" => ""),
                        array("id" => 36, "value" => "Frecuente"),
                        array("id" => 37, "value" => "Si"),
                        // array("id" => 38,"value" => ""),
                        // array("id" => 39,"value" => ""),
                        // array("id" => 40,"value" => ""),
                        // array("id" => 41,"value" => ""),
                        array("id" => 42, "value" => "GOBIERNO DEL ESTADO DE NUEVO LEON"),
                        array("id" => 43, "value" => $storeIdAcq),
                        // array("id" => 44,"value" => "Monterrey"),
                        // array("id" => 45,"value" => "64000"),
                        array("id" => 46, "value" => $storeIdAcq),
                        // array("id" => 93,"value" => "1234567890"),   /// AGREGAR PARAMETRO DE PROD.
                        array("id" => 94, "value" => $postid)
                    ),
                )
            );

            $authorization = "Authorization: Bearer " . $lgk;
            $data_string = json_encode($data);
            $parametrosLog = array(
                "id_transaccion" => $idTransaccion,
                "proceso" => 'ENVIO ARMADO',
                "banco" => $banco,
                "parametros" => $data_string
            );
            agregarLogEnvio($parametrosLog);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $URLNPC);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $decode = json_decode($response);
            $errorCurl = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($info['http_code'] == 200) {
                $newurl = urlencode(base64_encode('https://egobierno.nl.gob.mx/egob/netpay/np_answer_.php'));
                $url_response = $decode->response->webAuthorizerUrl
                        . '?checkoutTokenId=' . $decode->response->checkoutTokenId
                        . '&checkoutDetail=true&MerchantResponseURL=' . $newurl;
                $datosBanco = array(
                    'jwt' => $lgk
                );
            }
            $parametrosLog = array(
                "id_transaccion" => $idTransaccion,
                "proceso" => 'ENVIO',
                "banco" => $banco,
                "parametros" => json_encode($datosBanco)
            );
            agregarLogEnvio($parametrosLog);
            actualizaTipoPago($idTransaccion, 26); //netpay
            break;
        default:
            $error = 4;
            $url_response = "";
            $datosBanco = "";
            break;
    }
    $datosEnvio = array(
        "error" => $error,
        "url_response" => $url_response,
        "datos" => $datosBanco
    );

    actualizaEstatusTransaccion($idTransaccion, 5);
    return $datosEnvio;
}

function tipoServicioDesc($idTipoServicio) {
    $descripcion = array("ND");
    try {
        $descripcion = DB::table('egobierno.tipo_servicios')
                        ->select('Tipo_Code', 'Tipo_Descripcion')
                        ->where('Tipo_Code', '=', $idTipoServicio)
                        ->get()->toArray();
    } catch (\Exception $e) {
        
    }

    return $descripcion[0];
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
        'recibo' => [
            'url' => $urlFormatoPago . $idTransaccion,
            'pdf' => null
        ],
        'tramites' => $arrTramites
    );

    if ($urlConfirmaPago != '') {
        consumirUrlConfirmaPago($urlConfirmaPago, $json_retorno);
    }

    actualizaEstatusTransaccion($idTransaccion, $estatus);

    $datosEnvio = array(
        "error" => 0,
        "url_response" => $urlRetorno,
        "datos" => $json_retorno
    );

    return $datosEnvio;
}

function consumirUrlConfirmaPago($urlConfirmaPago, $json_retorno) {
    $execx = "ND";
    try {
        $chx = curl_init($urlConfirmaPago);
        $curl_optionsx = array(
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => array(
                'json' => json_encode($json_retorno)
            )
        );
        curl_setopt_array($chx, $curl_optionsx);
        $execx = curl_exec($chx);
        curl_close($chx);
    } catch (\Exception $e) {
        $execx = $e;
    }
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

function getLoginToken() {

    $variablesEnt = explode("|", getenv("NETPAY_DATA"));
    $URLNPL = $variablesEnt[0] . "/v1/auth/login";
    $USR = $variablesEnt[1];
    $PSS = $variablesEnt[2];

    $return = "";
    try {

        $data_string = json_encode(array("security" => array("userName" => $USR, "password" => $PSS)));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URLNPL);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $response = curl_exec($ch);


        $decode = json_decode($response);
        $errorCurl = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $return = $decode->token;
    } catch (\Exception $e) {

// $_SESSION["MensajeError"] = "No es posible procesar la informacion por este medio de pago -- Err #200 -- CHKOUT.";
// header("Location:../ErrorNP.php");
// exit;
//        $return = "";
    }
    return $return;
}
