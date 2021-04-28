<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class CancelController {

    public static function post_index($request) {
        $respuesta = array("error" => 1, "mensaje" => "desconocido");
        extract(get_object_vars($request));
        if (isset($referencia)) {
            if (existeReferencia($referencia)) {
                if (actualizaReferencia($referencia)) {
                    $respuesta = array(
                        "error" => 0,
                        "msg" => "Actualizado Correctamente"
                    );
                } else {
                    throw new ShowableException(423, "Sin Actualizacion");
                }
            } else {
                throw new ShowableException(424, "Referencia no encontrada");
            }
        } else {
            throw new ShowableException(425, "Parametro Requerido");
        }

        return $respuesta;
    }

}

function actualizaReferencia($referencia) {
    $return = false;
    try {
        DB::table('oper_transacciones')
                ->where('referencia', (string) $referencia)
                ->update(['estatus' => 65]);
        $return = true;
    } catch (PdoExcepcion $pdoE) {
        throw new ShowableException(426, "Error al intentar actualizar");
    }
    return $return;
}

function existeReferencia($referencia) {
    $return = false;
    try {
        $ref = DB::table('oper_transacciones')
                        ->select('referencia')
                        ->where('referencia', $referencia)
                        ->get()->toArray();
    } catch (PDOException $pdoe) {
        throw new ShowableException(427, "Error al consultar");
    }
    if (count($ref) > 0) {
        $return = true;
    }
    return $return;
}
