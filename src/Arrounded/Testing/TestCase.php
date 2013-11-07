<?php
namespace Arrounded\Testing;

use Artisan;
use Auth;
use Closure;
use Eloquent;
use Illuminate\Foundation\Testing\TestCase as IlluminateTestCase;
use Mockery;
use Redirect;
use Schema;
use User;

class TestCase extends IlluminateTestCase
{
	/**
	 * Some aliases for mocks
	 *
	 * @var array
	 */
	protected $namespaces = array(
		'app' => '',
	);

	////////////////////////////////////////////////////////////////////
	//////////////////////////// TESTS LIFETIME ////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Recreate the database
	 *
	 * @return void
	 */
	protected function recreateDatabase()
	{
		if (!Schema::hasTable('migrations')) {
			Artisan::call('migrate:install');
			Artisan::call('migrate');
		}

		$this->seedDatabase();
		Eloquent::reguard();
	}

	/**
	 * Seed the database with dummy data
	 *
	 * @return void
	 */
	protected function seedDatabase()
	{
		// ...
	}

	/**
	 * Remove mocked instances on close
	 *
	 * @return void
	 */
	public function tearDown()
	{
		// Remove mocked instances
		Mockery::close();

		// Close connection
		unset($this->app['db']);
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////// AUTHENTIFICATION ///////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Authentify as an User
	 *
	 * @return User
	 */
	public function authentify($user = null)
	{
		$user = $user ?: User::first();
		if (!$user) {
			return;
		}

		// Log in
		$this->be($user);
		Auth::setUser($user);

		return $user;
	}

	/**
	 * Get the test user
	 *
	 * @return User
	 */
	public function testUser()
	{
		return $this->app['auth']->user();
	}

	/**
	 * Logout the user
	 *
	 * @return void
	 */
	public function logout()
	{
		$this->app['auth']->logout();
		Auth::logout();
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// REQUESTS ///////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Spoof the Redirect::back method
	 *
	 * @param  string $endpoint
	 *
	 * @return void
	 */
	protected function spoofRedirectBack($endpoint = '/')
	{
		$redirect = Redirect::to($endpoint);
		Redirect::shouldReceive('back')->andReturn($redirect);
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////////// MOCKING ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Mock a repository
	 *
	 * @param sring   $repository
	 * @param Closure $expectations
	 *
	 * @return Mockery
	 */
	protected function mockRepository($repository, Closure $expectations)
	{
		$mocked = $this->getMockedClass('Repositories\\'.$repository.'Repository');

		return $this->mock($mocked, $expectations);
	}

	/**
	 * Mock a class and inject it into the container
	 *
	 * @param  string  $class
	 * @param  Closure $expectations
	 *
	 * @return void
	 */
	protected function mock($class, $expectations)
	{
		$mock = Mockery::mock($class);
		$mock = $expectations($mock)->mock();

		$this->app->instance($class, $mock);
	}

	/**
	 * Get the full path to a mocked class
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	protected function getMockedClass($class)
	{
		return $this->namespaces['app'].'\\'.$class;
	}
}