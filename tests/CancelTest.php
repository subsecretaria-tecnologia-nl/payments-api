<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

//use Illuminate\Support\Facades\DB;


class CancelTest extends TestCase {

    /**
     * A basic test example.
     *
     * @return void
     */
    public $referencia = "XXss11";

    protected function getReferencia() {
        $datosLimite = DB::table('oper_transacciones as A')
                ->select('A.referencia')
                ->where('A.referencia', '!=', '')
                ->orderByDesc('A.id_transaccion_motor')
                ->limit(1)
                ->get();
        $this->referencia = $datosLimite[0]->referencia;
    }

    public function testCancelSuccess() {
        $this->getReferencia();
        $response = $this->json('POST', '/v1/cancel', ['referencia' => $this->referencia], ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertEquals(200, $response->response->getStatusCode());
        $this->assertEquals("response", $VAR->data);
        $this->assertEquals(0, $VAR->response['error']);
    }

    public function testCancelQueryErrorActualiza() {
        $this->getReferencia();
        DB::statement('ALTER TABLE `operacion`.`oper_transacciones` CHANGE COLUMN `estatus` `top` TINYINT(4) NOT NULL ;');
        $response = $this->json('POST', '/v1/cancel', ['referencia' => $this->referencia], ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertEquals(426, $response->response->getStatusCode());
        $this->assertEquals("Error al intentar actualizar", $VAR->error['message']);
        DB::statement('ALTER TABLE `operacion`.`oper_transacciones` CHANGE COLUMN `top` `estatus` TINYINT(4) NOT NULL ;');
    }

    public function testCancelQueryErrorConsulta() {

        $host = config('database.connections.db_operacion.host');

        config(['database.connections.db_operacion.host' => '127.0.0.1']);
        $response = $this->json('POST', '/v1/cancel', ['referencia' => 'eee'], ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertEquals(427, $response->response->getStatusCode());
        $this->assertEquals("Error al consultar", $VAR->error['message']);
        config(['database.connections.db_operacion.host' => $host]);
    }

    public function testCancelNotFound() {
        $response = $this->json('POST', '/v1/cancel', ['referencia' => $this->referencia], ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertEquals(424, $response->response->getStatusCode());
        $this->assertEquals("error", $VAR->data);
        $this->assertEquals("Referencia no encontrada", $VAR->error['message']);
    }

    public function testCancelParamLost() {
        $response = $this->json('POST', '/v1/cancel', ['referenscia' => $this->referencia], ['Authorization' => 'Bearer ' . getenv("APP_KEY")]);
        $VAR = $response->response->getOriginalContent();
        $this->assertEquals(425, $response->response->getStatusCode());
        $this->assertEquals("error", $VAR->data);
        $this->assertEquals("Parametro Requerido", $VAR->error['message']);
    }

}
