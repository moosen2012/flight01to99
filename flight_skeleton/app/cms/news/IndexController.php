<?php
namespace app\cms\news;

use flight\Engine;

class IndexController {

	protected Engine $app;

	public function __construct($app) {
		$this->app = $app;
	}

    public function index() {
		return "";
	}


}