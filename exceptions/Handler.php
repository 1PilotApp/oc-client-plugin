<?php

namespace OnePilot\Client\Exceptions;

use App;

class Handler
{
    /**
     * Register our own exception handler
     */
    public static function register()
    {
        App::error(function (OnePilotException $exception) {
            $content = [
                'status' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'data' => [],
            ];

            if (!empty($previous = $exception->getPrevious())) {
                $content['data']['previous'] = $previous;
            }

            if (!empty($exceptionData = $exception->getData())) {
                $content['data'] = array_merge($content['data'], $exceptionData);
            }

            return $content;
        });
    }
}
