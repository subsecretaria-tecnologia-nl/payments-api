<?php

namespace App\Http\Controllers;

use App\Exceptions\ResponseException;
use App\Exceptions\ShowableException;
use App\Schemas\SchemaValidator;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RedirectController extends Controller {

	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct() {

	}

	public function index(Request $request){
		// CACHAS DEL BANCO
		// PROCESAS LA INFO
		// ACTUALIZAS DB
		// REDIRECCIONAS A LA RUTA DE RETORNO POR POST

		$url = "http://10.153.144.218/tramites-ciudadano/cart";
		$headers = null;
		$data = [
			"token" => "LJASHFLJASHFLJAHF",
			"metodo" => "netpay"
		];
		
		return Utils::redirect_with($url, $data, 'get');
	}
}