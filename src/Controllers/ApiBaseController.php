<?php

namespace Hanafalah\LaravelSupport\Controllers;

class ApiBaseController extends BaseController
{
    /**
     * Construct the class, this class is being used as a parent of another API controller class
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
}
