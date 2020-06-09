<?php
namespace App\Controllers\v1;
use App\Exceptions\ShowableException;
use App\Utils\Utils;

class PredialController {
	// OPTIONS
	public static function options_index ($params, $data){
		$schemas = Utils::getSchemas($data->name, $data->version);
		return $schemas;
	}

	public static function options_districts($params, $data){
		$schemas = Utils::getSchemas($data->name, $data->version, $data->action);
		return $schemas;
	}

	// GET
	public static function get_index ($params){
		$district = self::districts($params->district);
		$exp["54000053"] = [
			"expedient" => preg_replace("/([0-9]{0,2})([0-9]{2,3})([0-9]{3,3})/i", "$1-$2-$3", $params->expedient),
			"district" => $district,
			"name" => "Garcia De Le Cruz Heliodoro Y Esp",
			"address" => "Rancho La Esperanza",
			"expiration" => "2020-06-05T00:00:00+00:00",
			"initial_year" => "2005",
			"resume" => [
				"2015" => [
					"amount" => 164.00,
					"surcharges" => 30.00,
					"subtotal" => 194.00
				],
				"2016" => [
					"amount" => 164.00,
					"surcharges" => 49.00,
					"subtotal" => 213.00
				],
				"2017" => [
					"amount" => 164.00,
					"surcharges" => 69.00,
					"subtotal" => 233.00
				],
				"2018" => [
					"amount" => 164.00,
					"surcharges" => 89.00,
					"subtotal" => 253.00
				],
				"2019" => [
					"amount" => 1607.00,
					"surcharges" => 1061.00,
					"subtotal" => 2668.00
				],
				"2020" => [
					"amount" => 164.00,
					"surcharges" => 10.00,
					"discount" => 0.00,
					"subtotal" => 174.00,
				],
			],
			"totals" => [
				"amount" => 2427.00,
				"surcharges" => 1308.00,
				"discount" => 0.00
			],
			"grand_total" => 3735.00,
			"reference" => "192754000053200210011926080278"
		];

		$exp["54000062"] = [
			"expedient" => preg_replace("/([0-9]{0,2})([0-9]{2,3})([0-9]{3,3})/i", "$1-$2-$3", $params->expedient),
			"district" => $district,
			"name" => "SOLIS SALINAS MARIO HUMBERTO",
			"address" => "CARR PE\/A BLANCA-COMALES",
			"expiration" => "2020-06-05T00:00:00+00:00",
			"initial_year" => "2011",
			"resume" => [
				"2015" => [
					"amount" => 151.00,
					"surcharges" => 27.00,
					"subtotal" => 178.00
				],
				"2016" => [
					"amount" => 151.00,
					"surcharges" => 45.00,
					"subtotal" => 196.00
				],
				"2017" => [
					"amount" => 146.00,
					"surcharges" => 61.00,
					"subtotal" => 207.00
				],
				"2018" => [
					"amount" => 133.00,
					"surcharges" => 72.00,
					"subtotal" => 205.00
				],
				"2019" => [
					"amount" => 482.00,
					"surcharges" => 318.00,
					"subtotal" => 800.00
				],
				"2020" => [
					"amount" => 161.00,
					"surcharges" => 10.00,
					"discount" => 0.00,
					"subtotal" => 171.00,
				],
			],
			"totals" => [
				"amount" => 1224.00,
				"surcharges" => 533.00,
				"discount" => 0.00
			],
			"grand_total" => 1757.00,
			"reference" => "192754000062200210011926084237"
		];

		$response = $exp[str_replace("-", "", $params->expedient)];

		return $response;
	}

	public static function get_districts($id){
		return [
			"quantity" => 31,
			"items" => self::districts()
		];
	}

	// POST
	public static function get_payment ($params) {
		$district = self::districts($params->district);
		$info = Utils::toObject(self::get_index($params));
		$expedientRaw = str_replace("-", "", $params->expedient);
		return [
			"url" => "http://10.153.144.94/egobQA/tramite.php",
			"params" => [
				[
					"name" => "json",
					"type" => "hidden",
					"value" => '{"url_retorno": "http://10.153.162.38/egob/end-process/index.php?exp='.base64_encode("{$expedientRaw}|{$district->id}|{$district->name}|{$info->grand_total}|".date("Ymd", strtotime($info->expiration))).'","importe_transaccion": 3735,"id_transaccion": 0,"entidad": "99","tramite": [{"id_seguimiento": "0","id_tipo_servicio": "106","id_tramite": "0","importe_tramite": 3735,"auxiliar_1": "'.$district->id.$expedientRaw.'","auxiliar_2": "El importe fue determinado por el municipio '.$district->name.' con expediente '.$info->expedient.' y los montos son actualizados al '.strftime("%A, %d de %B de %Y", strtotime($info->expiration)).'.","auxiliar_3": "'.$info->reference.'","datos_solicitante": {"nombre": "'.$info->name.'","apellido_paterno": "","apellido_materno": "","razon_social": "","rfc": "","curp": "","email": "","calle": "","colonia": "","numexterior": "","numinterior": "","municipio": "","codigopostal": 33},"datos_factura": {"nombre": "","apellido_paterno": "","apellido_materno": "","razon_social": "","rfc": "","curp": "","email": "","calle": "","colonia": "","numexterior": "","numinterior": "","municipio": "","codigopostal": 33},"detalle": [{"concepto": "$ '.number_format($info->totals->amount, 2).' Total de Impuesto + $ '.number_format($info->totals->surcharges, 2).' Total de Recargos - $ '.number_format($info->totals->discount, 2).' Subsidio","importe_concepto": '.$info->grand_total.',"partida": 80127}]}]}'
				]
			],
			"method" => "POST"
		];
	}

	protected static function districts ($id = null){
		$district = [
			[
				"id" => 25,
				"name" => "DR. ARROYO"
			],
			[
				"id" => 27,
				"name" => "DR. COSS"
			],
			[
				"id" => 26,
				"name" => "DR. GONZALEZ"
			],
			[
				"id" => 33,
				"name" => "ESCOBEDO"
			],
			[
				"id" => 29,
				"name" => "GALEANA"
			],
			[
				"id" => 30,
				"name" => "GARCIA"
			],
			[
				"id" => 34,
				"name" => "GENERAL TERAN"
			],
			[
				"id" => 35,
				"name" => "GENERAL TREVI\u00d1O"
			],
			[
				"id" => 32,
				"name" => "GRAL. BRAVO"
			],
			[
				"id" => 28,
				"name" => "GUADALUPE"
			],
			[
				"id" => 37,
				"name" => "HERRERAS"
			],
			[
				"id" => 38,
				"name" => "HIGUERAS"
			],
			[
				"id" => 40,
				"name" => "HUALAHUISES"
			],
			[
				"id" => 41,
				"name" => "ITURBIDE"
			],
			[
				"id" => 43,
				"name" => "LAMPAZOS"
			],
			[
				"id" => 44,
				"name" => "LINARES"
			],
			[
				"id" => 46,
				"name" => "MARIN"
			],
			[
				"id" => 49,
				"name" => "MELCHOR OCAMPO"
			],
			[
				"id" => 48,
				"name" => "MIER Y NORIEGA"
			],
			[
				"id" => 47,
				"name" => "MINA"
			],
			[
				"id" => 45,
				"name" => "MONTEMORELOS"
			],
			[
				"id" => 50,
				"name" => "PARAS"
			],
			[
				"id" => 52,
				"name" => "RAMONES"
			],
			[
				"id" => 53,
				"name" => "RAYONES"
			],
			[
				"id" => 54,
				"name" => "SABINAS HIDALGO"
			],
			[
				"id" => 55,
				"name" => "SALINAS VICTORIA"
			],
			[
				"id" => 58,
				"name" => "SAN NICOLAS DE LOS GARZA"
			],
			[
				"id" => 31,
				"name" => "SAN PEDRO GARZA GARCIA"
			],
			[
				"id" => 57,
				"name" => "SANTA CATARINA"
			],
			[
				"id" => 56,
				"name" => "SANTIAGO"
			],
			[
				"id" => 60,
				"name" => "VILLALDAMA"
			]
		];


		if($id){
			$find = array_filter(
				$district,
				function ($e) use (&$id, &$ind) {
					return $e["id"] == $id;
				}
			);
			
			return (object)current($find);

		}

		return $district;
	}
}