<?php

namespace App\Http\Controllers;

use App\Exceptions\ResponseException;
use App\Exceptions\ShowableException;
use App\Schemas\SchemaValidator;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FrontController extends Controller {

	/**
	 * Create a new controller instance.
	 *
	 * @return void
	 */
	public function __construct() {
		
	}

	public function index(Request $request){
		// OBTENER PAYLOAD (JSON)
		// CONSULTAMOS METODOS DE PAGO
		// REDIRECCIONAMOS AL BANCO O REFERENCIA

		return view("metodos-pago");
	}
}