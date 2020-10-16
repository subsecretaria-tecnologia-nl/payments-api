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

        $referencia= $ttlTr= $Autorizacion=0;
        
        //validamos las variables de los bancos
        if (isset($request->REFER_PGO)) {//variable de banamex
            
            $idTransaccion = (isset($request->REFER_PGO)) ? substr($request->REFER_PGO, 0, -2) : "";
            $datosRespuesta = datosTransaccion($idTransaccion);
            $status = 15; //tramite no autorizado
            if (isset($request->AUTORIZA) && !empty($request->AUTORIZA)) {
                $status = 0;
                $estatus = 1;
                $mensaje = 'correcto';
                $url_recibo = 'http://10.153.144.94/egobQA/recibopago.php?folio=' . $idTransaccion;
            }
        } else if (isset($request->mp_response)) {//variable de bancomer
            $status = 15; //tramite no autorizado
            $idTransaccion = (isset($request->s_transm)) ? $request->s_transm : "";
            $datosRespuesta = datosTransaccion($idTransaccion);
            $impbco = isset($datosRespuesta['datos']['importe_transaccion']) ? $datosRespuesta['datos']['importe_transaccion'] : 0;
            $mp_response = (isset($request->mp_response)) ? $request->mp_response : "";
            $hash = (isset($request->mp_signature)) ? $request->mp_signature : "";
            $referencia = (isset($datosRespuesta['datos']['referencia'])) ? $datosRespuesta['datos']['referencia'] : "";
            $Autorizacion = (isset($request->n_autoriz)) ? $request->n_autoriz : "";
            $ttlTr = number_format($impbco, 2, ".", "");
            $regHash = hash_hmac("sha256", $idTransaccion . $referencia . $ttlTr . $Autorizacion, "Nljuk3u99D8383899XE8399NLi98I653rv8273WQ80202mUbbI28AO762i3828");

            if ($mp_response == "00" && (md5($hash) === md5($regHash))
            ) {//pagado
                $estatus = 1;
                $status = 0;
                $mensaje = 'correcto';
                $url_recibo = 'http://10.153.144.94/egobQA/recibopago.php?folio=' . $idTransaccion;
            }
        } else if (isset($request->transaction->merchantReferenceCode)) {//variable de netpay
            $status = 15; //tramite no autorizado
            $idTransaccion = (isset($request->transaction->merchantReferenceCode)) ? $request->transaction->merchantReferenceCode : "";
            $impbco = (isset($request->transaction->totalAmount)) ? $request->transaction->totalAmount : "";
            $response = (isset($request->response->responseCode)) ? $request->response->responseCode : "";
            $mensaje = (isset($request->response->responseMsg)) ? $request->response->responseMsg : $mensaje;
            if ($response == "00") {
                $estatus = 1;
                $status = 0;
                $url_recibo = 'http://10.153.144.94/egobQA/recibopago.php?folio=' . $idTransaccion;
            }
        }
        actualizaTransaccion($idTransaccion, $status);
        $datosRespuesta['datos']['mensaje'] = $mensaje;
        $datosRespuesta['datos']['estatus'] = $estatus;
        $datosRespuesta['datos']['url_recibo'] = $url_recibo;
        $datosRespuesta['datos']['pHash'] = $idTransaccion . "_" . $referencia . "_" . $ttlTr . "_" . $Autorizacion;
        $datosRespuesta['datos']['hash'] = $regHash;

        return $datosRespuesta;
    }

}

function datosTransaccion($idTransaccion) {
    $datosTransaccion = DB::table('oper_transacciones as OT')
            ->where('OT.id_transaccion_motor', '=', $idTransaccion)
            ->leftJoin('oper_tramites as Tr', 'OT.id_transaccion_motor', '=', 'Tr.id_transaccion_motor')
            ->select('OT.id_transaccion_motor', 'OT.id_transaccion', 'Tr.id_tramite_motor', 'Tr.id_tramite', 'OT.referencia', 'Tr.importe_tramite',
                    'OT.importe_transaccion',
                    \DB::raw('JSON_EXTRACT(CONVERT(OT.json,CHAR), "$.url_retorno") url_retorno'))
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
        "url_response" => $urlRetorno,
        "datos" => array(
            'importe_transaccion' => $importeTransaccion,
            'id_transaccion_motor' => $idTransaccion,
            'id_transaccion' => $idTransaccionEntidad,
            'referencia' => $referencia,
            'tramites' => $arrTramites
        )
    );
    return $datos;
}

//actualizamos el estatus de la transaccion
function actualizaTransaccion($idTransaccion, $estatus) {
    DB::table('oper_transacciones')
            ->where('id_transaccion_motor', $idTransaccion)
            ->update(['estatus' => $estatus]);
}
