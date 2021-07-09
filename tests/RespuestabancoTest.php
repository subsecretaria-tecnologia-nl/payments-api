<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class RespuestabancoTest extends TestCase {

    public $idTransaccion = "2147483647";
    public $Authorization = "00000000";
    public $referencia = "00000000asdasds";
    public $importeTransaccion = "0";
    public $tokenNetpay = "ssadasdaa";

    protected function getTransaccion() {
        $datos = DB::table('oper_transacciones as A')
                ->select('A.id_transaccion_motor')
                ->orderByDesc('A.id_transaccion_motor')
                ->limit(1)
                ->get();
        $this->idTransaccion = $datos[0]->id_transaccion_motor;
    }

    protected function getTransaccionLog($banco = 'NetPay', $estatus = 0) {
        switch ($banco) {
            case "Bancomer":
                $campoBanco = \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(B.parametros, "$.n_autoriz")) as Auth');
                break;
            case "NetPay":
                $campoBanco = \DB::raw('JSON_UNQUOTE(JSON_EXTRACT(B.parametros, "$.transactionTokenId")) as transactionTokenId');
                break;
            default:
                break;
        }

        $datos = DB::table('oper_transacciones as A')
                ->select('B.idoper_log_bancos', 'A.id_transaccion_motor', 'B.parametros', $campoBanco, 'A.referencia', 'A.importe_transaccion')
                ->join('oper_log_bancos as B', 'A.id_transaccion_motor', '=', 'B.id_transaccion')
                ->where('A.estatus', $estatus)
                ->where('B.banco', $banco)
                ->where('B.proceso', 'RECIBO')
                ->orderByDesc('A.id_transaccion_motor')
                ->limit(1)
                ->get();
        $this->idTransaccion = $datos[0]->id_transaccion_motor;
        $this->referencia = $datos[0]->referencia;
        $this->importeTransaccion = $datos[0]->importe_transaccion;

        switch ($banco) {
            case "Bancomer":
                $this->Authorization = $datos[0]->Auth;
                break;
            case "NetPay":

                if ($datos[0]->transactionTokenId == 'null' || strlen($datos[0]->transactionTokenId) <= 0) {
                    $arrDato = explode('/', str_replace('"', "", $datos[0]->parametros));
                    $this->tokenNetpay = end($arrDato);
                } else {
                    $this->tokenNetpay = $datos[0]->transactionTokenId;
                }
                break;
            default:
                break;
        }
    }

    public function testRespuestabancoNoParams() {
        $parametros = array();
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();

        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertEquals("error", $VAR->response['datos']->mensaje);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoBanamexSuccess() {
        $this->getTransaccion();
        $refer_pgo = $this->idTransaccion . "11";
        $parametros = array(
            "REFER_PGO" => $refer_pgo,
            "AUTORIZA" => $this->idTransaccion
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(1, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoBanamexError() {
        $this->getTransaccion();
        $refer_pgo = $this->idTransaccion . "11";
        $parametros = array(
            "REFER_PGO" => $refer_pgo,
            "AUTORIZA" => ""
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();

        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertEquals("error", $VAR->response['datos']->mensaje);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoBancomerSuccess() {
        $this->getTransaccionLog('Bancomer', 15);
        $variablesEnt = explode("|", getenv("BANCOMER_DATA"));
        $KeyHash = $variablesEnt[0];
        $hash = hash_hmac('sha256', $this->idTransaccion . $this->referencia . $this->importeTransaccion . $this->Authorization, $KeyHash);
        $parametros = array(
            "mp_response" => 00,
            "s_transm" => $this->idTransaccion,
            "mp_signature" => $hash,
            "n_autoriz" => $this->Authorization
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(1, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoBancomerError() {
        $this->getTransaccionLog('Bancomer', 15);
        $parametros = array(
            "mp_response" => 00,
            "s_transm" => $this->idTransaccion,
            "mp_signature" => "sssaa23243324",
            "n_autoriz" => $this->Authorization
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertEquals("error", $VAR->response['datos']->mensaje);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoScotiabankSuccess() {
        $this->getTransaccion();

        $parametros = array(
            "indPago" => "1",
            "s_transm" => $this->idTransaccion
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(1, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoScotiabankError() {
        $parametros = array(
            "indPago" => "0",
            "s_transm" => $this->idTransaccion
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();

        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertEquals("error", $VAR->response['datos']->mensaje);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoNetPaySuccess() {
        $this->getTransaccionLog();
        $parametros = array(
            "transactionToken" => $this->tokenNetpay
        );

        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(1, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertArrayHasKey('0', $VAR->response['datos']->tramites);
        $this->assertEquals("Aprobada", $VAR->response['datos']->mensaje);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoNetpayErrorNotoken() {
        $parametros = array(
            "transactionToken" => $this->tokenNetpay
        );

        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertEquals('', $VAR->response['datos']->tramites);
        $this->assertEquals("No recibido", $VAR->response['datos']->mensaje);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

    public function testRespuestabancoNetpayErrorDenied() {
        $this->getTransaccionLog('NetPay', 15);
        $parametros = array(
            "transactionToken" => $this->tokenNetpay
        );
        $response = $this->json('POST', '/v1/respuestabanco', $parametros, ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertArrayHasKey('datos', $VAR->response);
        $this->assertEquals(0, $VAR->response['datos']->estatus);
        $this->assertObjectHasAttribute('tramites', $VAR->response['datos']);
        $this->assertEquals(200, $response->response->getStatusCode());
    }

}
