<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class RespuestabancoController {

    public static function post_index($request) {
        $variablesEntRecibo = explode("|", getenv("FORMATO_RECIBO"));
        $idTransaccion = -1;
        $status = 45; //estatus de la transaccion
        $estatus = 0; //estatus del la respuesta
        $mensaje = 'error'; //mensaje del servicio
        $url_recibo = "";
        $banco = "ND"; //No Definido

        $referencia = $ttlTr = $Autorizacion = 0;
        $regHash = 0;

        //validamos las variables de los bancos
        if (isset($request->REFER_PGO)) {//variable de banamex
            $banco = "Banamex";
            $idTransaccion = (isset($request->REFER_PGO)) ? substr($request->REFER_PGO, 0, -2) : "";
            $datosRespuesta = datosTransaccion($idTransaccion);
            $status = 15; //tramite no autorizado
            if (isset($request->AUTORIZA) && !empty($request->AUTORIZA)) {
                $status = 0;
                $estatus = 1;
                $mensaje = 'correcto';
                $url_recibo = $variablesEntRecibo[1] . $idTransaccion;
            }
        } else if (isset($request->mp_response)) {//variable de bancomer
            $variablesEnt = explode("|", getenv("BANCOMER_DATA"));
            $KeyHash = $variablesEnt[0];
            $banco = "Bancomer";
            $status = 15; //tramite no autorizado
            $idTransaccion = (isset($request->s_transm)) ? $request->s_transm : "";
            $datosRespuesta = datosTransaccion($idTransaccion);
            $impbco = isset($datosRespuesta['datos']['importe_transaccion']) ? $datosRespuesta['datos']['importe_transaccion'] : 0;
            $mp_response = (isset($request->mp_response)) ? $request->mp_response : "";
            $hash = (isset($request->mp_signature)) ? $request->mp_signature : "";
            $referencia = (isset($datosRespuesta['datos']['referencia'])) ? $datosRespuesta['datos']['referencia'] : "";
            $Autorizacion = (isset($request->n_autoriz)) ? $request->n_autoriz : "";
            $ttlTr = number_format($impbco, 2, ".", "");
            $regHash = hash_hmac("sha256", $idTransaccion . $referencia . $ttlTr . $Autorizacion, $KeyHash);

            if ($mp_response == "00" && (md5($hash) === md5($regHash))
            ) {//pagado
                $estatus = 1;
                $status = 0;
                $mensaje = 'correcto';
                $url_recibo = $variablesEntRecibo[1] . $idTransaccion;
            }
        } else if (isset($request->indPago)) {//variable de Scotibank
            $banco = "Scotiabank";
            $status = 15; //tramite no autorizado
            $idTransaccion = (isset($request->s_transm)) ? $request->s_transm : "";
            $estatusBanco = (isset($request->indPago)) ? $request->indPago : "";

            if ($estatusBanco == 1) {//Pagado
                $estatus = 1;
                $status = 0;
                $mensaje = 'correcto';
                $url_recibo = $variablesEntRecibo[1] . $idTransaccion;
            }
        } else if (isset($request->transactionToken)) {//variable de netpay
            $banco = "NetPay";
            $status = 15; //tramite no autorizado

            $variablesEnt = explode("|", getenv("NETPAY_DATA"));
            $url = $variablesEnt[0] . "/v1/transaction-report/transaction/";

            $lgk = getLoginToken();
            $authorization = "Authorization: Bearer " . $lgk;


            $trtkn = $request->transactionToken;
            $ch = curl_init();
            $request = $url . $trtkn;
            curl_setopt($ch, CURLOPT_URL, $request);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));

            $response = curl_exec($ch);
            $decode = json_decode($response);
            $error = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            $idTransaccion = (isset($decode->transaction->merchantReferenceCode)) ? $decode->transaction->merchantReferenceCode : 0;
            $datosRespuesta = datosTransaccion($idTransaccion);
            $impbco = (isset($decode->transaction->totalAmount)) ? $decode->transaction->totalAmount : "";
            $response = (isset($decode->response->responseCode)) ? $decode->response->responseCode : "";
            $mensaje = (isset($decode->response->responseMsg)) ? $decode->response->responseMsg : "No recibido";
            if ($response == "00") {
                $estatus = 1;
                $status = 0;
                $url_recibo = $variablesEntRecibo[1] . $idTransaccion;
            }
        }

        $parametrosLog = array(
            "id_transaccion" => $idTransaccion,
            "proceso" => 'RECIBO',
            "banco" => $banco,
            "parametros" => json_encode($request)
        );

        agregarLogEnvio($parametrosLog);
        actualizaTransaccion($idTransaccion, $status);
        $datosRespuesta['datos']['mensaje'] = $mensaje;
        $datosRespuesta['datos']['estatus'] = $estatus;
        $datosRespuesta['datos']['url_recibo'] = $url_recibo;
        $datosRespuesta['datos']['pHash'] = $idTransaccion . "_" . $referencia . "_" . $ttlTr . "_" . $Autorizacion;
        $datosRespuesta['datos']['hash'] = $regHash;

        return $datosRespuesta;
    }

}

function agregarLogEnvio($datosLog) {
    DB::table('oper_log_bancos')->insert($datosLog);
}

function datosTransaccion($idTransaccion) {
    $datosTransaccion = DB::table('oper_transacciones as OT')
            ->where('OT.id_transaccion_motor', '=', $idTransaccion)
            ->leftJoin('oper_tramites as Tr', 'OT.id_transaccion_motor', '=', 'Tr.id_transaccion_motor')
            ->select('OT.id_transaccion_motor', 'OT.id_transaccion', 'Tr.id_tramite_motor', 'Tr.id_tramite', 'OT.referencia', 'Tr.importe_tramite',
                    'OT.importe_transaccion',
                    \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(CONVERT(OT.json,CHAR), "$.url_retorno")) url_retorno'))
            ->get();
    foreach ($datosTransaccion as $valor) {
        $idTransaccion = $valor->id_transaccion_motor;
        $importeTransaccion = $valor->importe_transaccion;
        $referencia = $valor->referencia;
        $idTransaccionEntidad = $valor->id_transaccion;
        $urlRetorno = $valor->url_retorno;
        $arrTramites[] = array(
            "id_tramite_motor" => $valor->id_tramite_motor,
            "id_tramite" => $valor->id_tramite,
            "importe_tramite" => $valor->importe_tramite
        );
    }
    $datos = array(
        "url_response" => isset($urlRetorno)?$urlRetorno:"",
        "datos" => array(
            'importe_transaccion' => isset($importeTransaccion)?$importeTransaccion:0,
            'id_transaccion_motor' => isset($idTransaccion)?$idTransaccion:"",
            'id_transaccion' => isset($idTransaccionEntidad)?$idTransaccionEntidad:0,
            'referencia' => isset($referencia)?$referencia:"",
            'tramites' =>isset($arrTramites)?$arrTramites:""
        )
    );
    return $datos;
}

//actualizamos el estatus de la transaccion
function actualizaTransaccion($idTransaccion, $estatus) {
    
    
    if ($estatus == 0) {
        DB::table('oper_transacciones')
                ->where('id_transaccion_motor', $idTransaccion)
                ->update(['estatus' => $estatus]);
        DB::table('oper_transacciones')
                ->where('id_transaccion_motor', $idTransaccion)
                ->update(['fecha_pago' => DB::raw('now()')]);
    } else {
        DB::table('oper_transacciones')
                ->where('id_transaccion_motor', $idTransaccion)
                ->update(['estatus' => $estatus]);
    }
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
