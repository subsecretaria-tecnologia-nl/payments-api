<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class PayTest extends TestCase {

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testPaySuccess() {

        $response = $this->json("post", '/v1/pay', array(''));
        var_dump($response->response->getStatusCode());

        $this->assertEquals(
                401, $response->response->getStatusCode()
        );
    }

}
