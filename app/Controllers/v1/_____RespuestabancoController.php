<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class RespuestabancoController {

    public static function post_index($request) {

        $idTransaccion = -1;
        $status = 45; //estatus de la transaccion
        $estatus = 0; //estatus del la respuesta
        $mensaje = 'error'; //mensaje del servicio
        $url_recibo = "";
        $tipopago = -1;

        //validamos las variables de los bancos

        if (isset($request->REFER_PGO)) {//variable de banamex
            $tipopago = 3;
            $status = 5; //tramite no autorizado

            $idTransaccion = (isset($request->REFER_PGO)) ? substr($request->REFER_PGO, 0, -2) : "";
            $status = 15; //tramite no autorizado
            if (isset($request->AUTORIZA) && !empty($request->AUTORIZA)) {
                $status = 0;
                $estatus = 1;
                $mensaje = 'correcto';
                $url_recibo = 'https://egobierno.nl.gob.mx/egob/reciboGPM.php?folio=' . $idTransaccion;
            }
        } else if (isset($request->mp_response)) {//variable de bancomer
            $tipopago = 8;
            $status = 5; //tramite no autorizado
            $idTransaccion = (isset($request->s_transm)) ? $request->s_transm : "";
            $impbco = isset($request->mp_amount) ? $request->mp_amount : $json->importe_transaccion;
            $mp_response = (isset($request->mp_response)) ? $request->mp_response : "";
            $hash = (isset($request->mp_signature)) ? $request->mp_signature : "";
            $referencia = (isset($request->c_referencia)) ? $request->c_referencia : "";
            $Autorizacion = (isset($request->n_autoriz)) ? $request->n_autoriz : "";
            $ttlTr = number_format($impbco, 2, ".", "");

            $regHash = hash_hmac("sha256", $idTransaccion . $referencia . $ttlTr . $Autorizacion, "Nljuk3u99D8383899XE8399NLi98I653rv8273WQ80202mUbbI28AO762i3828");


            if ($mp_response == "00" && (md5($hash) === md5($regHash))) {//pagado
                $estatus = 1;
                $status = 0;
                $mensaje = 'correcto';
                $url_recibo = 'https://egobierno.nl.gob.mx/egob/reciboGPM.php?folio=' . $idTransaccion;
            }
            
        } else if (isset($request->transaction->merchantReferenceCode)) {//variable de netpay
            $tipopago = 26;
            $status = 5; //tramite no autorizado
            $idTransaccion = (isset($request->transaction->merchantReferenceCode)) ? $request->transaction->merchantReferenceCode : "";
            $impbco = (isset($request->transaction->totalAmount)) ? $request->transaction->totalAmount : "";
            $response = (isset($request->response->responseCode)) ? $request->response->responseCode : "";
            $mensaje = (isset($request->response->responseMsg)) ? $request->response->responseMsg : $mensaje;
            if ($response == "00") {
                $estatus = 1;
                $status = 0;
                $url_recibo = 'https://egobierno.nl.gob.mx/egob/reciboGPM.php?folio=' . $idTransaccion;
            }
        }
    }

}
