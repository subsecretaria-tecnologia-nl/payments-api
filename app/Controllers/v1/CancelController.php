<?php

namespace App\Controllers\v1;

use DB;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class CancelController {

    public $estatusCancelado = 11;//Tramite Incompleto Cancelado

    public function post_index($request) {
        $respuesta = array();
        extract(get_object_vars($request));
        if (isset($referencia)) {
            if ($this->existeReferencia($referencia)) {
                if ($this->actualizaReferencia($referencia)) {
                    $respuesta = array(
                        "error" => 0,
                        "estatus" => $this->estatusCancelado,
                        "msg" => "Actualizado Correctamente"
                    );
                }
            } else {
                throw new ShowableException(424, "Referencia no encontrada");
            }
        } else {
            throw new ShowableException(425, "Parametro Requerido");
        }

        return $respuesta;
    }

    private function actualizaReferencia($referencia) {
        try {
            DB::table('oper_transacciones')
                    ->where('referencia', (string) $referencia)
                    ->update(['estatus' => $this->estatusCancelado]);
            $return = true;
        } catch (\Illuminate\Database\QueryException $pdoE) {
            throw new ShowableException(426, "Error al intentar actualizar");
            $return = false;
        }
        return $return;
    }

    private function existeReferencia($referencia) {
        $return = false;
        try {
            $ref = DB::table('oper_transacciones')
                            ->select('referencia')
                            ->where('referencia', $referencia)
                            ->get()->toArray();
        } catch (\Illuminate\Database\QueryException $pdoe) {
            throw new ShowableException(427, "Error al consultar");
        }
        if (count($ref) > 0) {
            $return = true;
        }
        return $return;
    }

}
