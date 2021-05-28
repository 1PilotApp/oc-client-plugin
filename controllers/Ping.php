<?php

namespace OnePilot\Client\Controllers;

use Illuminate\Routing\Controller;
use OnePilot\Client\Classes\Response;

class Ping extends Controller
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function ping()
    {
        return Response::make([
            'message' => "pong",
        ]);
    }
}
