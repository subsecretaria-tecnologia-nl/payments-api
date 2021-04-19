<?php

namespace App\Http\Controllers;

use App\Exceptions\ResponseException;
use App\Exceptions\ShowableException;
use App\Schemas\SchemaValidator;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Controllers\v1\RespuestabancoController;

class RedirectController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        
    }

    public function index(Request $request) {
        // CACHAS DEL BANCO
        // PROCESAS LA INFO
        // ACTUALIZAS DB
        // REDIRECCIONAS A LA RUTA DE RETORNO POR POST
        $b = new RespuestabancoController();
        $res = $b->post_index($request);
        $urlRetorno = isset($res['url_response'])?$res['url_response']:"";
        $urlRetorno = "http://10.153.144.94/egobQA/pruebas/newEmptyPHP.php";
        
        $data = ["json"=>json_encode($res['datos'])];

        return Utils::redirect_with($urlRetorno, $data, 'post');
    }

}
