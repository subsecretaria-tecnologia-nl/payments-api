<?php
public function map()
{
	$this->mapApiRoutes();
}

protected function mapApiRoutes()
{
	Route::prefix('api/v1')
	// ->middleware('authorization')
	->namespace($this->namespace)
	->group(base_path('routes/api.php'));
}